@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('clinics.index') }}" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-[#637388]">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ __('messages.add_clinic') }}</h1>
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
    <form action="{{ route('clinics.store') }}" method="POST" class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.name') }} *</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="{{ __('messages.clinic_name_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.name_en') }}</label>
            <input type="text" name="name_en" value="{{ old('name_en') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="{{ __('messages.clinic_name_en_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.assign_hospital') }}</label>
            <select name="hospital_id" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <option value="">{{ __('messages.none') }} ({{ __('messages.standalone_clinic') }})</option>
                @foreach($hospitals as $hospital)
                <option value="{{ $hospital['id'] }}" {{ old('hospital_id', request('hospital_id')) === $hospital['id'] ? 'selected' : '' }}>{{ $hospital['name'] ?? $hospital['id'] }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.specialty') }}</label>
            <input type="text" name="specialty" value="{{ old('specialty') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="{{ __('messages.specialty_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.icon') }}</label>
            <input type="text" name="icon" value="{{ old('icon', 'medical_services') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="medical_services">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.address') }}</label>
            <input type="text" name="address" value="{{ old('address') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="{{ __('messages.address_placeholder') }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.status') }} *</label>
            <select name="status" required class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                <option value="paused" {{ old('status') === 'paused' ? 'selected' : '' }}>{{ __('messages.paused') }}</option>
                <option value="closed" {{ old('status') === 'closed' ? 'selected' : '' }}>{{ __('messages.closed') }}</option>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.geofence_radius') }} (m)</label>
                <input type="number" name="geofence_radius" value="{{ old('geofence_radius', 100) }}" min="50" max="1000" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.daily_capacity') }}</label>
                <input type="number" name="daily_capacity" value="{{ old('daily_capacity', 50) }}" min="1" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.opening_time') }}</label>
                <input type="time" name="open_time" value="{{ old('open_time', '09:00') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.closing_time') }}</label>
                <input type="time" name="close_time" value="{{ old('close_time', '17:00') }}" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.latitude') }}</label>
                <input type="number" name="latitude" value="{{ old('latitude') }}" step="any" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="24.7136">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#111418] dark:text-white mb-1.5">{{ __('messages.longitude') }}</label>
                <input type="number" name="longitude" value="{{ old('longitude') }}" step="any" class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="46.6753">
            </div>
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]">
            <button type="submit" class="px-6 py-2.5 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium text-sm transition-colors">
                {{ __('messages.add_clinic') }}
            </button>
            <a href="{{ route('clinics.index') }}" class="px-6 py-2.5 text-[#637388] hover:text-[#111418] dark:hover:text-white font-medium text-sm transition-colors">
                {{ __('messages.cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection
