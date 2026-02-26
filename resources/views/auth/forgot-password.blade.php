<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.forgot_password_title') }} - {{ __('messages.app_name') }}</title>

    <!-- Google Fonts: Inter, Cairo & Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    <!-- Theme: default light -->
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

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-indigo-50 via-slate-50 to-indigo-100 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950 min-h-screen font-sans antialiased flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 relative">

    <!-- Decorative Background Shapes -->
    <div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-20 -left-20 w-96 h-96 bg-primary/20 rounded-full blur-3xl animate-float"></div>
        <div class="absolute top-1/4 right-0 w-72 h-72 bg-indigo-400/10 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-0 left-1/3 w-80 h-80 bg-blue-400/10 rounded-full blur-3xl animate-float" style="animation-delay: 4s;"></div>
    </div>

    <!-- Forgot Password Card -->
    <div class="w-full max-w-md bg-white dark:bg-surface-dark rounded-3xl overflow-hidden relative z-10 animate-fade-in-up mx-auto border border-gray-200 dark:border-gray-700 shadow-xl">
        <!-- Decorative Top Bar -->
        <div class="h-2 w-full bg-gradient-to-r from-primary to-indigo-400"></div>

        <div class="p-8 sm:p-10">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto size-16 flex items-center justify-center rounded-2xl bg-gradient-to-br from-primary/10 to-indigo-500/10 text-primary mb-6 shadow-sm border border-primary/10">
                    <span class="material-symbols-outlined text-4xl">lock_reset</span>
                </div>
                <h2 class="text-3xl font-bold text-text-main-light dark:text-white tracking-tight mb-2">
                    {{ __('messages.forgot_password_title') }}
                </h2>
                <p class="text-text-sub-light dark:text-text-sub-dark">
                    {{ __('messages.forgot_password_subtitle') }}
                </p>
            </div>

            <!-- Forgot Password Form -->
            <form class="space-y-6" action="{{ route('password.email') }}" method="POST">
                @csrf

                @if (session('status'))
                    <div class="bg-emerald-50/50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-xl p-4 flex gap-3 items-start">
                        <span class="material-symbols-outlined text-emerald-500 text-sm mt-0.5">check_circle</span>
                        <p class="text-sm text-emerald-600 dark:text-emerald-400">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-50/50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 rounded-xl p-4 flex gap-3 items-start animate-pulse">
                        <span class="material-symbols-outlined text-rose-500 text-sm mt-0.5">error</span>
                        <div class="text-sm text-rose-600 dark:text-rose-400">
                             <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <div class="space-y-5">
                    <!-- Email -->
                    <div class="group">
                        <label for="email" class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5 ml-1">
                            {{ __('messages.email') }}
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-sub-light/50 transition-colors group-focus-within:text-primary pointer-events-none">mail</span>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                autocomplete="email"
                                required
                                value="{{ old('email') }}"
                                class="w-full pl-10 pr-4 py-3 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-text-main-light dark:text-white placeholder-text-sub-light/50 outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all"
                                placeholder="{{ __('messages.email_placeholder') }}"
                            >
                        </div>
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-lg shadow-primary/20 text-sm font-bold text-white bg-gradient-to-r from-primary to-indigo-600 hover:from-primary-hover hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all active:scale-[0.98] transform hover:-translate-y-0.5"
                >
                    {{ __('messages.send_reset_link') }}
                </button>
            </form>

            <div class="mt-8 flex flex-col items-center gap-6">
                <!-- Back to Login Link -->
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark text-center">
                    <a href="{{ route('login') }}" class="font-bold text-primary hover:text-indigo-500 transition-colors hover:underline decoration-2 underline-offset-4">{{ __('messages.back_to_login') }}</a>
                </p>

                <!-- Language Switcher -->
                <div class="flex justify-center gap-3 p-1.5 bg-gray-100/50 dark:bg-slate-800/50 rounded-full backdrop-blur-sm border border-gray-200/50 dark:border-slate-700/50">
                    <a href="{{ route('switch-to-arabic') }}" class="text-xs font-medium px-4 py-1.5 rounded-full transition-all duration-300 {{ app()->getLocale() === 'ar' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-text-sub-light hover:text-text-main-light dark:text-text-sub-dark dark:hover:text-white' }}">
                        العربية
                    </a>
                    <a href="{{ route('switch-to-english') }}" class="text-xs font-medium px-4 py-1.5 rounded-full transition-all duration-300 {{ app()->getLocale() === 'en' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-text-sub-light hover:text-text-main-light dark:text-text-sub-dark dark:hover:text-white' }}">
                        English
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
