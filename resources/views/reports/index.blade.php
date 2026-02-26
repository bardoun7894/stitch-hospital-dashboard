@extends('layouts.app')

@section('title', 'التقارير - Reports')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">{{ __('messages.reports_title') }}</h1>

        <!-- Date Range Filter -->
        <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm mb-6">
            <form id="reportFilterForm" class="flex flex-col sm:flex-row items-end gap-4">
                <div class="flex-1 w-full sm:w-auto">
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.date_from') }}</label>
                    <input type="date" id="date_from" name="date_from" value="{{ date('Y-m-d') }}"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div class="flex-1 w-full sm:w-auto">
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.date_to') }}</label>
                    <input type="date" id="date_to" name="date_to" value="{{ date('Y-m-d') }}"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div class="flex gap-2">
                    <a id="exportDailyBtn" href="{{ route('reports.export.daily') }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        {{ __('messages.export_daily_report') }}
                    </a>
                    <a id="exportDoctorLoadBtn" href="{{ route('reports.export.doctor-load') }}"
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        {{ __('messages.export_doctor_load') }}
                    </a>
                    <a id="exportBookingsBtn" href="{{ route('reports.export.bookings') }}"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700 transition shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        {{ __('messages.export_bookings') }}
                    </a>
                </div>
            </form>
        </div>

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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');
        const exportDailyBtn = document.getElementById('exportDailyBtn');
        const exportDoctorLoadBtn = document.getElementById('exportDoctorLoadBtn');
        const exportBookingsBtn = document.getElementById('exportBookingsBtn');

        const baseDailyUrl = "{{ route('reports.export.daily') }}";
        const baseDoctorLoadUrl = "{{ route('reports.export.doctor-load') }}";
        const baseBookingsUrl = "{{ route('reports.export.bookings') }}";

        function updateExportLinks() {
            const from = dateFrom.value;
            const to = dateTo.value;

            exportDailyBtn.href = baseDailyUrl + '?date=' + from;
            exportDoctorLoadBtn.href = baseDoctorLoadUrl + '?date=' + from;
            exportBookingsBtn.href = baseBookingsUrl + '?date_from=' + from + '&date_to=' + to;
        }

        dateFrom.addEventListener('change', updateExportLinks);
        dateTo.addEventListener('change', updateExportLinks);
        updateExportLinks();
    });
</script>
@endsection
