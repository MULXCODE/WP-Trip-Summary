<?php
/**
 * Copyright (c) 2014-2016, Alexandru Boia
 * All rights reserved.

 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *  - Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 *  - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *  - Neither the name of the <organization> nor the
 *    names of its contributors may be used to endorse or promote products
 *    derived from this software without specific prior written permission.

 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if (!defined('ABP01_LOADED') || !ABP01_LOADED) {
	exit;
}

/**
 * Provides the means of managing plug-in settings. 
 * It contains methods to get, set and persist plug-in settings.
 * The settings are retrieved automatically the first time one of these events happen:
 * - An option is read;
 * - An option is set to a new value.
 * In order to be persisted, however, the saveSettings() method has to be called explicitly.
 * It uses the WP options API (see https://codex.wordpress.org/Options_API) to read and persist settings.
 * */
class Abp01_Settings {
	/**
	 * Key for the "show teaser" setting
	 * */
	const OPT_TEASER_SHOW = 'showTeaser';

	/**
	 * Key for the "top teaser text" setting
	 * */
	const OPT_TEASER_TOP = 'teaserTopTxt';

	/**
	 * Key for the "bottom teaser text" setting
	 * */
	const OPT_TEASER_BOTTOM = 'teaserBottomTxt';

	/**
	 * Key for the tile layer settings
	 * */
	const OPT_MAP_TILE_LAYER_URLS = 'mapTileLayerUrls';

	/**
	 * Key for the "show magnifying glass" setting
	 * */
	const OPT_MAP_FEATURES_MAGNIFYING_GLASS_SHOW = 'mapMagnifyingGlassShow';

	/**
	 * Key for the "show full screen" setting
	 * */
	const OPT_MAP_FEATURES_FULL_SCREEN_SHOW = 'mapFullScreenShow';

	/**
	 * Key for the "show map scale" setting
	 * */
	const OPT_MAP_FEATURES_SCALE_SHOW = 'mapScaleShow';

	/**
	 * Key for the unit system setting
	 * */
	const OPT_UNIT_SYSTEM = 'unitSystem';

	/**
	 * Key for the "allow track download" setting
	 * */
	const OPT_ALLOW_TRACK_DOWNLOAD = 'allowTrackDownload';

	/**
	 * The key used to store the serialized settings, using the WP options API
	 * */
	const OPT_SETTINGS_KEY = 'abp01.settings';

	private static $_instance = null;

	/**
	 * Holds a cache of the setting array, to avoid repeatedly looking up the settings
	 * */
	private $_data = null;

	private function __construct() {
		return;
	}

	public function __clone() {
		throw new Exception('Cloning a singleton of type ' . __CLASS__ . ' is not allowed');
	}

