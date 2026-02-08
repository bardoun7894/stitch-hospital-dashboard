<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.login') }} - {{ __('messages.app_name') }}</title>

    <!-- Google Fonts: Inter, Cairo & Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background-light dark:bg-background-dark h-full font-sans antialiased flex items-center justify-center p-4">
    
    <!-- Login Card -->
    <div class="max-w-md w-full bg-white dark:bg-surface-dark rounded-2xl shadow-xl border border-border-light dark:border-border-dark overflow-hidden relative">
        <!-- Decorative Top Bar -->
        <div class="h-2 w-full bg-gradient-to-r from-primary to-primary-hover"></div>

        <div class="p-8 sm:p-10">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto size-14 flex items-center justify-center rounded-xl bg-primary/10 text-primary text-3xl font-bold mb-4">
                    S
                </div>
                <h2 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight">
                    {{ __('messages.login_title', [], 'Welcome Back') }}
                </h2>
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark mt-2">
                    {{ __('messages.login_subtitle', [], 'Sign in to your account') }}
                </p>
            </div>

            <!-- Login Form -->
            <form class="space-y-6" action="{{ route('login.submit') }}" method="POST">
                @csrf
                
                @if ($errors->any())
                    <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 rounded-lg p-4 flex gap-3 items-start">
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

                @if (session('error'))
                    <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 rounded-lg p-4 flex gap-3 items-start">
                        <span class="material-symbols-outlined text-rose-500 text-sm mt-0.5">error</span>
                        <p class="text-sm text-rose-600 dark:text-rose-400">{{ session('error') }}</p>
                    </div>
                @endif

                <div class="space-y-5">
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">
                            {{ __('messages.email', [], 'Email Address') }}
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-sub-light/50 pointer-events-none">mail</span>
                            <input 
                                id="email" 
                                name="email" 
                                type="email" 
                                autocomplete="email" 
                                required 
                                value="{{ old('email') }}"
                                class="w-full pl-10 pr-4 py-2.5 bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all shadow-sm"
                                placeholder="{{ __('messages.email_placeholder', [], 'name@clinic.com') }}"
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-medium text-text-main-light dark:text-white">
                                {{ __('messages.password', [], 'Password') }}
                            </label>
                            <a href="#" class="text-sm font-medium text-primary hover:text-primary-hover transition-colors">
                                {{ __('messages.forgot_password', [], 'Forgot?') }}
                            </a>
                        </div>
                         <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-sub-light/50 pointer-events-none">lock</span>
                            <input 
                                id="password" 
                                name="password" 
                                type="password" 
                                autocomplete="current-password" 
                                required 
                                class="w-full pl-10 pr-4 py-2.5 bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg text-text-main-light dark:text-white placeholder-text-sub-light/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all shadow-sm"
                                placeholder="••••••••"
                            >
                        </div>
                    </div>
                </div>

                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                    <label for="remember-me" class="ml-2 block text-sm text-text-sub-light dark:text-text-sub-dark">
                        {{ __('messages.remember_me', [], 'Remember me') }}
                    </label>
                </div>

                <button 
                    type="submit" 
                    class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-primary hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all active:scale-[0.98]"
                >
                    {{ __('messages.login_button', [], 'Sign In') }}
                </button>
            </form>

            <!-- Language Switcher -->
            <div class="mt-8 pt-6 border-t border-border-light dark:border-border-dark flex justify-center gap-4">
                <a href="{{ route('switch-to-arabic') }}" class="text-xs font-medium px-3 py-1 rounded-full transition-colors {{ app()->getLocale() === 'ar' ? 'bg-primary/10 text-primary' : 'text-text-sub-light hover:text-text-main-light dark:text-text-sub-dark dark:hover:text-white' }}">
                    العربية
                </a>
                <a href="{{ route('switch-to-english') }}" class="text-xs font-medium px-3 py-1 rounded-full transition-colors {{ app()->getLocale() === 'en' ? 'bg-primary/10 text-primary' : 'text-text-sub-light hover:text-text-main-light dark:text-text-sub-dark dark:hover:text-white' }}">
                    English
                </a>
            </div>
        </div>
    </div>
</body>
</html>
