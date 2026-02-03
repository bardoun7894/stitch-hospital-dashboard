# Laravel Multilingual Localization Guide

## Project Structure

Your clinic app is now set up with complete multilingual support for **English (en)** and **Arabic (ar)**.

### Translation Files Structure

```
/lang/
├── en/
│   ├── messages.php       (Main application messages)
│   ├── auth.php           (Authentication related strings)
│   ├── pagination.php     (Pagination navigation)
│   └── validation.php     (Form validation error messages)
└── ar/
    ├── messages.php       (Main application messages - Arabic)
    ├── auth.php           (Authentication related strings - Arabic)
    ├── pagination.php     (Pagination navigation - Arabic)
    └── validation.php     (Form validation error messages - Arabic)
```

## Configuration

### 1. Default Locale Configuration (config/app.php)

```php
// Current setting - Arabic is the default
'locale' => env('APP_LOCALE', 'ar'),

// Fallback locale when key is not found
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
```

To change via `.env`:
```env
APP_LOCALE=ar    # Default locale (ar or en)
APP_FALLBACK_LOCALE=en  # Fallback locale
```

## Usage

### 1. Basic Translation with `__()`

#### In Views (Blade Templates)

```blade
<!-- Display a simple translation -->
<h1>{{ __('messages.dashboard') }}</h1>
<!-- Output: "لوحة التحكم" (if locale is 'ar') -->

<!-- Display with dot notation -->
<button>{{ __('messages.accept') }}</button>
<!-- Output: "قبول" (if locale is 'ar') or "Accept" (if locale is 'en') -->

<!-- Translation with parameters -->
<p>{{ __('auth.throttle', ['seconds' => 60]) }}</p>

<!-- Conditional translations -->
@if(App::isLocale('ar'))
    <p>{{ __('messages.welcome_ar') }}</p>
@else
    <p>{{ __('messages.welcome_en') }}</p>
@endif
```

#### In Controllers

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;

class DashboardController extends Controller
{
    public function index()
    {
        // Get translation string
        $title = __('messages.dashboard');

        // Check current locale
        $isArabic = App::isLocale('ar');

        return view('dashboard', [
            'title' => $title,
            'isArabic' => $isArabic,
        ]);
    }
}
```

#### In Controllers with Validation

```php
use Illuminate\Support\Facades\Validator;

public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|min:8',
    ]);

    // Laravel automatically uses current locale for validation messages
    if ($validator->fails()) {
        return back()->withErrors($validator);
    }
}
```

### 2. Middleware for Automatic Locale Detection

The `SetLocale` middleware automatically handles locale detection:

```php
// app/Http/Middleware/SetLocale.php
public function handle(Request $request, Closure $next): Response
{
    if (Session::has('locale')) {
        App::setLocale(Session::get('locale'));
    } else {
        // Default to Arabic
        App::setLocale('ar');
    }

    return $next($request);
}
```

**Apply middleware** in `app/Http/Kernel.php`:

```php
protected $middleware = [
    // ... other middleware
    \App\Http\Middleware\SetLocale::class,
];
```

### 3. Language Switcher Routes

#### Available Routes

```
GET /lang/{locale}         - Generic locale setter
GET /lang/en               - Switch to English
GET /lang/ar               - Switch to Arabic
```

#### Using in Views

```blade
<!-- Language Switcher Links -->
<div class="language-switcher">
    <a href="{{ route('set-locale', 'en') }}" class="btn">English</a>
    <a href="{{ route('set-locale', 'ar') }}" class="btn">العربية</a>
</div>

<!-- Conditional styling based on locale -->
<div dir="{{ App::isLocale('ar') ? 'rtl' : 'ltr' }}">
    <!-- Content will be RTL for Arabic, LTR for English -->
</div>

<!-- Current Locale Display -->
<span>Current Language: {{ App::getLocale() }}</span>
```

### 4. Using the LocalizationHelper

The `LocalizationHelper` class provides convenient helper functions:

```php
<?php

use App\Helpers\LocalizationHelper;

// Get current locale
$locale = LocalizationHelper::getCurrentLocale(); // Returns 'ar' or 'en'

// Set locale
LocalizationHelper::setLocale('en');

// Check locale
if (LocalizationHelper::isArabic()) {
    // Do something for Arabic
}

if (LocalizationHelper::isEnglish()) {
    // Do something for English
}

// Get text direction (for CSS)
$direction = LocalizationHelper::getDirection(); // Returns 'rtl' or 'ltr'

// Get text alignment
$textAlign = LocalizationHelper::getTextAlign(); // Returns 'right' or 'left'

// Get supported locales
$locales = LocalizationHelper::getSupportedLocales();
// Returns: ['en' => 'English', 'ar' => 'العربية']

// Get locale name
$name = LocalizationHelper::getLocaleName('ar'); // Returns 'العربية'

// Temporary locale switching for a callback
$result = LocalizationHelper::withLocale('en', function() {
    return __('messages.dashboard'); // Returns "Dashboard"
});
```

#### In Blade Views

Create a helper function alias in `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Helpers\LocalizationHelper;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Make helper accessible in views
        view()->share('localizationHelper', new LocalizationHelper());
    }
}
```

Then use in Blade:

```blade
<html dir="{{ $localizationHelper::getDirection() }}">
    <head>
        <title>{{ $localizationHelper::getLocaleName() }}</title>
    </head>
    <body>
        @if($localizationHelper::isArabic())
            <p>هذا نص عربي</p>
        @endif
    </body>
