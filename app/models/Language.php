<?php
class Language {
    private static $translations = [];
    private static $currentLang = 'tr';

    public static function init($lang) {
        self::$currentLang = $lang;
        $langFile = dirname(__DIR__) . '/languages/' . $lang . '.php';
        if (file_exists($langFile)) {
            self::$translations = require $langFile;
        }
    }

    public static function get($key) {
        return self::$translations[$key] ?? $key;
    }

    public static function getCurrentLang() {
        return self::$currentLang;
    }
} 