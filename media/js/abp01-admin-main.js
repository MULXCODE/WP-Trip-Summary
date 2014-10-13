(function($) {
    "use strict";

    /**
     * Define available tour types
     * */

    var TOUR_TYPE_BIKE = 'bike';
    var TOUR_TYPE_HIKING = 'hiking';
    var TOUR_TYPE_TRAIN_RIDE = 'trainRide';

    /**
     * Define server-side track upload error codes
     * */

    var UPLOAD_OK = 0;
    var UPLOAD_INVALID_MIME_TYPE = 1;
    var UPLOAD_TOO_LARGE = 2;
    var UPLOAD_NO_FILE = 3;
    var UPLOAD_INTERNAL_ERROR = 4;
    var UPLOAD_STORE_FAILED = 5;
    var UPLOAD_NOT_VALID = 6;
    var UPLOAD_FAILED = 99;

    /**
     * Current state information
     * */

    var baseTitle = null;
    var progressBar = null;
    var currentTourType = null;
    var formInfoRendered = false;
    var formMapRendered = false;
    var firstTime = true;
    var uploader = null;
    var context = null;
    var map = null;

    /**
     * Configuration objects - they are initialized at startup
     * */

    var typeSelectRenderers = {};
    var typeTitles = {};
    var tabHandlers = {};
    var uploaderErrors = {
        server: {},
        client: {}
    };

    /**
     * Cache jQuery object references
     * */

    var $ctrlEditorTabs = null;
    var $ctrlResetTechBox = null;
    var $ctrlTitleContainer = null;
    var $ctrlFormInfoContainer = null;
    var $ctrlFormMapContainer = null;
    var $ctrlEditor = null;

    /**
     * Cache rendered content
     * */

    var contentFormInfoBikeTour = null;
    var contentFormInfoHikingTour = null;
    var contentFormInfoTrainRide = null;
    var contentFormInfoUnselected = null;

    var contentFormMapUnselected = null;
    var contentFormMapUploaded = null;

    /**
     * Templates and rendering functions
     * */

    function renderFormMapUnselected() {
        if (contentFormMapUnselected == null) {
            contentFormMapUnselected = kite('#tpl-abp01-formMap-unselected')();
        }
        return contentFormMapUnselected;
    }

    function renderFormInfoBikeTour() {
        if (contentFormInfoBikeTour == null) {
            contentFormInfoBikeTour = kite('#tpl-abp01-formInfo-bikeTour')();
        }
        return contentFormInfoBikeTour;
    }

    function renderFormInfoHikingTour() {
        if (contentFormInfoHikingTour == null) {
            contentFormInfoHikingTour = kite('#tpl-abp01-formInfo-hikingTour')();
        }
        return contentFormInfoHikingTour;
    }

    function renderFormInfoTrainRideTour() {
        if (contentFormInfoTrainRide == null) {
            contentFormInfoTrainRide = kite('#tpl-abp01-formInfo-trainRide')();
        }
        return contentFormInfoTrainRide;
    }

    function renderFormMapUploaded() {
        if (contentFormMapUploaded == null) {
            contentFormMapUploaded = kite('#tpl-abp01-formMap-uploaded')();
        }
        return contentFormMapUploaded;
    }

    function renderFormInfoUnselected() {
        if (contentFormInfoUnselected == null) {
            contentFormInfoUnselected = kite('#tpl-abp01-formInfo-unselected')();
        }
        return contentFormInfoUnselected;
    }

    function updateTitle(title) {
        if (title) {
            $ctrlTitleContainer.html([baseTitle, title].join(' - '));
        } else {
            $ctrlTitleContainer.html(baseTitle);
        }
    }

    /**
     * Window state management
     * */

    function toastMessage(success, message) {
        if (success) {
            toastr.success(message);
        } else {
            toastr.error(message);
        }
    }

    function showProgress(progress, text) {
        if ($ctrlEditor.data('isBlocked')) {
            return;
        }

        var isDeterminate = progress !== false;
        if (progressBar != null) {
            progressBar.update({
                progress: progress,
                message: text
            });
        } else {
            progressBar = $('#tpl-abp01-progress-container').progressOverlay({
                $target: $ctrlEditor,
                determinate: isDeterminate,
                progress: progress,
                message: text
            });
        }
    }

    function hideProgress() {
        if (progressBar) {
            progressBar.destroy();
            progressBar = null;
        }
    }

    /**
     * Reset button management functions
     * */

    function executeResetAction() {
        var handler = $ctrlResetTechBox.data('formInfoResetHandler');
        if (!handler || !$.isFunction(handler)) {
            handler = $ctrlResetTechBox.data('formMapResetHandler');
        }
        if (handler && $.isFunction(handler)) {
            handler();
        }
    }

    function toggleFormInfoReset(enable) {
        if (enable) {
            var resetHandler = arguments.length == 2 ? arguments[1] : null;
            $ctrlResetTechBox.text('Clear info').show();
            $ctrlResetTechBox.data('formInfoResetHandler', resetHandler);
        } else {
            $ctrlResetTechBox.hide();
            $ctrlResetTechBox.data('formInfoResetHandler', null);
        }
    }

    function toggleFormMapReset(enable) {
        if (enable) {
            var resetHandler = arguments.length == 2 ? arguments[1] : null;
            $ctrlResetTechBox.text('Clear track').show();
            $ctrlResetTechBox.data('formMapResetHandler', resetHandler);
        } else {
            $ctrlResetTechBox.hide();
            $ctrlResetTechBox.data('formMapResetHandler', null);
        }
    }

    function clearInputValues($container) {
        var $input = $container.find('input,select,textarea');
        $input.each(function(idx, el) {
            var $field = $(this);
            var tagName = $field.prop('tagName');

            if (!tagName) {
                return;
            }

            tagName = tagName.toLowerCase();
            if (tagName == 'input') {
                var inputType = $field.attr('type');
                inputType = inputType.toLowerCase();
                if (inputType == 'text') {
                    $field.val('');
                } else if (inputType == 'checkbox') {
                    $field.prop('checked', false);
                    if ($field.iCheck) {
                        $field.iCheck('update');
                    }
                }
            } else if (tagName == 'select') {
                $field.val('0');
            }
        });
    }

    /**
     * Checkbox management functions
     * */

    function prepareCheckboxes($container) {
        $container.find('input[type=checkbox]').iCheck({
            checkboxClass: 'icheckbox_minimal-blue',
            radioClass: 'iradio_minimal-blue'
        });
    }

    function destroyCheckboxes($container) {
        $container.find('input[type=checkbox]')
            .iCheck('destroy');
    }

    function selectFormInfoTourType(type, clearForm) {
        currentTourType = type;
        $ctrlFormInfoContainer.empty();

        if (typeof typeSelectRenderers[type] == 'function') {
            $ctrlFormInfoContainer.html(typeSelectRenderers[type]());
            prepareCheckboxes($ctrlFormInfoContainer);
            toggleFormInfoReset(true, resetFormInfo);
            updateTitle(typeTitles[type]);

            if (clearForm) {
                clearInputValues($ctrlFormInfoContainer);
            }
        } else {
            updateTitle(null);
            toggleFormInfoReset(false);
            currentTourType = null;
        }
    }

    /**
     * Tour info editor management functions
     * */

    function initFormInfo() {
        typeSelectRenderers[TOUR_TYPE_BIKE] = renderFormInfoBikeTour;
        typeSelectRenderers[TOUR_TYPE_HIKING] = renderFormInfoHikingTour;
        typeSelectRenderers[TOUR_TYPE_TRAIN_RIDE] = renderFormInfoTrainRideTour;

        typeTitles[TOUR_TYPE_BIKE] = 'Biking';
        typeTitles[TOUR_TYPE_HIKING] = 'Hiking';
        typeTitles[TOUR_TYPE_TRAIN_RIDE] = 'Train ride';
    }

    function resetFormInfo() {
        clearInputValues($ctrlFormInfoContainer);
        clearInfo();
    }

    function switchToFormInfoSelection() {
        currentTourType = null;

        toggleFormInfoReset(false);
        destroyCheckboxes($ctrlFormInfoContainer);
        updateTitle(null);

        $ctrlFormInfoContainer.empty()
            .html(renderFormInfoUnselected());
    }

    function showFormInfo($container) {
        if (!formInfoRendered) {
            var crType = window.abp01_tourType || null;
            if (!crType) {
                $container.html(renderFormInfoUnselected());
            } else {
                selectFormInfoTourType(crType, false);
            }
            formInfoRendered = true;
        }

        toggleFormMapReset(false);
        if (!currentTourType) {
            toggleFormInfoReset(false);
        } else {
            toggleFormInfoReset(true, resetFormInfo);
        }
    }

    function getFormInfoValues($container) {
        var $input = $container.find('input,select,textarea');
        var values = {
            type: currentTourType
        };

        function isMultiCheckbox(inputName) {
            return inputName.indexOf('[]') == inputName.length - 2;
        }

        function getSimpleCheckboxName(inputName) {
            return inputName.substring(0, inputName.length - 2);
        }

        function addFormValue(name, value, isMultiple) {
            if (isMultiple) {
                if (typeof values[name] == 'undefined') {
                    values[name] = [];
                }
                if (value !== null) {
                    values[name].push(value);
                }
            } else {
                values[name] = value;
            }
        }

        $input.each(function(idx, el) {
            var $field = $(this);
            var tagName = $field.prop('tagName');
            var inputName = $field.attr('name');
            var inputType = $field.attr('type');

            if (!tagName || !inputName) {
                return;
            }

            tagName = tagName.toLowerCase();
            inputName = inputName.replace('ctrl_abp01_', '');

            if (tagName == 'input') {
                if (inputType == 'text') {
                    addFormValue(inputName, $field.val());
                } else if (inputType == 'checkbox') {
                    var checked = $field.is(':checked');
                    var isMultiple = isMultiCheckbox(inputName);
                    if (isMultiple) {
                        inputName = getSimpleCheckboxName(inputName);
                    }
                    addFormValue(inputName, (checked ? $field.val() : null), isMultiple);
                }
            } else if (tagName == 'select') {
                addFormValue(inputName, $field.val(), false);
            } else if (tagName == 'textarea') {
                addFormValue(inputName, $field.val(), false);
            }
        });

        return values;
    }

    function saveInfo() {
        showProgress(false, 'Saving data. Please wait...');
        $.ajax(getAjaxEditInfoUrl(), {
            type: 'POST',
            dataType: 'json',
            data: getFormInfoValues($ctrlFormInfoContainer)
        }).done(function(data, status, xhr) {
            hideProgress();
            if (data) {
                if (data.success) {
                    toastMessage(true, 'The data has been saved');
                } else {
                    toastMessage(false, data.message || 'The data could not be saved');
                }
            } else {
                toastMessage(false, 'The data could not be saved');
            }
        }).fail(function() {
            hideProgress();
            toastMessage(false, 'The data could not be saved due to a possible network error or an internal server issue');
        });
    }

    function clearInfo() {
        showProgress(false, 'Clearing trip info. Please wait...');
        $.ajax(getAjaxClearInfoUrl(), {
            type: 'POST',
            dataType: 'json',
            data: {}
        }).done(function(data, status, xhr) {
            hideProgress();
            if (data) {
                if (data.success) {
                    switchToFormInfoSelection();
                    toastMessage(true, 'The trip info has been cleared');
                } else {
                    toastMessage(false, data.message || 'The trip info could not be clear');
                }
            } else {
                toastMessage(false, 'The trip info could not be cleared');
            }
        }).fail(function() {
            hideProgress();
            toastMessage(false, 'The trip info could not be cleared due to a possible network error or an internal server issue');
        });
    }

    /**
     * Tour map editor management functions
     * */

    function initFormMap() {
        uploaderErrors.client[plupload.FILE_SIZE_ERROR] = 'The selected file is too large. Maximum allowed size is 10MB';
        uploaderErrors.client[plupload.FILE_EXTENSION_ERROR] = 'The selected file type is not valid. Only GPX files are allowed';
        uploaderErrors.client[plupload.IO_ERROR] = 'The file could not be read';
        uploaderErrors.client[plupload.SECURITY_ERROR] = 'The file could not be read';
        uploaderErrors.client[plupload.INIT_ERROR] = 'The uploader could not be initialized';
        uploaderErrors.client[plupload.HTTP_ERROR] = 'The file could not be uploaded';

        uploaderErrors.server[UPLOAD_INVALID_MIME_TYPE] = 'The selected file type is not valid. Only GPX files are allowed';
        uploaderErrors.server[UPLOAD_TOO_LARGE] = 'The selected file is too large. Maximum allowed size is 10MB';
        uploaderErrors.server[UPLOAD_NO_FILE] = 'No file was uploaded';
        uploaderErrors.server[UPLOAD_INTERNAL_ERROR] = 'The file could not be uploaded due to a possible internal server issue';
        uploaderErrors.server[UPLOAD_FAILED] = 'The file could not be uploaded';
    }

    function resetFormMap() {
        map.destroyMap();
        map = null;

        context.hasTrack = false;
        $ctrlFormMapContainer.empty().html(renderFormMapUnselected());

        toggleFormMapReset(false);
        createTrackUploader();
    }

    function showFormMap($container) {
        if (!formMapRendered) {
            formMapRendered = true;
            if (!context.hasTrack) {
                $container.html(renderFormMapUnselected());
                createTrackUploader();
            } else {
                showMap();
            }
        } else {
            if (map != null) {
                map.forceRedraw();
            }
        }

        toggleFormInfoReset(false);
        if (!context.hasTrack) {
            toggleFormMapReset(false);
        } else {
            toggleFormMapReset(true, clearTrack);
        }
    }

    function clearTrack() {
        showProgress(false, 'Clearing track. Please wait...');
        $.ajax(getAjaxClearTrackUrl(), {
            type: 'POST',
            dataType: 'json',
            cache: false
        }).done(function(data, status, xhr) {
            hideProgress();
            if (data && data.success) {
                resetFormMap();
                toastMessage(true, 'The track has been successfully cleared');
            } else {
                toastMessage(false, data.message || 'The data could not be updated');
            }
        }).fail(function(xhr, status, error) {
            hideProgress();
            toastMessage(false, 'The data could not be updated due to a possible network error or an internal server issue');
        });
    }

    function createTrackUploader() {
        if (uploader != null) {
            return;
        }

        uploader = new plupload.Uploader({
            browse_button: 'abp01-track-selector',
            filters: {
                max_file_size: window.abp01_uploadMaxFileSize || 10485760,
                mime_types: [
                    { title: 'GPX files', extensions: 'gpx' }
                ]
            },
            runtimes: 'html5,flash,silverlight',
            flash_swf_url: window.abp01_flashUploaderUrl || '',
            silverlight_xap_url: window.abp01_xapUploaderUrl || '',
            multipart: true,
            multipart_params: {},
            chunk_size: window.abp01_uploadChunkSize || 102400,
            url: getAjaxUploadTrackUrl(),
            multi_selection: false,
            urlstream_upload: true,
            unique_names: false,
            file_data_name: window.abp01_uploadKey || 'file',
            init: {
                FilesAdded: handleUploaderFilesAdded,
                UploadProgress: handleUploaderProgress,
                UploadComplete: handleUploaderCompleted,
                ChunkUploaded: handleChunkCompleted,
                Error: handleUploaderError
            }
        });

        uploader.init();
    }

    function destroyTrackUploader() {
        if (uploader == null) {
            return;
        }

        uploader.unbindAll();
        uploader.destroy();
        uploader = null;
    }

    function handleUploaderError(upl, error) {
        uploader.disableBrowse(false);
        uploader.refresh();
        hideProgress();
        toastMessage(false, getTrackUploaderErrorMessage(error));
    }

    function handleUploaderFilesAdded(upl, files) {
        if (!files || !files.length) {
            return;
        }

        var file = files[0];
        if (file.size <= 102400) {
            uploader.setOption('chunk_size', Math.round(file.size / 2));
        } else {
            uploader.setOption('chunk_size', 102400);
        }

        uploader.disableBrowse(true);
        uploader.start();
    }

    function handleUploaderCompleted(upl) {
        context.hasTrack = true;
        uploader.disableBrowse(false);

        destroyTrackUploader();
        toggleFormMapReset(true, clearTrack);
        toastMessage(true, 'The track has been uploaded and saved successfully');
        showMap();
    }

    function handleChunkCompleted(upl, file, info) {
        var status = 0;
        var response = info.response || null;
        if (response != null) {
            try {
                response = JSON.parse(response);
                status = parseInt(response.status || 0);
            } catch (e) {
                status = UPLOAD_FAILED;
            }
        } else {
            status = UPLOAD_FAILED;
        }

        if (status != UPLOAD_OK) {
            uploader.stop();
            uploader.disableBrowse(false);
            hideProgress();
            toastMessage(false, getTrackUploaderErrorMessage({
                server: true,
                code: status
            }));
        }
    }

    function handleUploaderProgress(upl, file) {
        showProgress(file.percent / 100, 'Uploading track: ' + file.percent + '%');
    }

    function getTrackUploaderErrorMessage(err) {
        var message = null;
        if (err.hasOwnProperty('server') && err.server === true) {
            message = uploaderErrors.server[err.code] || null;
        } else {
            message = uploaderErrors.client[err.code] || null;
        }
        if (!message) {
            message = uploaderErrors.server[UPLOAD_FAILED];
        }
        return message;
    }

    function showMap() {
        map = $ctrlFormMapContainer.empty()
            .html(renderFormMapUploaded())
            .find('#abp01-map')
            .mapTrack({
                iconBaseUrl: context.imgBase,
                trackDataUrl: getAjaxLoadTrackUrl(),
                handlePreLoad: function() {
                    showProgress(false, 'Generating preview. Please wait...');
                },
                handleLoad: function(success) {
                    hideProgress();
                }
            });
    }

    /**
     * Main editor management functions
     * */

    function getContext() {
        return {
            nonce: $('#abp01-nonce').val(),
            imgBase: window['abp01_imgBase'] || null,
            nonceGet: $('#abp01-nonce-get').val(),
            postId: window['abp01_postId'] || 0,
            hasTrack: window['abp01_hasTrack'] || 0,
            ajaxBaseUrl: window['abp01_ajaxUrl'] || null,
            ajaxLoadTrackAction: window['abp01_ajaxGetTrackAction'] || null,
            ajaxUploadTrackAction: window['abp01_ajaxUploadTrackAction'] || null,
            ajaxEditInfoAction: window['abp01_ajaxEditInfoAction'] || null,
            ajaxClearTrackAction: window['abp01_ajaxClearTrackAction'] || null,
            ajaxClearInfoAction: window['abp01_ajaxClearInfoAction'] || null
        };
    }

    function initEditorState() {
        context = getContext();
        baseTitle = window['abp01_baseTitle'] || '';
    }

    function initEditorControls() {
        $ctrlEditor = $('#abp01-techbox-editor');
        $ctrlTitleContainer = $('#ctrl_abp01_editorTitle');
        $ctrlFormInfoContainer = $('#abp01-form-info');
        $ctrlFormMapContainer = $('#abp01-form-map');
        $ctrlResetTechBox = $('#abp01-resetTechBox');
    }

    function initToastMessages() {
        $.extend(toastr.options, {
            iconClasses: {
                error: 'abp01-toast-error',
                info: 'abp01-toast-info',
                success: 'abp01-toast-success',
                warning: 'abp01-toast-warning'
            },
            target: '#abp01-editor-content',
            positionClass: 'toast-bottom-right',
            timeOut: 4000
        });
    }

    function initEventHandlers() {
        $ctrlResetTechBox .click(function() {
            executeResetAction();
        });

        $('#abp01-saveTechBox').click(function() {
            saveInfo();
        });

        $(document)
            .on('click', 'a[data-action=abp01-openTechBox]', {}, function() {
                openEditor();
            })
            .on('click', 'a[data-action=abp01-closeTechBox]', {}, function() {
                $.unblockUI();
            })

            .on('click', 'a[data-action=abp01-typeSelect]', {}, function() {
                selectFormInfoTourType($(this).attr('data-type'), true);
            });
    }

    function initEditor() {
        initEditorState();
        initToastMessages();
        initEditorControls();
        initEventHandlers();
    }

    function openEditor() {
        var $window = $(window);
        var blockUICss = $.blockUI.defaults.css;

        $.blockUI({
            message: $ctrlEditor,
            css: {
                top: ($window.height() - blockUICss.height) / 2,
                left: ($window.width() - blockUICss.width) / 2,
                boxShadow: '0 5px 15px rgba(0, 0, 0, 0.7)'
            },
            onBlock: function() {
                if (firstTime) {
                    initTabs();
                    firstTime = false;
                } else {
                    if (map != null && $ctrlFormMapContainer.is(':visible')) {
                        map.forceRedraw();
                    }
                }
            }
        });
    }

    function getAjaxEditInfoUrl() {
        var context = getContext();
        return URI(context.ajaxBaseUrl)
            .addSearch('action', context.ajaxEditInfoAction)
            .addSearch('abp01_nonce', context.nonce || '')
            .addSearch('abp01_postId', context.postId || 0)
            .toString();
    }

    function getAjaxClearInfoUrl() {
        var context = getContext();
        return URI(context.ajaxBaseUrl)
            .addSearch('action', context.ajaxClearInfoAction)
            .addSearch('abp01_nonce', context.nonce || '')
            .addSearch('abp01_postId', context.postId || 0)
            .toString();
    }

    function getAjaxUploadTrackUrl() {
        var context = getContext();
        return URI(context.ajaxBaseUrl)
            .addSearch('action', context.ajaxUploadTrackAction)
            .addSearch('abp01_nonce', context.nonce || '')
            .addSearch('abp01_postId', context.postId || 0)
            .toString();
    }

    function getAjaxLoadTrackUrl() {
        var context = getContext();
        return URI(context.ajaxBaseUrl)
            .addSearch('action', context.ajaxLoadTrackAction)
            .addSearch('abp01_nonce_get', context.nonceGet || '')
            .addSearch('abp01_postId', context.postId || '')
            .toString();
    }

    function getAjaxClearTrackUrl() {
        var context = getContext();
        return URI(context.ajaxBaseUrl)
            .addSearch('action', context.ajaxClearTrackAction)
            .addSearch('abp01_nonce', context.nonce || '')
            .addSearch('abp01_postId', context.postId || '')
            .toString();
    }

    /**
     * Editor tabs management functions
     * */

    function initTabs() {
        tabHandlers['abp01-form-info'] = showFormInfo;
        tabHandlers['abp01-form-map'] = showFormMap;

        $ctrlEditorTabs = $('#abp01-editor-content').easytabs({
            animate: false,
            tabActiveClass: 'abp01-tab-active',
            panelActiveClass: 'abp01-tabContentActive',
            defaultTab: '#abp01-tab-info',
            updateHash: false
        });

        $ctrlEditorTabs.bind('easytabs:after', function(e, $clicked, $target, settings) {
            selectTab($target);
        });

        selectTab($('#abp01-form-info'));
    }

    function selectTab($target) {
        var target = $target.attr('id');
        if (typeof tabHandlers[target] == 'function') {
            tabHandlers[target]($target);
        }
    }

    $(document).ready(function() {
        $.blockUI.defaults.css = {
            width: 720,
            height: 590
        };

        initEditor();
        initFormInfo();
        initFormMap();
    });
})(jQuery);