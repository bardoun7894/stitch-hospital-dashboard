<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.registration_success_title') }} - {{ __('messages.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background-light dark:bg-background-dark h-full font-sans antialiased flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white dark:bg-surface-dark rounded-2xl shadow-xl border border-border-light dark:border-border-dark overflow-hidden relative">
        <div class="h-2 w-full bg-gradient-to-r from-success-green to-emerald-400"></div>

        <div class="p-8 sm:p-10 text-center">
            <div class="mx-auto size-16 flex items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-success-green mb-6">
                <span class="material-symbols-outlined text-4xl">check_circle</span>
            </div>

            <h2 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight mb-3">
                {{ __('messages.registration_success_title') }}
            </h2>

            <p class="text-sm text-text-sub-light dark:text-text-sub-dark leading-relaxed mb-6">
                {{ __('messages.registration_success_message') }}
            </p>

            @if(session('credentials'))
            <div class="bg-background-light dark:bg-background-dark rounded-xl border border-border-light dark:border-border-dark p-4 mb-6 text-left rtl:text-right">
                <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-3">{{ __('messages.login') }}</p>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-text-sub-light">{{ __('messages.email') }}:</span>
                        <span class="text-sm font-mono font-bold text-text-main-light dark:text-white">{{ session('credentials.email') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-text-sub-light">{{ __('messages.password') }}:</span>
                        <span class="text-sm font-mono font-bold text-text-main-light dark:text-white">{{ session('credentials.password') }}</span>
                    </div>
                </div>
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-3 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">warning</span>
                    Save these credentials. You can login once the hospital is approved.
                </p>
            </div>
            @endif

            <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-primary hover:bg-primary-hover transition-all active:scale-[0.98]">
                <span class="material-symbols-outlined text-lg">login</span>
                {{ __('messages.back_to_login') }}
            </a>
        </div>
    </div>
</body>
</html>
