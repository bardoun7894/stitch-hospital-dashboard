@extends('layouts.app')

@section('title', 'إحصائيات اليوم - Daily Stats')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">إحصائيات اليوم (Daily Stats)</h1>
            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">{{ date('Y-m-d') }}</span>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <p class="text-sm font-medium text-gray-500 truncate">{{ __('messages.now_serving_report') }}</p>
                <p class="mt-1 text-3xl font-semibold text-indigo-600">#{{ $data['now_serving'] ?? '--' }}</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <p class="text-sm font-medium text-gray-500 truncate">{{ __('messages.last_issued') }}</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900">#{{ $data['last_issued'] ?? '--' }}</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <p class="text-sm font-medium text-gray-500 truncate">{{ __('messages.total_bookings') }}</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $data['stats']['bookings_today'] ?? 0 }}</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                <p class="text-sm font-medium text-gray-500 truncate">{{ __('messages.arrived') }}</p>
                <p class="mt-1 text-3xl font-semibold text-green-600">{{ $data['stats']['arrived'] ?? 0 }}</p>
            </div>
        </div>

        <!-- Advanced Stats -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">{{ __('messages.queue_details') }}</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">{{ __('messages.attendance_rate') }}</h4>
                        <div class="flex items-center">
                            @php
                                $total = $data['stats']['bookings_today'] ?? 1;
                                $arrived = $data['stats']['arrived'] ?? 0;
                                $percent = round(($arrived / max($total, 1)) * 100);
                            @endphp
                            <div class="relative w-full h-4 bg-gray-200 rounded-full overflow-hidden">
                                <div class="absolute top-0 left-0 h-full bg-green-500" style="width: {{ $percent }}%"></div>
                            </div>
                            <span class="ml-4 text-sm font-medium text-gray-700">{{ $percent }}%</span>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">{{ __('messages.attendance_description') }}</p>
                    </div>

                    <div>
                        <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">{{ __('messages.no_shows') }}</h4>
                        <div class="text-2xl font-bold text-red-600">
                            {{ count($data['skipped'] ?? []) }}
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ __('messages.no_shows_description') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                {{ __('messages.print_report') }}
            </button>
        </div>
    </div>
</div>
@endsection
