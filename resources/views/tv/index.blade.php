<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>{{ __('messages.tv_clinic_display') }} - {{ $clinicSettings['name'] }}</title>

    <!-- DNS prefetch & preconnect for external resources -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "navy": "#0f172a",
                        "navy-light": "#1e293b",
                        "navy-card": "#1a2332",
                        "accent-green": "#22c55e",
                        "accent-amber": "#f59e0b",
                        "accent-blue": "#3b82f6",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    keyframes: {
                        pulseGlow: {
                            '0%, 100%': { boxShadow: '0 0 20px 5px rgba(34, 197, 94, 0.3)', borderColor: 'rgba(34, 197, 94, 0.6)' },
                            '50%': { boxShadow: '0 0 40px 15px rgba(34, 197, 94, 0.15)', borderColor: 'rgba(34, 197, 94, 0.3)' },
                        },
                        pulseIndicator: {
                            '0%, 100%': { opacity: '1', transform: 'scale(1)' },
                            '50%': { opacity: '0.5', transform: 'scale(1.2)' },
                        },
                        scrollMarquee: {
                            '0%': { transform: 'translateX(0)' },
                            '100%': { transform: 'translateX(-50%)' }
                        },
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    },
                    animation: {
                        'pulse-glow': 'pulseGlow 3s ease-in-out infinite',
                        'pulse-indicator': 'pulseIndicator 2s ease-in-out infinite',
                        'scroll-marquee': 'scrollMarquee var(--marquee-duration, 30s) linear infinite',
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                    }
                },
            },
        }
    </script>

    <style>
        body {
            overflow: hidden;
            cursor: none;
        }
        .token-number {
            font-size: clamp(100px, 15vw, 180px);
            line-height: 1;
            font-weight: 900;
            letter-spacing: -0.04em;
        }
        .waiting-list-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .waiting-list-scroll::-webkit-scrollbar {
            display: none;
        }
        .marquee-track {
            display: flex;
            width: max-content;
        }
    </style>
</head>

