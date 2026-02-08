@php
    $alerts = $alerts ?? [];
@endphp

<!-- Alerts Section -->
<h3 class="text-lg font-bold text-text-main-light dark:text-white mb-4 tracking-tight">{{ __('messages.active_alerts') }}</h3>
<div class="flex flex-col gap-3">
    @forelse($alerts as $alert)
        @php
            $color = match($alert['type']) {
                'critical' => 'rose',
                'warning' => 'amber',
                'info' => 'sky',
                default => 'slate'
            };
            $icon = match($alert['type']) {
                'critical' => 'error',
                'warning' => 'warning',
                'info' => 'info',
                default => 'notifications'
            };
        @endphp
        <div class="bg-white dark:bg-surface-dark p-4 rounded-xl border border-border-light dark:border-border-dark shadow-sm border-l-4 border-l-{{ $color }}-500 relative overflow-hidden group card-hover">
            <div class="flex justify-between items-start mb-2">
                <div class="flex items-center gap-2 text-{{ $color }}-600 dark:text-{{ $color }}-400">
                    <span class="material-symbols-outlined text-lg">{{ $icon }}</span>
                    <span class="text-[10px] font-bold uppercase tracking-wide">{{ ucfirst($alert['type']) }}</span>
                </div>
                <span class="text-[10px] text-text-sub-light dark:text-text-sub-dark font-medium">{{ $alert['time'] }}</span>
            </div>
            <p class="text-sm font-bold text-text-main-light dark:text-white mb-1 leading-snug">{{ $alert['title'] }}</p>
            <p class="text-xs text-text-sub-light dark:text-text-sub-dark mb-3 leading-relaxed">{{ $alert['description'] }}</p>
            @if($alert['action'] ?? null)
                <button class="text-xs font-semibold text-primary hover:text-primary-hover hover:underline flex items-center gap-1 transition-colors">
                    {{ $alert['action'] }}
                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                </button>
            @endif
        </div>
    @empty
        <div class="bg-white dark:bg-surface-dark p-6 rounded-xl border border-border-light dark:border-border-dark text-center shadow-sm flex flex-col items-center justify-center gap-2">
             <span class="material-symbols-outlined text-3xl text-emerald-500/50">check_circle</span>
            <p class="text-sm font-medium text-text-sub-light dark:text-text-sub-dark">{{ __('messages.no_active_alerts') }}</p>
        </div>
    @endforelse
</div>

<!-- System Status Mini Card -->
<div class="mt-6 p-5 rounded-xl bg-background-light dark:bg-white/5 border border-border-light dark:border-border-dark">
    <div class="flex items-center gap-2 mb-4">
        <span class="material-symbols-outlined text-text-sub-light text-lg">dns</span>
        <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-widest">{{ __('messages.system_health') }}</p>
    </div>
    <div class="space-y-4">
        <div>
            <div class="flex justify-between text-xs mb-1.5">
                <span class="text-text-main-light dark:text-white font-semibold">{{ __('messages.server_load') }}</span>
                <span class="text-emerald-600 dark:text-emerald-400 font-bold">24%</span>
            </div>
            <div class="h-1.5 w-full bg-border-light dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full bg-emerald-500 w-[24%] rounded-full shadow-[0_0_10px_rgba(16,185,129,0.3)]"></div>
            </div>
        </div>
        <div>
            <div class="flex justify-between text-xs mb-1.5">
                <span class="text-text-main-light dark:text-white font-semibold">{{ __('messages.database') }}</span>
                <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ __('messages.connected') }}</span>
            </div>
            <div class="h-1.5 w-full bg-border-light dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full bg-emerald-500 w-full rounded-full shadow-[0_0_10px_rgba(16,185,129,0.3)]"></div>
            </div>
        </div>
    </div>
</div>
