@extends('layouts.app')

@section('content')
@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-[#111418] dark:text-white leading-tight">{{ __('messages.clinics_management') }}</h1>
                <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ __('messages.manage_hospital_clinics') }}</p>
            </div>
            <button class="bg-primary hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-colors shadow-sm">
                <span class="material-symbols-outlined">add</span>
                {{ __('messages.add_clinic') }}
            </button>
        </div>

        <!-- Clinics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($clinics as $clinic)
            @php
                $statusColor = match($clinic['status']) {
                    'Running' => 'green',
                    'High Load' => 'amber',
                    'Paused' => 'red',
                    default => 'gray'
                };
            @endphp
            <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm overflow-hidden hover:shadow-md transition-shadow group">
                <div class="p-5 border-b border-[#e5e7eb] dark:border-[#2d3748] flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        <div class="size-12 rounded-xl bg-{{ $clinic['icon_color'] }}-100 dark:bg-{{ $clinic['icon_color'] }}-900/40 text-{{ $clinic['icon_color'] }}-600 flex items-center justify-center">
                            <span class="material-symbols-outlined">{{ $clinic['icon'] }}</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-[#111418] dark:text-white">{{ $clinic['name'] }}</h3>
                            <p class="text-xs text-[#637388] dark:text-[#9ca3af]">{{ $clinic['patients_waiting'] }} {{ __('messages.patients_waiting_text') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 dark:bg-{{ $statusColor }}-900/30 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-400 border border-{{ $statusColor }}-200 dark:border-{{ $statusColor }}-800">
                        <span class="size-1.5 rounded-full bg-{{ $statusColor }}-500"></span>
                        {{ $clinic['status'] }}
                    </span>
                </div>
                <div class="p-5 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-[#637388] dark:text-[#9ca3af]">{{ __('messages.doctors_on_duty_label') }}</span>
                        <span class="font-medium text-[#111418] dark:text-white">{{ $clinic['doctors_on_duty'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-[#637388] dark:text-[#9ca3af]">{{ __('messages.average_wait_label') }}</span>
                        <span class="font-medium text-[#111418] dark:text-white">{{ $clinic['avg_wait'] }}</span>
                    </div>
                     <div class="flex justify-between text-sm">
                        <span class="text-[#637388] dark:text-[#9ca3af]">{{ __('messages.patients_waiting_label') }}</span>
                        <span class="font-medium text-[#111418] dark:text-white">{{ $clinic['patients_waiting'] }}</span>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-[#202a37] p-4 border-t border-[#e5e7eb] dark:border-[#2d3748] flex justify-end gap-3">
                    <a href="{{ route('bookings.index') }}" class="text-[#637388] dark:text-[#9ca3af] hover:text-primary text-sm font-medium transition-colors">{{ __('messages.view_queue') }}</a>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <button class="text-[#637388] dark:text-[#9ca3af] hover:text-primary text-sm font-medium transition-colors">{{ __('messages.details') }}</button>
                </div>
            </div>
            @endforeach
            
             <!-- Add Grid Item -->
            <button class="border-2 border-dashed border-[#e5e7eb] dark:border-[#2d3748] rounded-xl p-6 flex flex-col items-center justify-center text-[#637388] dark:text-[#9ca3af] hover:border-primary hover:text-primary transition-colors h-full min-h-[250px] bg-gray-50 dark:bg-[#1a222e]/50">
                <span class="material-symbols-outlined text-4xl mb-3">add_circle</span>
                <span class="font-medium">{{ __('messages.add_new_clinic') }}</span>
            </button>
        </div>
    </div>
@endsection
