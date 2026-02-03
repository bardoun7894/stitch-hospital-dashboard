@php
    $alerts = $alerts ?? [];
@endphp

<!-- Alerts Section -->
<h3 class="text-lg font-bold text-[#111418] dark:text-white">{{ __('messages.active_alerts') }}</h3>
<div class="flex flex-col gap-3">
    @forelse($alerts as $alert)
        @php
            $color = match($alert['type']) {
                'critical' => 'alert-red',
                'warning' => 'alert-amber',
                'info' => 'blue',
                default => 'gray'
            };
            $icon = match($alert['type']) {
                'critical' => 'error',
                'warning' => 'warning',
                'info' => 'info',
                default => 'notifications'
            };
        @endphp
        <div class="bg-white dark:bg-[#1a222e] p-4 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm border-l-4 border-l-{{ $color === 'blue' ? 'blue-400' : $color }} relative overflow-hidden group">
            <div class="flex justify-between items-start mb-2">
                <div class="flex items-center gap-2 text-{{ $color === 'blue' ? 'blue-500' : $color }}">
                    <span class="material-symbols-outlined text-lg">{{ $icon }}</span>
                    <span class="text-xs font-bold uppercase tracking-wide">{{ ucfirst($alert['type']) }}</span>
                </div>
                <span class="text-[10px] text-[#9ca3af]">{{ $alert['time'] }}</span>
            </div>
            <p class="text-sm font-semibold text-[#111418] dark:text-white mb-1">{{ $alert['title'] }}</p>
            <p class="text-xs text-[#637388] dark:text-[#9ca3af] mb-3">{{ $alert['description'] }}</p>
            @if($alert['action'] ?? null)
                <button class="text-xs font-medium text-primary hover:underline">{{ $alert['action'] }}</button>
            @endif
        </div>
    @empty
        <div class="bg-white dark:bg-[#1a222e] p-4 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] text-center">
            <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ __('messages.no_active_alerts') }}</p>
        </div>
    @endforelse
</div>

<!-- System Status Mini Card -->
<div class="mt-2 p-4 rounded-xl bg-gray-100 dark:bg-[#202a37] border border-gray-200 dark:border-[#2d3748]">
    <p class="text-xs font-bold text-[#637388] dark:text-[#9ca3af] uppercase mb-3">{{ __('messages.system_health') }}</p>
    <div class="space-y-3">
        <div>
            <div class="flex justify-between text-xs mb-1">
                <span class="text-[#111418] dark:text-white font-medium">{{ __('messages.server_load') }}</span>
                <span class="text-green-600 dark:text-green-400">{{ __('messages.normal') }} (24%)</span>
            </div>
            <div class="h-1.5 w-full bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-green-500 w-[24%] rounded-full"></div>
            </div>
        </div>
        <div>
            <div class="flex justify-between text-xs mb-1">
                <span class="text-[#111418] dark:text-white font-medium">{{ __('messages.database') }}</span>
                <span class="text-green-600 dark:text-green-400">{{ __('messages.connected') }}</span>
            </div>
            <div class="h-1.5 w-full bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full bg-green-500 w-full rounded-full"></div>
            </div>
        </div>
    </div>
</div>
