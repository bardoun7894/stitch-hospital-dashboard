<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redirect;

class LocalizationController extends Controller
{
    /**
     * Change the application locale
     *
     * @param  Request  $request
     * @param  string   $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setLocale(Request $request, string $locale)
    {
        // Validate locale is supported
        $supportedLocales = ['en', 'ar'];

        if (!in_array($locale, $supportedLocales)) {
            return Redirect::back();
        }

        // Store locale in session
        Session::put('locale', $locale);

        // Set locale for current request
        App::setLocale($locale);

        // Redirect back to previous page
        return Redirect::back();
    }

    /**
     * Get current locale
     *
     * @return string
     */
    public function getCurrentLocale()
    {
        return Session::get('locale', config('app.locale'));
    }

    /**
     * Get all supported locales
     *
     * @return array
     */
    public function getSupportedLocales()
    {
        return [
            'en' => 'English',
            'ar' => 'العربية',
        ];
    }

    /**
     * Switch to English
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function switchToEnglish(Request $request)
    {
        return $this->setLocale($request, 'en');
    }

    /**
     * Switch to Arabic
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function switchToArabic(Request $request)
    {
        return $this->setLocale($request, 'ar');
    }
}