	public static function getInstance() {
		if (self::$_instance == null) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Loads the settings if the local cache is not yet set.
	 * The cache is considered unset if it has a null value.
	 * If no data is found, the cache is initialized with an empty array.
	 * @return void
	 * */
	private function _loadSettingsIfNeeded() {
		if ($this->_data === null) {
			$this->_data = get_option(self::OPT_SETTINGS_KEY, array());
			if (!is_array($this->_data)) {
				$this->_data = array();
			}
		}
	}

	private function _getOption($key, $type, $default) {
		$this->_loadSettingsIfNeeded();
		$optionValue = isset($this->_data[$key]) ? $this->_data[$key] : $default;
		if (!settype($optionValue, $type)) {
			$optionValue = $default;
		}
		$this->_data[$key] = $optionValue;
		return $optionValue;
	}

	private function _setOption($key, $type, $value) {
		$this->_loadSettingsIfNeeded();
		$this->_data[$key] = Abp01_InputFiltering::filterValue($value, $type);
	}

	/**
	 * Normalizes the given tile layer instance. Normalization consists of: 
	 * - checking that the given object is indeed an object and that it has the url property set;
	 * - if attributionTxt property does not exist, it is set to null;
	 * - if attributionUrl property does not exist, it is set to null.
	 * @param Object $tileLayer The tile layer descriptor to be checked and normalized
	 * @return object Either false (if the tile layer is not an object, or if the url property is not set), or the normalized object.
	 * */
	private function _checkAndNormalizeTileLayer($tileLayer) {
		if (!is_object($tileLayer) || empty($tileLayer->url)) {
			return false;
		}
		if (!isset($tileLayer->attributionTxt)) {
			$tileLayer->attributionTxt = null;
		}
		if (!isset($tileLayer->attributionUrl)) {
			$tileLayer->attributionUrl = null;
		}
		return $tileLayer;
	}

	private function _getDefaultTileLayer() {
		$tileLayer = new stdClass();
		$tileLayer->url = 'http://{s}.tile.osm.org/{z}/{x}/{y}.png';
		$tileLayer->attributionTxt = 'OpenStreetMap & Contributors';
		$tileLayer->attributionUrl = 'http://osm.org/copyright';
		return $tileLayer;
	}

	public function getShowTeaser() {
		return $this->_getOption(self::OPT_TEASER_SHOW, 'boolean', true);
	}

	public function setShowTeaser($showTeaser) {
		$this->_setOption(self::OPT_TEASER_SHOW, 'boolean', $showTeaser);
		return $this;
	}

	public function getTopTeaserText() {
		return $this->_getOption(self::OPT_TEASER_TOP, 'string', __('For the pragmatic sort, there is also a trip summary at the bottom of this page. Click here to consult it', 'abp01-trip-summary'));
	}

	public function setTopTeaserText($topTeaserText) {
		$this->_setOption(self::OPT_TEASER_TOP, 'string', $topTeaserText);
		return $this;
	}

	public function getBottomTeaserText() {
		return $this->_getOption(self::OPT_TEASER_BOTTOM, 'string', __('It looks like you skipped the story. You should check it out. Click here to go back to beginning', 'abp01-trip-summary'));
	}

	public function setBottomTeaserText($bottomTeaserText) {
		$this->_setOption(self::OPT_TEASER_BOTTOM, 'string', $bottomTeaserText);
		return $this;
	}

	public function getTileLayers() {
		return $this->_getOption(self::OPT_MAP_TILE_LAYER_URLS, 'array', array($this->_getDefaultTileLayer()));
	}

	public function setTileLayers($tileLayers) {
		$saveLayers = array();
		if (!is_array($tileLayers)) {
			$tileLayers = array($tileLayers);
		}
		foreach ($tileLayers as $layer) {
			$layer = $this->_checkAndNormalizeTileLayer($layer);
			if ($layer) {
				$saveLayers[] = $layer;
			}
		}
		if (!count($saveLayers)) {
			throw new InvalidArgumentException('tileLayers');
		}
		$this->_setOption(self::OPT_MAP_TILE_LAYER_URLS, 'string', $saveLayers);
		return $this;
	}

	public function getAllowTrackDownload() {
		return $this->_getOption(self::OPT_ALLOW_TRACK_DOWNLOAD, 'boolean', true);
	}

	public function setAllowTrackDownload($allowTrackDownload) {
		$this->_setOption(self::OPT_ALLOW_TRACK_DOWNLOAD, 'boolean', $allowTrackDownload);
		return $this;
	}

	public function getShowFullScreen() {
		return $this->_getOption(self::OPT_MAP_FEATURES_FULL_SCREEN_SHOW, 'boolean', true);
	}

	public function setShowFullScreen($showFullScreen) {
		$this->_setOption(self::OPT_MAP_FEATURES_FULL_SCREEN_SHOW, 'boolean', $showFullScreen);
		return $this;
	}

	public function getShowMapScale() {
		return $this->_getOption(self::OPT_MAP_FEATURES_SCALE_SHOW, 'boolean', true);
	}

	public function setShowMapScale($showMapScale) {
		$this->_setOption(self::OPT_MAP_FEATURES_SCALE_SHOW, 'boolean', $showMapScale);
		return $this;
	}

	public function getShowMagnifyingGlass() {
		return $this->_getOption(self::OPT_MAP_FEATURES_MAGNIFYING_GLASS_SHOW, 'boolean', true);
	}

	public function setShowMagnifyingGlass($showMagnifyingGlass) {
		$this->_setOption(self::OPT_MAP_FEATURES_MAGNIFYING_GLASS_SHOW, 'boolean', $showMagnifyingGlass);
		return $this;
	}

	public function getUnitSystem() {
		return $this->_getOption(self::OPT_UNIT_SYSTEM, 'string', Abp01_UnitSystem::METRIC);
	}

	public function setUnitSystem($unitSystem) {
		$allowedUnitSystems = $this->getAllowedUnitSystems();
		if (!in_array($unitSystem, $allowedUnitSystems)) {
			$unitSystem = $this->getUnitSystem();
		}
		$this->_setOption(self::OPT_UNIT_SYSTEM, 'string', $unitSystem);
		return $this;
	}

	public function saveSettings() {
		$this->_loadSettingsIfNeeded();
		update_option(self::OPT_SETTINGS_KEY, $this->_data);
		return true;
	}

	public function purgeAllSettings() {
		$this->clearSettingsCache();
		return delete_option(self::OPT_SETTINGS_KEY);
	}

	public function clearSettingsCache() {
		$this->_data = null;
	}

	public function getAllowedUnitSystems() {
		return array(Abp01_UnitSystem::METRIC, Abp01_UnitSystem::IMPERIAL);
	}
}
