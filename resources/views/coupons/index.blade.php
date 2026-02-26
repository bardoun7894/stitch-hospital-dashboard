@extends('layouts.app')

@section('content')
    <div class="flex flex-col gap-6">
        {{-- Alerts --}}
        @if(session('success'))
        <div class="px-4 py-3 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-lg text-sm">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm">
            {{ session('error') }}
        </div>
        @endif

        {{-- Header --}}
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-gradient-to-br from-amber-100 to-orange-100 dark:from-amber-900/30 dark:to-orange-900/30 rounded-xl p-2.5">
                    <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-2xl">local_offer</span>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-[#111418] dark:text-white leading-tight">{{ __('messages.coupons_discounts') }}</h1>
                    <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ __('messages.manage_coupons_subtitle') }}</p>
                </div>
            </div>
            <a href="{{ route('coupons.create') }}" class="bg-primary hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-colors shadow-sm">
                <span class="material-symbols-outlined">add</span>
                {{ __('messages.create_coupon') }}
            </a>
        </div>

        {{-- Stats Summary --}}
        @php
            $totalCoupons = count($coupons);
            $activeCoupons = collect($coupons)->where('is_active', true)->count();
            $expiredCoupons = collect($coupons)->filter(fn($c) => ($c['valid_to'] ?? '') < date('Y-m-d'))->count();
            $totalUses = collect($coupons)->sum('current_uses');
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-4">
                <p class="text-xs text-[#637388] dark:text-[#9ca3af] font-medium uppercase tracking-wider">{{ __('messages.coupons') }}</p>
                <p class="text-2xl font-bold text-[#111418] dark:text-white mt-1">{{ $totalCoupons }}</p>
            </div>
            <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-4">
                <p class="text-xs text-[#637388] dark:text-[#9ca3af] font-medium uppercase tracking-wider">{{ __('messages.active') }}</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ $activeCoupons }}</p>
            </div>
            <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-4">
                <p class="text-xs text-[#637388] dark:text-[#9ca3af] font-medium uppercase tracking-wider">{{ __('messages.coupon_expired') }}</p>
                <p class="text-2xl font-bold text-red-500 dark:text-red-400 mt-1">{{ $expiredCoupons }}</p>
            </div>
            <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-4">
                <p class="text-xs text-[#637388] dark:text-[#9ca3af] font-medium uppercase tracking-wider">{{ __('messages.current_uses') }}</p>
                <p class="text-2xl font-bold text-[#111418] dark:text-white mt-1">{{ $totalUses }}</p>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#e5e7eb] dark:border-[#2d3748] bg-gray-50 dark:bg-[#202a37]">
                            <th class="text-start px-5 py-3 font-semibold text-[#637388] dark:text-[#9ca3af] uppercase text-xs tracking-wider">{{ __('messages.coupon_code') }}</th>
                            <th class="text-start px-5 py-3 font-semibold text-[#637388] dark:text-[#9ca3af] uppercase text-xs tracking-wider">{{ __('messages.discount_type') }}</th>
                            <th class="text-start px-5 py-3 font-semibold text-[#637388] dark:text-[#9ca3af] uppercase text-xs tracking-wider">{{ __('messages.discount_value') }}</th>
                            <th class="text-start px-5 py-3 font-semibold text-[#637388] dark:text-[#9ca3af] uppercase text-xs tracking-wider">{{ __('messages.valid_from') }} / {{ __('messages.valid_to') }}</th>
                            <th class="text-start px-5 py-3 font-semibold text-[#637388] dark:text-[#9ca3af] uppercase text-xs tracking-wider">{{ __('messages.current_uses') }}</th>
                            <th class="text-start px-5 py-3 font-semibold text-[#637388] dark:text-[#9ca3af] uppercase text-xs tracking-wider">{{ __('messages.clinic') }}</th>
                            <th class="text-start px-5 py-3 font-semibold text-[#637388] dark:text-[#9ca3af] uppercase text-xs tracking-wider">{{ __('messages.status') }}</th>
                            <th class="text-start px-5 py-3 font-semibold text-[#637388] dark:text-[#9ca3af] uppercase text-xs tracking-wider">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2d3748]">
                        @forelse($coupons as $coupon)
                        @php
                            $isExpired = ($coupon['valid_to'] ?? '') < date('Y-m-d');
                            $isActive = $coupon['is_active'] ?? false;
                            $maxUses = (int)($coupon['max_uses'] ?? 0);
                            $currentUses = (int)($coupon['current_uses'] ?? 0);
                            $isMaxedOut = $maxUses > 0 && $currentUses >= $maxUses;
                        @endphp
                        <tr class="{{ $isExpired ? 'opacity-50' : '' }} hover:bg-gray-50 dark:hover:bg-[#202a37] transition-colors">
                            <td class="px-5 py-4">
                                <span class="font-mono font-bold text-[#111418] dark:text-white bg-gray-100 dark:bg-[#2d3748] px-2.5 py-1 rounded-md text-xs tracking-wider">{{ $coupon['code'] ?? '' }}</span>
                            </td>
                            <td class="px-5 py-4">
                                @if(($coupon['discount_type'] ?? '') === 'percentage')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">
                                        {{ __('messages.percentage') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400">
                                        {{ __('messages.fixed_amount') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-semibold text-[#111418] dark:text-white">
                                @if(($coupon['discount_type'] ?? '') === 'percentage')
                                    {{ $coupon['discount_value'] ?? 0 }}%
                                @else
                                    {{ $coupon['discount_value'] ?? 0 }} {{ __('messages.sar') }}
                                @endif
                            </td>
                            <td class="px-5 py-4 text-[#637388] dark:text-[#9ca3af]">
                                <div class="text-xs">{{ $coupon['valid_from'] ?? '-' }}</div>
                                <div class="text-xs">{{ $coupon['valid_to'] ?? '-' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="text-[#111418] dark:text-white font-medium">{{ $currentUses }}</span>
                                <span class="text-[#637388] dark:text-[#9ca3af]">/</span>
                                <span class="text-[#637388] dark:text-[#9ca3af]">{{ $maxUses > 0 ? $maxUses : __('messages.unlimited') }}</span>
                            </td>
                            <td class="px-5 py-4 text-[#637388] dark:text-[#9ca3af] text-xs">
                                @if($coupon['clinic_id'] ?? null)
                                    {{ $clinicNames[$coupon['clinic_id']] ?? $coupon['clinic_id'] }}
                                @else
                                    <span class="text-green-600 dark:text-green-400">{{ __('messages.all_clinics') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if($isExpired)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
                                        <span class="size-1.5 rounded-full bg-red-500"></span>
                                        {{ __('messages.coupon_expired') }}
                                    </span>
                                @elseif($isMaxedOut)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-900/30 text-gray-700 dark:text-gray-400">
                                        <span class="size-1.5 rounded-full bg-gray-500"></span>
                                        {{ __('messages.coupon_max_uses_reached') }}
                                    </span>
                                @elseif($isActive)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                        <span class="size-1.5 rounded-full bg-green-500"></span>
                                        {{ __('messages.active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-900/30 text-gray-700 dark:text-gray-400">
                                        <span class="size-1.5 rounded-full bg-gray-500"></span>
                                        {{ __('messages.inactive') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    {{-- Toggle Active --}}
                                    <form action="{{ route('coupons.toggle', $coupon['id']) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="p-1.5 rounded-lg {{ $isActive ? 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20' : 'text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-900/20' }} transition-colors" title="{{ $isActive ? __('messages.deactivate') : __('messages.activate') }}">
                                            <span class="material-symbols-outlined text-lg">{{ $isActive ? 'toggle_on' : 'toggle_off' }}</span>
                                        </button>
                                    </form>

                                    {{-- Edit --}}
                                    <a href="{{ route('coupons.edit', $coupon['id']) }}" class="p-1.5 rounded-lg text-[#637388] hover:text-primary hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="{{ __('messages.edit') }}">
                                        <span class="material-symbols-outlined text-lg">edit</span>
                                    </a>

                                    {{-- Delete --}}
                                    <form action="{{ route('coupons.destroy', $coupon['id']) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-[#637388] hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="{{ __('messages.delete') }}">
                                            <span class="material-symbols-outlined text-lg">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="material-symbols-outlined text-4xl text-[#637388] dark:text-[#9ca3af]">local_offer</span>
                                    <p class="text-[#637388] dark:text-[#9ca3af]">{{ __('messages.no_data') }}</p>
                                    <a href="{{ route('coupons.create') }}" class="text-primary hover:underline text-sm font-medium">{{ __('messages.create_coupon') }}</a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
