@extends('layouts.app')

@section('content')
    <!-- Welcome Section -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight mb-1">
                {{ __('messages.good_morning') }}, {{ $currentUser['name'] ?? 'Doctor' }}
            </h1>
            <p class="text-text-sub-light dark:text-text-sub-dark text-sm">
                {{ __('messages.dashboard_overview_text') ?? 'Here is what’s happening in the hospital today.' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button class="bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark text-text-main-light dark:text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm card-hover flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">file_download</span>
                {{ __('messages.download_report') ?? 'Report' }}
            </button>
            <button class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-md shadow-primary/20 hover:bg-primary-hover transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">add</span>
                {{ __('messages.new_appointment') ?? 'New Appointment' }}
            </button>
        </div>
    </div>

    <!-- KPI Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        @include('dashboard.partials.kpi-card', [
            'title' => __('messages.patients_today_kpi'),
            'value' => $stats['total'] ?? '0',
            'trend' => $stats['total_trend'] ?? '0%',
            'trendType' => $stats['total_trend_type'] ?? 'neutral',
            'comparison' => __('messages.vs_yesterday'),
            'iconName' => 'person_add',
            'color' => 'primary'
        ])

         @include('dashboard.partials.kpi-card', [
            'title' => __('messages.patients_waiting_kpi'),
            'value' => $stats['waiting'] ?? '0',
            'trend' => $stats['waiting_trend'] ?? '0%',
            'trendType' => $stats['waiting_trend_type'] ?? 'neutral',
            'comparison' => __('messages.vs_yesterday'),
            'iconName' => 'hourglass_top',
            'color' => 'alert-amber'
        ])

         @include('dashboard.partials.kpi-card', [
            'title' => __('messages.average_waiting_time_kpi'),
            'value' => $stats['avg_wait'] ?? '0m',
            'trend' => '',
            'trendType' => 'neutral',
            'comparison' => '',
            'iconName' => 'schedule',
            'color' => 'purple'
        ])

         @include('dashboard.partials.kpi-card', [
            'title' => __('messages.no_show_rate_kpi'),
            'value' => $stats['no_show'] ?? '0%',
            'trend' => __('messages.trend_stable'),
            'trendType' => 'neutral',
            'iconName' => 'event_busy',
            'color' => 'gray'
        ])
    </div>

    <!-- Content Split: Table & Alerts -->
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-8">
        <!-- Main Table Section -->
        <div class="xl:col-span-3 flex flex-col gap-6">
            @include('dashboard.partials.table')
        </div>

        <!-- Right Alerts Panel -->
        <div class="xl:col-span-1 flex flex-col gap-6">
             @include('dashboard.partials.alerts')
        </div>
    </div>
@endsection
