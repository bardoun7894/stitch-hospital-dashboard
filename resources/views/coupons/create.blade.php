@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('coupons.index') }}" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-[#637388]">arrow_back</span>
        </a>
        <div class="flex items-center gap-3">
            <div class="bg-gradient-to-br from-amber-100 to-orange-100 dark:from-amber-900/30 dark:to-orange-900/30 rounded-xl p-2">
                <span class="material-symbols-outlined text-amber-600 dark:text-amber-400">local_offer</span>
            </div>
            <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ __('messages.create_coupon') }}</h1>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm">
        {{ session('error') }}
    </div>
    @endif

    @if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('coupons.store') }}" method="POST" class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-6 space-y-5" x-data="{
        discountType: '{{ old('discount_type', 'percentage') }}',
        code: '{{ old('code', '') }}'
    }">
        @csrf

        {{-- Coupon Code --}}
        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.coupon_code') }} *</label>
            <div class="flex gap-2">
                <input type="text" name="code" x-model="code" value="{{ old('code') }}" required
                    class="flex-1 px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary font-mono uppercase tracking-wider"
                    placeholder="SUMMER2025">
                <button type="button"
                    @click="code = Array.from({length: 8}, () => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'[Math.floor(Math.random() * 36)]).join('')"
                    class="px-4 py-2.5 bg-gray-100 dark:bg-[#2d3748] border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg text-sm font-medium text-[#637388] dark:text-[#9ca3af] hover:bg-gray-200 dark:hover:bg-[#374151] transition-colors whitespace-nowrap">
                    <span class="material-symbols-outlined text-base align-middle">casino</span>
                    {{ __('messages.generate_code') }}
                </button>
            </div>
        </div>

        {{-- Discount Type --}}
        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-2">{{ __('messages.discount_type') }} *</label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer px-4 py-2.5 rounded-lg border transition-all"
                    :class="discountType === 'percentage' ? 'border-primary bg-primary/5 text-primary' : 'border-[#e5e7eb] dark:border-[#2d3748] text-[#637388]'">
                    <input type="radio" name="discount_type" value="percentage" x-model="discountType" class="sr-only">
                    <span class="material-symbols-outlined text-lg">percent</span>
                    <span class="text-sm font-medium">{{ __('messages.percentage') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer px-4 py-2.5 rounded-lg border transition-all"
                    :class="discountType === 'fixed' ? 'border-primary bg-primary/5 text-primary' : 'border-[#e5e7eb] dark:border-[#2d3748] text-[#637388]'">
                    <input type="radio" name="discount_type" value="fixed" x-model="discountType" class="sr-only">
                    <span class="material-symbols-outlined text-lg">payments</span>
                    <span class="text-sm font-medium">{{ __('messages.fixed_amount') }}</span>
                </label>
            </div>
        </div>

        {{-- Discount Value --}}
        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">
                {{ __('messages.discount_value') }} *
                <span class="text-xs text-[#637388] dark:text-[#9ca3af] font-normal" x-text="discountType === 'percentage' ? '(1-100%)' : '({{ __('messages.sar') }})'"></span>
            </label>
            <div class="relative">
                <input type="number" name="discount_value" value="{{ old('discount_value') }}" required step="0.01" min="0.01"
                    :max="discountType === 'percentage' ? 100 : 99999"
                    class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary"
                    placeholder="10">
                <span class="absolute end-4 top-1/2 -translate-y-1/2 text-[#637388] dark:text-[#9ca3af] text-sm font-medium"
                    x-text="discountType === 'percentage' ? '%' : '{{ __('messages.sar') }}'"></span>
            </div>
        </div>

        {{-- Valid Period --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.valid_from') }} *</label>
                <input type="date" name="valid_from" value="{{ old('valid_from', date('Y-m-d')) }}" required
                    class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.valid_to') }} *</label>
                <input type="date" name="valid_to" value="{{ old('valid_to', date('Y-m-d', strtotime('+30 days'))) }}" required
                    class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
        </div>

        {{-- Max Uses --}}
        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">
                {{ __('messages.max_uses') }}
                <span class="text-xs text-[#637388] dark:text-[#9ca3af] font-normal">(0 = {{ __('messages.unlimited') }})</span>
            </label>
            <input type="number" name="max_uses" value="{{ old('max_uses', 0) }}" required min="0"
                class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary"
                placeholder="0">
        </div>

        {{-- Clinic --}}
        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.clinic') }}</label>
            <select name="clinic_id"
                class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <option value="">{{ __('messages.all_clinics') }}</option>
                @foreach($clinics as $clinic)
                <option value="{{ $clinic['id'] }}" {{ old('clinic_id') === $clinic['id'] ? 'selected' : '' }}>
                    {{ $clinic['name'] ?? $clinic['name_en'] ?? $clinic['id'] }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Active --}}
        <div class="flex items-center gap-3">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/30 rounded-full peer dark:bg-[#2d3748] peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
            </label>
            <span class="text-sm font-medium text-[#111418] dark:text-white">{{ __('messages.active') }}</span>
        </div>

        {{-- Buttons --}}
        <div class="flex justify-end gap-3 pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]">
            <a href="{{ route('coupons.index') }}" class="px-5 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg text-sm font-medium text-[#637388] dark:text-[#9ca3af] hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
                {{ __('messages.cancel') }}
            </a>
            <button type="submit" class="px-5 py-2.5 bg-primary hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors shadow-sm">
                {{ __('messages.create_coupon') }}
            </button>
        </div>
    </form>
</div>
@endsection
