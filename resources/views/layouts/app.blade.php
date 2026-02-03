<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.app_name') }}</title>

    <!-- Google Fonts: Inter, Cairo & Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background-light dark:bg-background-dark text-[#111418] dark:text-white font-display overflow-hidden h-screen flex">
    <div x-data="{ sidebarOpen: false }" class="flex w-full h-full">
        
        <!-- Sidebar -->
        @include('dashboard.partials.sidebar')

        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
            
            <!-- Header -->
            @include('dashboard.partials.header')

            <!-- Scrollable Content -->
            <div class="flex-1 overflow-auto p-4 md:p-8">
                @yield('content')
            </div>
            
        </main>
    </div>

    <!-- Alpine.js for interactions -->
    <script src="//unpkg.com/alpinejs" defer></script>
    @yield('scripts')
</body>
</html>
