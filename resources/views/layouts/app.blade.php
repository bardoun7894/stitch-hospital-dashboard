<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="h-full bg-background-light dark:bg-background-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.app_name') }}</title>

    <!-- Google Fonts: Inter, Cairo & Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background-light dark:bg-background-dark text-text-main-light dark:text-text-main-dark font-sans antialiased overflow-hidden h-screen flex">

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

    <!-- Notification bell animation -->
    <style>
        @keyframes ring {
            0% { transform: rotate(0); }
            10% { transform: rotate(15deg); }
            20% { transform: rotate(-13deg); }
            30% { transform: rotate(10deg); }
            40% { transform: rotate(-8deg); }
            50% { transform: rotate(5deg); }
            60% { transform: rotate(0); }
            100% { transform: rotate(0); }
        }
        .animate-ring { animation: ring 1s ease-in-out; }
    </style>

    <!-- Notification polling -->
    <script>
        function notificationBell() {
            return {
                open: false,
                count: 0,
                bookings: [],
                hasNew: false,
                lastCount: 0,
                pollInterval: null,
                notificationSound: null,

                startPolling() {
                    this.fetchPending();
                    this.pollInterval = setInterval(() => this.fetchPending(), 15000);
                },

                async fetchPending() {
                    try {
                        const res = await fetch('/api/bookings/pending');
                        const data = await res.json();
                        const newCount = data.count || 0;

                        if (newCount > this.lastCount && this.lastCount > 0) {
                            this.hasNew = true;
                            this.playSound();
                            setTimeout(() => { this.hasNew = false; }, 1500);
                        }

                        this.count = newCount;
                        this.bookings = data.bookings || [];
                        this.lastCount = newCount;
                    } catch (e) {
                        console.warn('Notification poll failed:', e);
                    }
                },

                playSound() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.frequency.value = 800;
                        gain.gain.value = 0.15;
                        osc.start();
                        osc.stop(ctx.currentTime + 0.15);
                        setTimeout(() => {
                            const osc2 = ctx.createOscillator();
                            const gain2 = ctx.createGain();
                            osc2.connect(gain2);
                            gain2.connect(ctx.destination);
                            osc2.frequency.value = 1000;
                            gain2.gain.value = 0.15;
                            osc2.start();
                            osc2.stop(ctx.currentTime + 0.2);
                        }, 180);
                    } catch (e) {}
                },

                goToBookings() {
                    this.open = false;
                    window.location.href = '{{ route("bookings.index") }}';
                }
            };
        }
    </script>

    <!-- Alpine.js for interactions -->
    <script src="//unpkg.com/alpinejs" defer></script>
    @yield('scripts')
</body>
</html>
