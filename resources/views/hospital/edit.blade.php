@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('hospital.index') }}" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-[#637388]">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ __('messages.edit_hospital') }}</h1>
            <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ $hospital['name'] ?? '' }}</p>
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
    <form action="{{ route('hospital.update', $hospital['id']) }}" method="POST" class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-6 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.name') }} *</label>
                <input type="text" name="name" value="{{ old('name', $hospital['name'] ?? '') }}" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.name_en') }}</label>
                <input type="text" name="name_en" value="{{ old('name_en', $hospital['name_en'] ?? '') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.address') }}</label>
            <input type="text" name="address" value="{{ old('address', $hospital['address'] ?? '') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone', $hospital['phone'] ?? '') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" dir="ltr">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.status') }}</label>
                @php $currentStatus = old('status', $hospital['status'] ?? 'active'); @endphp
                <select name="status" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                    <option value="inactive" {{ $currentStatus === 'inactive' ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                </select>
            </div>
        </div>

        <div class="border-t border-[#e5e7eb] dark:border-[#2d3748] pt-5">
            <h3 class="text-sm font-bold text-[#111418] dark:text-white mb-3">{{ __('messages.location') }}</h3>
            @php
                $lat = old('latitude', $hospital['location']['lat'] ?? $hospital['location']['latitude'] ?? '');
                $lng = old('longitude', $hospital['location']['lng'] ?? $hospital['location']['longitude'] ?? '');
            @endphp
            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-[#637388] dark:text-[#9ca3af] mb-1.5">{{ __('messages.latitude') }}</label>
                    <input type="number" step="any" name="latitude" value="{{ $lat }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" dir="ltr">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#637388] dark:text-[#9ca3af] mb-1.5">{{ __('messages.longitude') }}</label>
                    <input type="number" step="any" name="longitude" value="{{ $lng }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" dir="ltr">
                </div>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-lg text-sm font-bold hover:bg-primary/90 transition-colors">{{ __('messages.update') }}</button>
            <a href="{{ route('hospital.index') }}" class="px-6 py-2.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-sm font-medium hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">{{ __('messages.cancel') }}</a>
        </div>
    </form>

    {{-- Quick Actions --}}
    <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-6 mt-6">
        <h3 class="text-sm font-bold text-[#111418] dark:text-white mb-4">{{ __('messages.quick_actions') }}</h3>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('clinics.create', ['hospital_id' => $hospital['id']]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-sm font-medium text-[#111418] dark:text-white hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
                <span class="material-symbols-outlined text-base text-primary">add</span>
                {{ __('messages.add_clinic_to_hospital') }}
            </a>
            <a href="{{ route('users.create', ['hospital_id' => $hospital['id']]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-sm font-medium text-[#111418] dark:text-white hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
                <span class="material-symbols-outlined text-base text-primary">person_add</span>
                {{ __('messages.add_staff_to_hospital') }}
            </a>
            <a href="{{ route('clinics.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-sm font-medium text-[#111418] dark:text-white hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
                <span class="material-symbols-outlined text-base text-primary">list</span>
                {{ __('messages.view_all_clinics') }}
            </a>
        </div>
    </div>
</div>
@endsection
