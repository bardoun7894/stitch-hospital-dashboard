<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>{{ __('messages.tv_display_title') }}</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1c74e9",
                        "background-light": "#F5F7FB",
                        "background-dark": "#111821",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.375rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                    keyframes: {
                        pulseSoft: {
                            '0%, 100%': { boxShadow: '0 0 0 0 rgba(28, 116, 233, 0.4)' },
                            '50%': { boxShadow: '0 0 0 15px rgba(28, 116, 233, 0)' },
                        },
                        marquee: {
                            '0%': { transform: 'translateX(100%)' },
                            '100%': { transform: 'translateX(-100%)' }
                        }
                    },
                    animation: {
                        'pulse-soft': 'pulseSoft 3s infinite',
                        'marquee': 'marquee 25s linear infinite',
                    }
                },
            },
        }
    </script>
    <style>
        /* Hide scrollbars for TV view */
        body {
            overflow: hidden;
        }
    </style>
    <script>
        // Auto-refresh every 15 seconds to fetch latest queue state
        setInterval(() => {
            window.location.reload();
        }, 15000);
        
        // Optional: Play sound if recalled recently (requires layout to pass data to JS)
        // For MVP, simple reload is enough.
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark font-display h-screen w-screen flex flex-col text-[#111418] dark:text-white">
    <!-- Top Header -->
    <header class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-[#1a222d] px-8 py-5 shadow-sm shrink-0 z-10">
        <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <span class="material-symbols-outlined text-4xl">local_hospital</span>
            </div>
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-primary">{{ __('messages.city_central_clinic') }}</h1>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __('messages.main_waiting_area') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-6 bg-gray-50 dark:bg-gray-800 px-6 py-3 rounded-xl border border-gray-200 dark:border-gray-700">
            <span class="material-symbols-outlined text-gray-500 dark:text-gray-400 text-3xl">schedule</span>
            <div class="text-right">
                <div class="text-3xl font-bold leading-none text-gray-900 dark:text-white tabular-nums">{{ date('h:i A') }}</div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ date('l, M d') }}</div>
            </div>
        </div>
    </header>

    <!-- Main Layout -->
    <main class="flex-1 grid grid-cols-12 gap-8 p-8 overflow-hidden h-full">
        <!-- Hero Section: Now Serving -->
        <div class="col-span-8 flex flex-col h-full">
            <div class="bg-white dark:bg-[#1a222d] rounded-2xl shadow-lg border border-gray-200 dark:border-gray-800 flex flex-col items-center justify-center flex-1 relative overflow-hidden animate-pulse-soft">
                <!-- Background Pattern -->
                <div class="absolute inset-0 opacity-5 pointer-events-none" style="background-image: radial-gradient(#1c74e9 2px, transparent 2px); background-size: 32px 32px;"></div>
                
                <h2 class="text-gray-500 dark:text-gray-400 text-4xl font-bold tracking-widest uppercase mb-4 z-10">{{ __('messages.now_serving') }}</h2>
                
                <div class="relative z-10 flex flex-col items-center">
                    <span class="text-[180px] leading-none font-black text-primary tracking-tighter drop-shadow-sm">{{ $data['current_serving']['token'] }}</span>
                    <div class="mt-8 flex items-center gap-3 bg-primary text-white px-8 py-4 rounded-full shadow-md">
                        <span class="material-symbols-outlined text-4xl">meeting_room</span>
                        <span class="text-4xl font-bold">{{ $data['current_serving']['room'] }}</span>
                    </div>
                </div>
                
                <div class="absolute bottom-0 w-full h-2 bg-primary"></div>
            </div>
        </div>

        <!-- Sidebar: Coming Up -->
        <div class="col-span-4 flex flex-col h-full gap-6">
            <div class="bg-white dark:bg-[#1a222d] rounded-2xl shadow-md border border-gray-200 dark:border-gray-800 flex flex-col h-full overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">format_list_numbered</span>
                        {{ __('messages.next_up') }}
                    </h2>
                </div>

                <div class="flex-1 flex flex-col justify-center p-6 gap-5">
                    @foreach($data['next_up'] as $next)
                    <div class="flex items-center justify-between p-6 rounded-xl bg-gray-50 dark:bg-gray-800 border-l-8 border-primary/{{ 60 - ($loop->index * 20) }} shadow-sm">
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-gray-500 uppercase tracking-wider">{{ __('messages.patient_label') }}</span>
                            <span class="text-5xl font-black text-gray-800 dark:text-white">{{ $next['token'] }}</span>
                        </div>
                        <span class="material-symbols-outlined text-gray-300 text-5xl">person_check</span>
                    </div>
                    @endforeach
                </div>

                <div class="bg-gray-100 dark:bg-gray-900 p-4 text-center">
                    <p class="text-gray-500 dark:text-gray-400 font-medium">{{ __('messages.wait_instruction') }}</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer Ticker -->
    <footer class="bg-primary text-white py-4 shrink-0 overflow-hidden relative shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] z-20">
        <div class="flex whitespace-nowrap">
            <div class="flex animate-marquee items-center">
                 <span class="mx-8 font-bold text-2xl flex items-center gap-2">
                    <span class="material-symbols-outlined">info</span>
                    {{ __('messages.ticker_welcome') }}
                </span>
                <span class="mx-8 font-bold text-2xl flex items-center gap-2">
                    <span class="material-symbols-outlined">masks</span>
                    {{ __('messages.ticker_masks') }}
                </span>
                <span class="mx-8 font-bold text-2xl flex items-center gap-2">
                    <span class="material-symbols-outlined">credit_card</span>
                    {{ __('messages.ticker_insurance') }}
                </span>
                <span class="mx-8 font-bold text-2xl flex items-center gap-2">
                    <span class="material-symbols-outlined">schedule</span>
                    {{ __('messages.ticker_doctor_late') }}
                </span>
            </div>
             <!-- Duplicate for seamless loop effect -->
            <div aria-hidden="true" class="flex animate-marquee items-center">
                 <span class="mx-8 font-bold text-2xl flex items-center gap-2">
                    <span class="material-symbols-outlined">info</span>
                    {{ __('messages.ticker_welcome') }}
                </span>
                <span class="mx-8 font-bold text-2xl flex items-center gap-2">
                    <span class="material-symbols-outlined">masks</span>
                    {{ __('messages.ticker_masks') }}
                </span>
                 <span class="mx-8 font-bold text-2xl flex items-center gap-2">
                    <span class="material-symbols-outlined">credit_card</span>
                    {{ __('messages.ticker_insurance') }}
                </span>
                <span class="mx-8 font-bold text-2xl flex items-center gap-2">
                    <span class="material-symbols-outlined">schedule</span>
                    {{ __('messages.ticker_doctor_late') }}
                </span>
            </div>
        </div>
    </footer>
</body>
</html>
