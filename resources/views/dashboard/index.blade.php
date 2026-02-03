@extends('layouts.app')

@section('content')
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
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
        <!-- Main Table Section -->
        <div class="xl:col-span-3 flex flex-col gap-4">
            @include('dashboard.partials.table')
        </div>

        <!-- Right Alerts Panel -->
        <div class="xl:col-span-1 flex flex-col gap-4">
             @include('dashboard.partials.alerts')
        </div>
    </div>
@endsection
