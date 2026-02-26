<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="h-full bg-background-light dark:bg-background-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.app_name') }}</title>

    <!-- DNS prefetch & preconnect for external resources -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Google Fonts: Inter, Cairo & Material Symbols (swap for fast first paint) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    <!-- Preload Alpine.js for faster interactive readiness -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js" as="script">

    <!-- Theme: default light, persist choice in localStorage -->
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

    <!-- Hide Alpine x-cloak elements before JS init to prevent modal flash -->
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gradient-to-br from-indigo-50/50 via-slate-50 to-indigo-50/50 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950/50 text-text-main-light dark:text-text-main-dark font-sans antialiased overflow-hidden h-screen flex relative">
    
    <!-- Decorative Background Shapes -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-20 -left-20 w-96 h-96 bg-primary/5 rounded-full blur-3xl animate-float"></div>
        <div class="absolute top-1/2 right-0 w-72 h-72 bg-indigo-400/5 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-0 left-1/3 w-80 h-80 bg-blue-400/5 rounded-full blur-3xl animate-float" style="animation-delay: 4s;"></div>
    </div>

    <div x-data="{ sidebarOpen: false }" class="flex w-full h-full z-10 relative">
        
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

    <!-- Notification bell animation & toast styles -->
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

        @keyframes toast-slide-up {
            0% { transform: translateY(100%); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        @keyframes toast-slide-down {
            0% { transform: translateY(0); opacity: 1; }
            100% { transform: translateY(100%); opacity: 0; }
        }
        .toast-enter { animation: toast-slide-up 0.4s cubic-bezier(0.22, 1, 0.36, 1) forwards; }
        .toast-exit { animation: toast-slide-down 0.3s cubic-bezier(0.55, 0, 1, 0.45) forwards; }
    </style>

    <!-- Toast notification container -->
    <div id="booking-toast-container" class="fixed bottom-6 left-6 rtl:left-auto rtl:right-6 z-[100] flex flex-col gap-3" style="pointer-events: none;"></div>

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

                startPolling() {
                    this.fetchPending();
                    this.pollInterval = setInterval(() => this.fetchPending(), 15000);
                },

                async fetchPending() {
                    try {
                        const res = await fetch('/api/bookings/pending');
                        const data = await res.json();
                        const newCount = data.count || 0;
                        const newBookings = data.bookings || [];

                        if (newCount > this.lastCount && this.lastCount >= 0 && this.count > 0) {
                            this.hasNew = true;
                            this.playMessengerSound();
                            setTimeout(() => { this.hasNew = false; }, 1500);

                            // Show toast for each new booking
                            const oldIds = new Set(this.bookings.map(b => b.id));
                            const brandNew = newBookings.filter(b => !oldIds.has(b.id));
                            if (brandNew.length > 0) {
                                brandNew.forEach(b => this.showBookingToast(b));
                            } else if (newCount > this.count) {
                                this.showBookingToast(newBookings[0]);
                            }
                        } else if (this.count === 0 && newCount > 0) {
                            // First load with pending bookings - just set silently
                        }

                        this.count = newCount;
                        this.bookings = newBookings;
                        this.lastCount = newCount;
                    } catch (e) {
                        console.warn('Notification poll failed:', e);
                    }
                },

                playMessengerSound() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const now = ctx.currentTime;

                        // Classic Messenger-style notification: D5 → F5 → A5 (bright rising arpeggio)
                        const notes = [
                            { freq: 587, start: 0, dur: 0.12 },
                            { freq: 698, start: 0.13, dur: 0.12 },
                            { freq: 880, start: 0.26, dur: 0.20 },
                        ];

                        notes.forEach(n => {
                            const osc = ctx.createOscillator();
                            const gain = ctx.createGain();
                            osc.type = 'sine';
                            osc.frequency.value = n.freq;
                            osc.connect(gain);
                            gain.connect(ctx.destination);
                            gain.gain.setValueAtTime(0, now + n.start);
                            gain.gain.linearRampToValueAtTime(0.18, now + n.start + 0.02);
                            gain.gain.exponentialRampToValueAtTime(0.001, now + n.start + n.dur);
                            osc.start(now + n.start);
                            osc.stop(now + n.start + n.dur + 0.01);
                        });
                    } catch (e) {}
                },

                showBookingToast(booking) {
                    if (!booking) return;
                    const container = document.getElementById('booking-toast-container');
                    if (!container) return;

                    const toast = document.createElement('div');
                    toast.style.pointerEvents = 'auto';
                    toast.className = 'toast-enter flex items-center gap-3 bg-white dark:bg-[#1e293b] border border-[#e2e8f0] dark:border-[#334155] rounded-xl shadow-2xl px-5 py-4 min-w-[320px] max-w-[400px] cursor-pointer';
                    toast.innerHTML = `
                        <div class="bg-primary/10 text-primary p-2.5 rounded-full flex-shrink-0">
                            <span class="material-symbols-outlined">calendar_add_on</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-primary mb-0.5">{{ __('messages.new_booking') }}</p>
                            <p class="text-sm font-bold text-[#111418] dark:text-white truncate">${booking.patient_name || 'Patient'}</p>
                            <p class="text-xs text-[#637388] dark:text-gray-400 truncate">${booking.doctor_name || ''}</p>
                        </div>
                        <button onclick="this.parentElement.classList.replace('toast-enter','toast-exit'); setTimeout(() => this.parentElement.remove(), 300);" class="text-[#9ca3af] hover:text-[#637388] p-1 flex-shrink-0">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    `;

                    toast.addEventListener('click', (e) => {
                        if (e.target.closest('button')) return;
                        window.location.href = '{{ route("bookings.index") }}';
                    });

                    container.appendChild(toast);

                    // Auto dismiss after 8 seconds
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.classList.replace('toast-enter', 'toast-exit');
                            setTimeout(() => toast.remove(), 300);
                        }
                    }, 8000);
                },

                goToBookings() {
                    this.open = false;
                    window.location.href = '{{ route("bookings.index") }}';
                }
            };
        }
    </script>

    <!-- Alpine.js for interactions (pinned version for cacheability) -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js" defer></script>
    @yield('scripts')
</body>
</html>
