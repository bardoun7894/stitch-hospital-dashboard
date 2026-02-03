@props(['title', 'value', 'trend', 'trendType' => 'neutral', 'comparison' => 'vs last period', 'iconName', 'color' => 'blue'])

@php
    $bgClass = match($color) {
        'primary' => 'bg-blue-50 dark:bg-blue-900/30',
        'alert-amber' => 'bg-orange-50 dark:bg-orange-900/30',
        'purple' => 'bg-purple-50 dark:bg-purple-900/30',
        'gray' => 'bg-gray-100 dark:bg-gray-800',
        default => 'bg-gray-50 dark:bg-gray-800'
    };
    $iconClass = match($color) {
        'primary' => 'text-primary',
        'alert-amber' => 'text-alert-amber',
        'purple' => 'text-purple-600 dark:text-purple-400',
        'gray' => 'text-[#637388] dark:text-[#9ca3af]',
        default => 'text-gray-600'
    };
@endphp

<div class="bg-white dark:bg-[#1a222e] p-6 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm flex flex-col justify-between h-32">
    <div class="flex justify-between items-start">
        <p class="text-[#637388] dark:text-[#9ca3af] font-medium text-sm">{{ $title }}</p>
        <div class="p-1.5 {{ $bgClass }} rounded-md">
            <span class="material-symbols-outlined {{ $iconClass }} text-xl">{{ $iconName }}</span>
        </div>
    </div>
    <div>
        <p class="text-2xl font-bold text-[#111418] dark:text-white">{{ $value }}</p>
        <div class="flex items-center gap-1 mt-1">
            @if($trendType === 'up')
                <span class="material-symbols-outlined text-success-green text-sm">trending_up</span>
                <span class="text-success-green text-xs font-bold">{{ $trend }}</span>
                <span class="text-[#9ca3af] text-xs ml-1">{{ $comparison }}</span>
            @elseif($trendType === 'down')
                <span class="material-symbols-outlined text-success-green text-sm">trending_down</span>
                <span class="text-success-green text-xs font-bold">{{ $trend }}</span>
                <span class="text-[#9ca3af] text-xs ml-1">{{ $comparison }}</span>
            @elseif($trendType === 'neutral')
                 <span class="text-[#637388] dark:text-[#9ca3af] text-xs font-medium">{{ $trend }}</span>
            @elseif($trendType === 'bad-up')
                 <span class="material-symbols-outlined text-alert-red text-sm">trending_up</span>
                 <span class="text-alert-red text-xs font-bold">{{ $trend }}</span>
                 <span class="text-[#9ca3af] text-xs ml-1">{{ $comparison }}</span>
            @endif
        </div>
    </div>
</div>
