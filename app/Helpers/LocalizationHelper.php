<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;

class LocalizationHelper
{
    /**
     * Get the current locale
     *
     * @return string
     */
    public static function getCurrentLocale()
    {
        return Session::get('locale', config('app.locale'));
    }

    /**
     * Set the locale for the application
     *
     * @param  string  $locale
     * @return void
     */
    public static function setLocale($locale)
    {
        $supported = ['en', 'ar'];

        if (in_array($locale, $supported)) {
            Session::put('locale', $locale);
            App::setLocale($locale);
        }
    }

    /**
     * Check if current locale is Arabic
     *
     * @return bool
     */
    public static function isArabic()
    {
        return static::getCurrentLocale() === 'ar';
    }

    /**
     * Check if current locale is English
     *
     * @return bool
     */
    public static function isEnglish()
    {
        return static::getCurrentLocale() === 'en';
    }

    /**
     * Get direction for the current locale (RTL for Arabic, LTR for English)
     *
     * @return string
     */
    public static function getDirection()
    {
        return static::isArabic() ? 'rtl' : 'ltr';
    }

    /**
     * Get text alignment for the current locale
     *
     * @return string
     */
    public static function getTextAlign()
    {
        return static::isArabic() ? 'right' : 'left';
    }

    /**
     * Get margin direction for the current locale
     *
     * @param  string  $side
     * @return string
     */
    public static function getMarginDirection($side = 'left')
    {
        if (static::isArabic()) {
            return $side === 'left' ? 'mr' : 'ml';
        }
        return $side === 'left' ? 'ml' : 'mr';
    }

    /**
     * Get all supported locales
     *
     * @return array
     */
    public static function getSupportedLocales()
    {
        return [
            'en' => 'English',
            'ar' => 'العربية',
        ];
    }

    /**
     * Get locale name
     *
     * @param  string|null  $locale
     * @return string
     */
    public static function getLocaleName($locale = null)
    {
        $locale = $locale ?? static::getCurrentLocale();
        $locales = static::getSupportedLocales();

        return $locales[$locale] ?? $locale;
    }

    /**
     * Switch locale temporarily for a callback
     *
     * @param  string  $locale
     * @param  callable  $callback
     * @return mixed
     */
    public static function withLocale($locale, callable $callback)
    {
        $previousLocale = static::getCurrentLocale();

        static::setLocale($locale);
        $result = call_user_func($callback);
        static::setLocale($previousLocale);

        return $result;
    }
}
