<?php

namespace App\Helpers;

class ArabicHelper
{
    private static $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /**
     * Convert Western (English) digits to Arabic-Indic digits.
     *
     * @param  string|int  $value
     * @return string
     */
    public static function toArabicDigits($value)
    {
        return preg_replace_callback('/[0-9]/', function ($match) {
            return self::$arabicDigits[(int) $match[0]];
        }, (string) $value);
    }
}
