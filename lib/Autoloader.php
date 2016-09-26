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

class Abp01_Autoloader {
    private static $_libDir;

    private static $_initialized = false;

    private static $_prefix = 'Abp01_';

    public static function init($libDir) {
        if (!self::$_initialized) {
            spl_autoload_register(array(__CLASS__, 'autoload'));
            self::$_libDir = $libDir;
            self::$_initialized = true;
        }
    }

    private static function autoload($className) {
        $classPath = null;
        if (strpos($className, self::$_prefix) === 0) {
            $classPath = str_replace(self::$_prefix, '', $className);
            $classPath = self::_getRelativePath($classPath);
            $classPath = self::$_libDir . '/' . $classPath . '.php';
        } else {
            $classPath = self::$_libDir . '/3rdParty/' . $className . '.php';
        }
        if (!empty($classPath) && file_exists($classPath)) {
            require_once $classPath;
        }
    }

    private static function _getRelativePath($className) {
        $classPath = array();
        $pathParts = explode('_', $className);
        $className = array_pop($pathParts);
        foreach ($pathParts as $namePart) {
            $namePart[0] = strtolower($namePart[0]);
            $classPath[] = $namePart;
        }
        $classPath[] = $className;
        return implode('/', $classPath);
    }
}