</html>
```

### 5. Using LocalizationController

The `LocalizationController` handles all language switching operations:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\LocalizationController;
use Illuminate\Http\Request;

// Direct controller usage (if needed)
$controller = new LocalizationController();

// Get current locale
$locale = $controller->getCurrentLocale(); // Returns 'ar' or 'en'

// Get supported locales
$locales = $controller->getSupportedLocales();
// Returns: ['en' => 'English', 'ar' => 'العربية']
```

## Translation File Examples

### Translation Namespace Usage

#### messages.php Structure

```php
<?php

return [
    // Navigation
    'dashboard' => 'لوحة التحكم',
    'bookings' => 'الحجوزات',
    'queue' => 'الطابور',

    // Messages with parameters
    'welcome' => 'مرحباً بك يا :name',

    // Nested translations (using dot notation)
    'booking.accept' => 'قبول الحجز',
    'booking.reject' => 'رفض الحجز',
];
```

#### Usage

```blade
<!-- Simple key -->
{{ __('messages.dashboard') }}

<!-- Key with parameter -->
{{ __('messages.welcome', ['name' => $user->name]) }}

<!-- Nested keys -->
{{ __('messages.booking.accept') }}
```

## Advanced Usage

### 1. Dynamic Translations Based on Locale

```php
public function showBooking($id)
{
    $booking = Booking::find($id);

    // Get direction and alignment for current locale
    $direction = LocalizationHelper::getDirection();
    $textAlign = LocalizationHelper::getTextAlign();

    return view('booking.show', [
        'booking' => $booking,
        'direction' => $direction,
        'textAlign' => $textAlign,
    ]);
}
```

### 2. Translating Application Names/Labels

```blade
<!-- In a dashboard view -->
<h1>{{ config('app.name') }}</h1>

<!-- Translating dynamic content -->
@foreach($statuses as $status)
    <span>{{ __("messages.{$status}") }}</span>
@endforeach
```

### 3. Pluralization (if using English)

```blade
<!-- English supports pluralization -->
{{ trans_choice('messages.minutes', $count, ['count' => $count]) }}

<!-- Output: "5 Minutes" if $count = 5 -->
```

### 4. Language-Specific Validations

```php
public function rules()
{
    return [
        'phone' => [
            'required',
            function ($attribute, $value, $fail) {
                // Different validation for Arabic/English locales
                if (LocalizationHelper::isArabic()) {
                    // Validate for Saudi Arabia format
                    if (!preg_match('/^\+966\d{9}$/', $value)) {
                        $fail(__('validation.custom.phone.saudi'));
                    }
                } else {
                    // Validate for international format
                    if (!preg_match('/^\+\d{1,3}\d{9,15}$/', $value)) {
                        $fail(__('validation.custom.phone.international'));
                    }
                }
            },
        ],
    ];
}
```

## Adding New Translations

### 1. Add to English File

```php
// lang/en/messages.php
return [
    'new_feature' => 'New Feature',
];
```

### 2. Add to Arabic File

```php
// lang/ar/messages.php
return [
    'new_feature' => 'ميزة جديدة',
];
```

### 3. Use in Code

```blade
{{ __('messages.new_feature') }}
```

## Supported Locales

Currently configured locales:
- **en** - English
- **ar** - العربية (Arabic)

To add more locales:

1. Create new language directories under `/lang/`
2. Add translation files matching existing structure
3. Update `LocalizationController::setLocale()` to allow the new locale
4. Update `LocalizationHelper::getSupportedLocales()` with the new locale

## Common Translation Keys by Category

### Authentication (auth.php)
- `auth.login` - Login button
- `auth.logout` - Logout button
- `auth.failed` - Failed login message
- `auth.register` - Register button
- `auth.forgot_password` - Forgot password link

### Validation (validation.php)
- `validation.required` - Field is required
- `validation.email` - Invalid email format
- `validation.min` - Minimum length validation
- `validation.unique` - Value already exists

### Messages (messages.php)
- `messages.success` - Success message
- `messages.error` - Error message
- `messages.warning` - Warning message
- `messages.dashboard` - Dashboard label

## Troubleshooting

### Translations Not Showing

1. **Clear cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Verify locale setting:**
   ```php
   dd(App::getLocale()); // Check current locale
   ```

3. **Check file path:**
   ```bash
   ls -la lang/en/messages.php
   ls -la lang/ar/messages.php
   ```

### Session Locale Not Persisting

1. Ensure middleware is registered
2. Check session driver in `.env` (should be `SESSION_DRIVER=file` or `database`)
3. Verify session cookie configuration in `config/session.php`

### Missing Translation Keys

Laravel falls back to the key name itself. To see missing keys:

```php
// In AppServiceProvider boot method
if (config('app.debug')) {
    \Illuminate\Support\Facades\Lang::addJsonPath(resource_path('lang'));
}
```

## Best Practices

1. **Use consistent key naming**: Use snake_case for keys
2. **Organize by module**: Group related translations together
3. **Use dot notation**: `__('messages.booking.accept')` instead of nested arrays
4. **Always provide fallback**: Set up fallback locale properly
5. **Test both locales**: Always test content in both English and Arabic
6. **RTL consideration**: Remember Arabic is RTL - test layout with Arabic text
7. **Use helpers**: Use `LocalizationHelper` for consistent locale checking
8. **Middleware first**: Apply SetLocale middleware early in the request cycle

## Quick Reference

| Action | Code |
|--------|------|
| Get translation | `__('messages.key')` |
| Check locale | `App::isLocale('ar')` |
| Get current locale | `App::getLocale()` |
| Set locale | `LocalizationHelper::setLocale('en')` |
| Get direction | `LocalizationHelper::getDirection()` |
| Switch to English | `route('set-locale', 'en')` |
| Switch to Arabic | `route('set-locale', 'ar')` |
