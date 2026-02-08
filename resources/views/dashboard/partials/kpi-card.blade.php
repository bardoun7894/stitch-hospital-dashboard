@props(['title', 'value', 'trend', 'trendType' => 'neutral', 'comparison' => 'vs last period', 'iconName', 'color' => 'blue'])
@php
    $bgClass = match($color) {
        'primary' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400',
        'alert-amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        'purple' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
        'gray' => 'bg-slate-50 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400',
        default => 'bg-slate-50 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400'
    };
@endphp

<div class="bg-white dark:bg-surface-dark p-6 rounded-2xl border border-border-light dark:border-border-dark shadow-sm card-hover flex flex-col justify-between h-36 relative overflow-hidden group">
    <!-- Background Decorator -->
    <div class="absolute -right-6 -top-6 size-24 rounded-full bg-current opacity-[0.03] pointer-events-none group-hover:scale-150 transition-transform duration-500 {{ $bgClass }}"></div>

    <div class="flex justify-between items-start relative z-10">
        <div>
            <p class="text-text-sub-light text-xs font-semibold uppercase tracking-wider mb-1">{{ $title }}</p>
            <h3 class="text-3xl font-bold text-text-main-light dark:text-white tracking-tight">{{ $value }}</h3>
        </div>
        <div class="p-2.5 rounded-xl {{ $bgClass }}">
            <span class="material-symbols-outlined text-2xl">{{ $iconName }}</span>
        </div>
    </div>
    
    <div class="relative z-10 flex items-center gap-2 mt-2">
         @if($trendType === 'up')
            <div class="flex items-center gap-0.5 text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded text-xs font-bold">
                <span class="material-symbols-outlined text-sm">trending_up</span>
                <span>{{ $trend }}</span>
            </div>
            <span class="text-text-sub-light text-xs">{{ $comparison }}</span>
        @elseif($trendType === 'down')
            <div class="flex items-center gap-0.5 text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded text-xs font-bold">
                 <span class="material-symbols-outlined text-sm">trending_down</span>
                 <span>{{ $trend }}</span>
            </div>
            <span class="text-text-sub-light text-xs">{{ $comparison }}</span>
        @elseif($trendType === 'bad-up')
             <div class="flex items-center gap-0.5 text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 px-1.5 py-0.5 rounded text-xs font-bold">
                 <span class="material-symbols-outlined text-sm">trending_up</span>
                 <span>{{ $trend }}</span>
             </div>
             <span class="text-text-sub-light text-xs">{{ $comparison }}</span>
        @elseif($trendType === 'neutral')
             <span class="text-text-sub-light text-xs font-medium">{{ $trend }}</span>
        @endif
    </div>
</div>