<body class="bg-navy font-display h-screen w-screen flex flex-col text-white select-none">

    {{-- ============================================================ --}}
    {{-- HEADER: Logo | Clinic Name | Live Clock                      --}}
    {{-- ============================================================ --}}
    <header class="flex items-center justify-between bg-navy-light/80 backdrop-blur-sm px-8 py-4 border-b border-white/5 shrink-0 z-20">
        <div class="flex items-center gap-5">
            @if($clinicSettings['logo_url'])
                <img src="{{ $clinicSettings['logo_url'] }}" alt="Clinic Logo" class="h-14 w-14 rounded-xl object-cover bg-white/10 p-1">
            @else
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-accent-green/10 border border-accent-green/20">
                    <span class="material-symbols-outlined text-accent-green text-3xl">local_hospital</span>
                </div>
            @endif
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">{{ $clinicSettings['name'] }}</h1>
                <p class="text-sm font-medium text-slate-400 uppercase tracking-widest">{{ __('messages.tv_clinic_display') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-4 bg-white/5 px-6 py-3 rounded-xl border border-white/10">
            <span class="material-symbols-outlined text-slate-400 text-2xl">schedule</span>
            <div class="text-right">
                <div id="live-clock" class="text-2xl font-bold leading-none text-white tabular-nums">--:--</div>
                <div id="live-date" class="text-sm font-medium text-slate-400">---</div>
            </div>
        </div>
    </header>

    {{-- ============================================================ --}}
    {{-- MAIN CONTENT: Now Serving (left) | Waiting List (right)      --}}
    {{-- ============================================================ --}}
    <main class="flex-1 grid grid-cols-12 gap-6 p-6 overflow-hidden min-h-0">

        {{-- LEFT: NOW SERVING --}}
        <section class="col-span-7 flex flex-col h-full">
            <div class="bg-navy-card rounded-2xl border-2 border-accent-green/20 flex flex-col items-center justify-center flex-1 relative overflow-hidden animate-pulse-glow">
                {{-- Background subtle pattern --}}
                <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(circle, #22c55e 1px, transparent 1px); background-size: 40px 40px;"></div>

                {{-- NOW SERVING label --}}
                <div class="z-10 flex items-center gap-3 mb-4">
                    <span class="inline-block w-3 h-3 rounded-full bg-accent-green animate-pulse-indicator"></span>
                    <h2 class="text-slate-300 text-3xl font-bold tracking-widest uppercase">{{ __('messages.now_serving') }}</h2>
                    <span class="inline-block w-3 h-3 rounded-full bg-accent-green animate-pulse-indicator"></span>
                </div>

                {{-- Token Number --}}
                <div class="relative z-10 flex flex-col items-center">
                    <span class="token-number text-white drop-shadow-[0_0_30px_rgba(34,197,94,0.3)]">
                        {{ $data['current_serving']['token'] }}
                    </span>

                    {{-- Patient Name --}}
                    <p class="mt-4 text-3xl font-semibold text-slate-200">
                        {{ $data['current_serving']['patient'] }}
                    </p>

                    {{-- Doctor / Type --}}
                    @if(!empty($data['current_serving']['type']) && $data['current_serving']['type'] !== '-')
                    <p class="mt-2 text-xl text-slate-400 flex items-center gap-2">
                        <span class="material-symbols-outlined text-accent-blue text-xl">stethoscope</span>
                        {{ $data['current_serving']['type'] }}
                    </p>
                    @endif

                    {{-- Room Badge --}}
                    <div class="mt-6 flex items-center gap-3 bg-accent-green/15 border border-accent-green/30 text-accent-green px-6 py-3 rounded-full">
                        <span class="material-symbols-outlined text-2xl">meeting_room</span>
                        <span class="text-2xl font-bold">{{ __('messages.room_label') }} {{ $data['current_serving']['room'] }}</span>
                    </div>
                </div>

                {{-- Bottom accent bar --}}
                <div class="absolute bottom-0 w-full h-1.5 bg-gradient-to-r from-transparent via-accent-green to-transparent"></div>
            </div>
        </section>

        {{-- RIGHT: WAITING LIST + SKIPPED --}}
        <section class="col-span-5 flex flex-col h-full gap-4 min-h-0">

            {{-- Waiting List Panel --}}
            <div class="bg-navy-card rounded-2xl border border-white/5 flex flex-col flex-1 overflow-hidden min-h-0">
                <div class="px-6 py-4 border-b border-white/5 bg-white/[0.02] shrink-0">
                    <h2 class="text-xl font-bold text-white flex items-center gap-3">
                        <span class="material-symbols-outlined text-accent-blue">format_list_numbered</span>
                        {{ __('messages.waiting_list') }}
                        @if(count($data['next_up']) > 0)
                            <span class="ml-auto text-sm font-medium text-slate-400 bg-white/5 px-3 py-1 rounded-full">{{ count($data['bookings']) > 0 ? count(array_filter($data['bookings'], fn($b) => ($b['estimated_wait'] ?? 0) > 0)) : count($data['next_up']) }} {{ __('messages.please_wait') }}</span>
                        @endif
                    </h2>
                </div>

                <div class="flex-1 overflow-y-auto waiting-list-scroll p-4 space-y-3 min-h-0">
                    @php
                        // Collect all waiting patients from bookings (those with estimated_wait)
                        $waitingPatients = array_filter($data['bookings'], fn($b) => isset($b['estimated_wait']) && $b['estimated_wait'] > 0);
                        // If no bookings with wait times, fallback to next_up
                        if (empty($waitingPatients)) {
                            $waitingPatients = $data['next_up'];
                        }
                    @endphp

                    @forelse($waitingPatients as $index => $waiting)
                        @php
                            $patientName = $waiting['patient'] ?? 'Patient';
                            // Create abbreviated name: first name + last initial
                            $nameParts = explode(' ', trim($patientName));
                            $displayName = $nameParts[0];
                            if (count($nameParts) > 1) {
                                $displayName .= ' ' . mb_substr(end($nameParts), 0, 1) . '.';
                            }
                            $waitDisplay = $waiting['estimated_wait_display'] ?? $waiting['wait'] ?? '';
                        @endphp
                        <div class="flex items-center gap-4 px-5 py-4 rounded-xl bg-white/[0.03] border border-white/5 hover:bg-white/[0.05] transition-colors"
                             style="animation-delay: {{ $index * 80 }}ms">
                            {{-- Token --}}
                            <div class="flex-shrink-0 w-16 h-16 flex items-center justify-center rounded-xl bg-accent-blue/10 border border-accent-blue/20">
                                <span class="text-2xl font-black text-accent-blue">{{ $waiting['token'] ?? str_pad($waiting['token_num'] ?? 0, 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                            {{-- Name --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-lg font-semibold text-white truncate">{{ $displayName }}</p>
                                @if(!empty($waiting['type']) && $waiting['type'] !== '-')
                                    <p class="text-sm text-slate-400 truncate">{{ $waiting['type'] }}</p>
                                @endif
                            </div>
                            {{-- Estimated Wait --}}
                            @if($waitDisplay)
                            <div class="flex-shrink-0 text-right">
                                <span class="text-sm font-bold text-accent-amber bg-accent-amber/10 border border-accent-amber/20 px-3 py-1.5 rounded-lg">
                                    {{ $waitDisplay }}
                                </span>
                            </div>
                            @endif
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center h-full text-center py-12">
                            <span class="material-symbols-outlined text-slate-600 text-6xl mb-4">hourglass_empty</span>
                            <p class="text-xl text-slate-500 font-medium">{{ __('messages.no_patients_waiting') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Skipped Patients (Amber Section) --}}
            @if(!empty($data['skipped']) && count($data['skipped']) > 0)
            <div class="bg-accent-amber/5 rounded-2xl border border-accent-amber/20 shrink-0 overflow-hidden">
                <div class="px-6 py-3 border-b border-accent-amber/10 bg-accent-amber/5">
                    <h3 class="text-base font-bold text-accent-amber flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg">person_off</span>
                        {{ __('messages.skipped_patients') }}
                    </h3>
                </div>
                <div class="px-6 py-3 flex flex-wrap gap-3">
                    @foreach(array_slice($data['skipped'], 0, 6) as $skipped)
                        <span class="inline-flex items-center gap-2 bg-accent-amber/10 border border-accent-amber/20 text-accent-amber px-3 py-1.5 rounded-lg text-sm font-bold">
                            {{ $skipped['token'] }}
                            <span class="text-accent-amber/60 font-normal">{{ explode(' ', $skipped['patient'])[0] }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
            @endif
        </section>
    </main>

    {{-- ============================================================ --}}
    {{-- SCROLLING ANNOUNCEMENTS BAR                                  --}}
    {{-- ============================================================ --}}
    <footer class="bg-accent-blue text-white py-3 shrink-0 overflow-hidden relative z-20">
        <div class="marquee-track animate-scroll-marquee" id="marquee-track">
            @php
                $icons = ['campaign', 'badge', 'health_and_safety', 'info', 'notification_important'];
            @endphp
            {{-- We duplicate the content for seamless scrolling --}}
            @for($copy = 0; $copy < 2; $copy++)
                @foreach($announcements as $i => $announcement)
                    <span class="mx-10 font-semibold text-xl flex items-center gap-3 whitespace-nowrap">
                        <span class="material-symbols-outlined">{{ $icons[$i % count($icons)] }}</span>
                        {{ $announcement }}
                    </span>
                @endforeach
            @endfor
        </div>
    </footer>

    {{-- ============================================================ --}}
    {{-- JAVASCRIPT: Live Clock, Auto-Refresh, Chime Sound            --}}
    {{-- ============================================================ --}}
    <script>
        // ---- Live Clock ----
        function updateClock() {
            const now = new Date();
            const hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const displayHours = ((hours % 12) || 12).toString().padStart(2, '0');
            document.getElementById('live-clock').textContent = displayHours + ':' + minutes + ' ' + ampm;

            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            document.getElementById('live-date').textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate();
        }
        updateClock();
        setInterval(updateClock, 1000);

        // ---- Marquee Speed Adjustment ----
        (function() {
            const track = document.getElementById('marquee-track');
            if (track) {
                // Adjust speed based on content width
                const contentWidth = track.scrollWidth;
                const speed = Math.max(20, contentWidth / 50); // pixels per second roughly
                track.style.setProperty('--marquee-duration', speed + 's');
            }
        })();

        // ---- Chime Sound via Web Audio API ----
        function playChime() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();

                // Three-tone pleasant chime
                const frequencies = [523.25, 659.25, 783.99]; // C5, E5, G5
                const startTimes = [0, 0.15, 0.3];
                const duration = 0.6;

                frequencies.forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();

                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, ctx.currentTime);

                    gain.gain.setValueAtTime(0, ctx.currentTime + startTimes[i]);
                    gain.gain.linearRampToValueAtTime(0.3, ctx.currentTime + startTimes[i] + 0.05);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + startTimes[i] + duration);

                    osc.connect(gain);
                    gain.connect(ctx.destination);

                    osc.start(ctx.currentTime + startTimes[i]);
                    osc.stop(ctx.currentTime + startTimes[i] + duration);
                });

                // Final sustained note
                setTimeout(() => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(1046.5, ctx.currentTime); // C6
                    gain.gain.setValueAtTime(0, ctx.currentTime);
                    gain.gain.linearRampToValueAtTime(0.2, ctx.currentTime + 0.05);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.0);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 1.0);
                }, 450);

            } catch (e) {
                // Web Audio not available, silently ignore
            }
        }

        // ---- Token Change Detection ----
        const CURRENT_TOKEN = @json($data['current_serving']['token']);
        const STORAGE_KEY = 'tv_last_serving_token';

        (function detectTokenChange() {
            const lastToken = localStorage.getItem(STORAGE_KEY);
            if (lastToken !== null && lastToken !== CURRENT_TOKEN && CURRENT_TOKEN !== '00') {
                // Token has changed - play chime
                playChime();
            }
            localStorage.setItem(STORAGE_KEY, CURRENT_TOKEN);
        })();

        // ---- Auto-Refresh every 15 seconds ----
        setTimeout(() => {
            window.location.reload();
        }, 15000);
    </script>
</body>
</html>
