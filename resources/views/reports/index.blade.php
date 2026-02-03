@extends('layouts.app')

@section('title', 'التقارير - Reports')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">{{ __('messages.reports_title') }}</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Daily Stats -->
            <a href="{{ route('reports.daily') }}" class="block p-6 bg-white rounded-lg border border-gray-200 shadow-md hover:bg-gray-50 transition">
                <div class="flex items-center mb-4">
                    <div class="p-3 bg-blue-100 rounded-full text-blue-600 mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <h5 class="text-xl font-bold tracking-tight text-gray-900">{{ __('messages.daily_stats') }}</h5>
                </div>
                <p class="font-normal text-gray-700">{{ __('messages.daily_stats_description') }}</p>
            </a>

            <!-- Doctor Load -->
            <a href="{{ route('reports.doctor-load') }}" class="block p-6 bg-white rounded-lg border border-gray-200 shadow-md hover:bg-gray-50 transition">
                <div class="flex items-center mb-4">
                    <div class="p-3 bg-green-100 rounded-full text-green-600 mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <h5 class="text-xl font-bold tracking-tight text-gray-900">{{ __('messages.doctor_load_report') }}</h5>
                </div>
                <p class="font-normal text-gray-700">{{ __('messages.doctor_load_description') }}</p>
            </a>
        </div>
    </div>
</div>
@endsection
