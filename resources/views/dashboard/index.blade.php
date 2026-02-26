@extends('layouts.app')

@section('content')
    <!-- Welcome Section -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight mb-1">
                {{ __('messages.good_morning') }}, {{ $currentUser['name'] ?? 'Doctor' }}
            </h1>
            <p class="text-text-sub-light dark:text-text-sub-dark text-sm">
                {{ __('messages.dashboard_overview_text') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('reports.export.daily') }}" class="bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark text-text-main-light dark:text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm card-hover flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">file_download</span>
                {{ __('messages.download_report') }}
            </a>
            @if(\App\Http\Middleware\RoleMiddleware::hasAnyRole(['reception', 'clinic_admin', 'hospital_manager', 'super_admin']))
            <button class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-md shadow-primary/20 hover:bg-primary-hover transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">add</span>
                {{ __('messages.new_appointment') }}
            </button>
            @endif
        </div>
    </div>

    {{-- Pending Hospitals Alert (super_admin only) --}}
    @if(($pendingHospitalsCount ?? 0) > 0)
    <a href="{{ route('hospital.index', ['status' => 'pending']) }}" class="mb-6 px-5 py-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-900/30 rounded-2xl flex items-center justify-between gap-4 hover:bg-amber-100/50 dark:hover:bg-amber-900/30 transition-colors block">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400">
                <span class="material-symbols-outlined">pending_actions</span>
            </div>
            <div>
                <p class="text-sm font-bold text-amber-800 dark:text-amber-200">{{ __('messages.pending_hospitals') }}</p>
                <p class="text-xs text-amber-600 dark:text-amber-400">{{ $pendingHospitalsCount }} {{ __('messages.hospitals_awaiting_review') }}</p>
            </div>
        </div>
        <span class="material-symbols-outlined text-amber-600 dark:text-amber-400">arrow_forward</span>
    </a>
    @endif

    <!-- KPI Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Patients Today -->
        <div class="bg-white dark:bg-[#1a2027] p-6 rounded-2xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-primary">person_add</span>
            </div>
            <div class="relative z-10">
                <p class="text-[#637388] dark:text-gray-400 text-sm font-bold uppercase tracking-wider mb-2">{{ __('messages.patients_today_kpi') }}</p>
                <div class="flex items-end gap-3 mb-2">
                    <h3 class="text-[#111418] dark:text-white text-4xl font-black">{{ $stats['total'] ?? '0' }}</h3>
                    <span class="mb-1 text-xs font-bold px-2 py-0.5 rounded-full {{ ($stats['total_trend_type'] ?? 'neutral') === 'up' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                        {{ $stats['total_trend'] ?? '0%' }}
                    </span>
                </div>
                <p class="text-xs text-[#637388] dark:text-gray-500 font-medium">{{ __('messages.vs_yesterday') }}</p>
            </div>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-indigo-500"></div>
        </div>

        <!-- Waiting -->
        <div class="bg-white dark:bg-[#1a2027] p-6 rounded-2xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-amber-500">hourglass_top</span>
            </div>
            <div class="relative z-10">
                <p class="text-[#637388] dark:text-gray-400 text-sm font-bold uppercase tracking-wider mb-2">{{ __('messages.patients_waiting_kpi') }}</p>
                <div class="flex items-end gap-3 mb-2">
                    <h3 class="text-[#111418] dark:text-white text-4xl font-black">{{ $stats['waiting'] ?? '0' }}</h3>
                    <span class="mb-1 text-xs font-bold px-2 py-0.5 rounded-full {{ ($stats['waiting_trend_type'] ?? 'neutral') === 'down' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $stats['waiting_trend'] ?? '0%' }}
                    </span>
                </div>
                <p class="text-xs text-[#637388] dark:text-gray-500 font-medium">{{ __('messages.vs_yesterday') }}</p>
            </div>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-amber-400 to-orange-500"></div>
        </div>

        <!-- Avg Wait -->
        <div class="bg-white dark:bg-[#1a2027] p-6 rounded-2xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-purple-500">schedule</span>
            </div>
            <div class="relative z-10">
                <p class="text-[#637388] dark:text-gray-400 text-sm font-bold uppercase tracking-wider mb-2">{{ __('messages.average_waiting_time_kpi') }}</p>
                <div class="flex items-end gap-3 mb-2">
                    <h3 class="text-[#111418] dark:text-white text-4xl font-black">{{ $stats['avg_wait'] ?? '0m' }}</h3>
                </div>
                <p class="text-xs text-[#637388] dark:text-gray-500 font-medium">{{ __('messages.trend_stable') }}</p>
            </div>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-purple-500 to-pink-500"></div>
        </div>

        <!-- No Show -->
        <div class="bg-white dark:bg-[#1a2027] p-6 rounded-2xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <span class="material-symbols-outlined text-6xl text-gray-400">event_busy</span>
            </div>
            <div class="relative z-10">
                <p class="text-[#637388] dark:text-gray-400 text-sm font-bold uppercase tracking-wider mb-2">{{ __('messages.no_show_rate_kpi') }}</p>
                <div class="flex items-end gap-3 mb-2">
                    <h3 class="text-[#111418] dark:text-white text-4xl font-black">{{ $stats['no_show'] ?? '0%' }}</h3>
                </div>
                <p class="text-xs text-[#637388] dark:text-gray-500 font-medium">{{ __('messages.trend_stable') }}</p>
            </div>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-gray-400 to-gray-600"></div>
        </div>
    </div>

    {{-- Appointment Reminder Stats (clinic_admin, hospital_manager, super_admin) --}}
    @if(!is_null($reminderStats ?? null))
    <div class="mb-6 px-5 py-4 bg-sky-50 dark:bg-sky-900/20 border border-sky-100 dark:border-sky-900/30 rounded-2xl flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-xl bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400">
                <span class="material-symbols-outlined">notifications_active</span>
            </div>
            <div>
                <p class="text-sm font-bold text-sky-800 dark:text-sky-200">{{ __('messages.reminders_sent') }}</p>
                <p class="text-xs text-sky-600 dark:text-sky-400">
                    @if(($reminderStats['total'] ?? 0) > 0)
                        {{ __('messages.reminders_sent_count', ['sent' => $reminderStats['sent'] ?? 0, 'total' => $reminderStats['total'] ?? 0]) }}
                    @else
                        {{ __('messages.no_reminders_needed') }}
                    @endif
                </p>
            </div>
        </div>
        @if(($reminderStats['total'] ?? 0) > 0)
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold {{ ($reminderStats['sent'] ?? 0) >= ($reminderStats['total'] ?? 0) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' }}">
                <span class="material-symbols-outlined text-sm">{{ ($reminderStats['sent'] ?? 0) >= ($reminderStats['total'] ?? 0) ? 'check_circle' : 'schedule' }}</span>
                {{ $reminderStats['sent'] ?? 0 }}/{{ $reminderStats['total'] ?? 0 }}
            </span>
        </div>
        @endif
    </div>
    @endif

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Line Chart: Bookings Last 7 Days -->
        <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl border border-border-light dark:border-border-dark relative overflow-hidden">
            <div class="absolute -right-6 -top-6 size-24 rounded-full bg-indigo-500 opacity-[0.03] pointer-events-none"></div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white tracking-tight flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg text-indigo-500">show_chart</span>
                    {{ __('messages.bookings_last_7_days') }}
                </h3>
            </div>
            <div class="relative" style="height: 260px;">
                <div id="bookings7daysLoading" class="absolute inset-0 flex flex-col items-center justify-center z-10 bg-white/80 dark:bg-surface-dark/80">
                    <span class="material-symbols-outlined text-3xl text-indigo-400 animate-pulse mb-2">show_chart</span>
                    <p class="text-text-sub-light dark:text-text-sub-dark text-xs font-medium">{{ __('messages.loading') }}</p>
                </div>
                <canvas id="bookings7daysChart"></canvas>
            </div>
            <div id="bookings7daysEmpty" class="hidden absolute inset-0 flex flex-col items-center justify-center">
                <span class="material-symbols-outlined text-3xl text-text-sub-light/30 dark:text-text-sub-dark/30 mb-2">show_chart</span>
                <p class="text-text-sub-light dark:text-text-sub-dark text-sm">{{ __('messages.no_chart_data') }}</p>
            </div>
        </div>

        <!-- Doughnut Chart: Status Breakdown -->
        <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl border border-border-light dark:border-border-dark relative overflow-hidden">
            <div class="absolute -left-6 -bottom-6 size-24 rounded-full bg-violet-500 opacity-[0.03] pointer-events-none"></div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white tracking-tight flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg text-violet-500">donut_large</span>
                    {{ __('messages.status_breakdown') }}
                </h3>
            </div>
            <div class="relative flex items-center justify-center" style="height: 260px;">
                <div id="statusBreakdownLoading" class="absolute inset-0 flex flex-col items-center justify-center z-10 bg-white/80 dark:bg-surface-dark/80">
                    <span class="material-symbols-outlined text-3xl text-violet-400 animate-pulse mb-2">donut_large</span>
                    <p class="text-text-sub-light dark:text-text-sub-dark text-xs font-medium">{{ __('messages.loading') }}</p>
                </div>
                <canvas id="statusBreakdownChart"></canvas>
            </div>
            <div id="statusBreakdownEmpty" class="hidden absolute inset-0 flex flex-col items-center justify-center">
                <span class="material-symbols-outlined text-3xl text-text-sub-light/30 dark:text-text-sub-dark/30 mb-2">donut_large</span>
                <p class="text-text-sub-light dark:text-text-sub-dark text-sm">{{ __('messages.no_chart_data') }}</p>
            </div>
        </div>
    </div>

    <!-- Doctor Utilization Bar Chart (full width) -->
    <div class="mb-8">
        <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl border border-border-light dark:border-border-dark relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 size-32 rounded-full bg-emerald-500 opacity-[0.03] pointer-events-none"></div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white tracking-tight flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg text-emerald-500">group</span>
                    {{ __('messages.doctor_utilization') }}
                </h3>
            </div>
            <div class="relative" style="min-height: 200px; max-height: 400px;">
                <div id="doctorUtilizationLoading" class="absolute inset-0 flex flex-col items-center justify-center z-10 bg-white/80 dark:bg-surface-dark/80">
                    <span class="material-symbols-outlined text-3xl text-emerald-400 animate-pulse mb-2">group</span>
                    <p class="text-text-sub-light dark:text-text-sub-dark text-xs font-medium">{{ __('messages.loading') }}</p>
                </div>
                <canvas id="doctorUtilizationChart"></canvas>
            </div>
            <div id="doctorUtilizationEmpty" class="hidden absolute inset-0 flex flex-col items-center justify-center">
                <span class="material-symbols-outlined text-3xl text-text-sub-light/30 dark:text-text-sub-dark/30 mb-2">group</span>
                <p class="text-text-sub-light dark:text-text-sub-dark text-sm">{{ __('messages.no_chart_data') }}</p>
            </div>
        </div>
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

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const isDark = document.documentElement.classList.contains('dark');

    // Color palette matching the app theme
    const colors = {
        primary: isDark ? 'rgba(129, 140, 248, 1)' : 'rgba(79, 70, 229, 1)',
        primaryBg: isDark ? 'rgba(129, 140, 248, 0.15)' : 'rgba(79, 70, 229, 0.08)',
        confirmed: isDark ? 'rgba(52, 211, 153, 1)' : 'rgba(16, 185, 129, 1)',
        completed: isDark ? 'rgba(96, 165, 250, 1)' : 'rgba(59, 130, 246, 1)',
        cancelled: isDark ? 'rgba(248, 113, 113, 1)' : 'rgba(239, 68, 68, 1)',
        noShow: isDark ? 'rgba(251, 191, 36, 1)' : 'rgba(245, 158, 11, 1)',
        pending: isDark ? 'rgba(148, 163, 184, 1)' : 'rgba(100, 116, 139, 1)',
        bookings: isDark ? 'rgba(129, 140, 248, 1)' : 'rgba(79, 70, 229, 1)',
        capacity: isDark ? 'rgba(148, 163, 184, 0.4)' : 'rgba(203, 213, 225, 0.6)',
        gridColor: isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(203, 213, 225, 0.4)',
        textColor: isDark ? 'rgba(203, 213, 225, 1)' : 'rgba(71, 85, 105, 1)',
        textMuted: isDark ? 'rgba(148, 163, 184, 1)' : 'rgba(100, 116, 139, 1)',
    };

    // Global Chart.js defaults for the dashboard theme
    Chart.defaults.color = colors.textMuted;
    Chart.defaults.font.family = "'Inter', 'Cairo', sans-serif";
    Chart.defaults.font.size = 12;

    let bookings7daysChart = null;
    let statusBreakdownChart = null;
    let doctorUtilizationChart = null;

    function hideLoading(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    function renderBookings7Days(data) {
        const canvas = document.getElementById('bookings7daysChart');
        const emptyEl = document.getElementById('bookings7daysEmpty');
        hideLoading('bookings7daysLoading');
        if (!canvas) return;

        const hasData = data && data.length > 0 && data.some(d => d.count > 0);
        if (!hasData) {
            canvas.style.display = 'none';
            emptyEl && emptyEl.classList.remove('hidden');
            return;
        }

        const labels = data.map(d => {
            const date = new Date(d.date + 'T00:00:00');
            return date.toLocaleDateString(document.documentElement.lang === 'ar' ? 'ar-SA' : 'en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        });
        const values = data.map(d => d.count);

        if (bookings7daysChart) bookings7daysChart.destroy();

        bookings7daysChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '{{ __("messages.bookings_count") }}',
                    data: values,
                    borderColor: colors.primary,
                    backgroundColor: colors.primaryBg,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: colors.primary,
                    pointBorderColor: isDark ? '#1e293b' : '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(30, 41, 59, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                        titleColor: isDark ? '#e2e8f0' : '#334155',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? 'rgba(148, 163, 184, 0.2)' : 'rgba(203, 213, 225, 0.5)',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: colors.textMuted, font: { size: 11 } },
                        border: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: colors.gridColor, drawBorder: false },
                        ticks: {
                            color: colors.textMuted,
                            font: { size: 11 },
                            stepSize: 1,
                            precision: 0,
                        },
                        border: { display: false },
                    }
                }
            }
        });
    }

    function renderStatusBreakdown(data) {
        const canvas = document.getElementById('statusBreakdownChart');
        const emptyEl = document.getElementById('statusBreakdownEmpty');
        hideLoading('statusBreakdownLoading');
        if (!canvas) return;

        const total = Object.values(data).reduce((a, b) => a + b, 0);
        if (total === 0) {
            canvas.style.display = 'none';
            emptyEl && emptyEl.classList.remove('hidden');
            return;
        }

        const labels = [
            '{{ __("messages.confirmed") }}',
            '{{ __("messages.completed") }}',
            '{{ __("messages.cancelled") }}',
            '{{ __("messages.no_show") }}',
            '{{ __("messages.pending") }}'
        ];
        const values = [
            data.confirmed || 0,
            data.completed || 0,
            data.cancelled || 0,
            data.noShow || 0,
            data.pending || 0,
        ];
        const bgColors = [
            colors.confirmed,
            colors.completed,
            colors.cancelled,
            colors.noShow,
            colors.pending,
        ];

        if (statusBreakdownChart) statusBreakdownChart.destroy();

        statusBreakdownChart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: bgColors,
                    borderColor: isDark ? '#1e293b' : '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: colors.textColor,
                            padding: 16,
                            usePointStyle: true,
                            pointStyleWidth: 10,
                            font: { size: 11, weight: '500' },
                        }
                    },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(30, 41, 59, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                        titleColor: isDark ? '#e2e8f0' : '#334155',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? 'rgba(148, 163, 184, 0.2)' : 'rgba(203, 213, 225, 0.5)',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                    }
                }
            }
        });
    }

    function renderDoctorUtilization(data) {
        const canvas = document.getElementById('doctorUtilizationChart');
        const emptyEl = document.getElementById('doctorUtilizationEmpty');
        hideLoading('doctorUtilizationLoading');
        if (!canvas) return;

        if (!data || data.length === 0) {
            canvas.style.display = 'none';
            emptyEl && emptyEl.classList.remove('hidden');
            return;
        }

        // Adjust canvas container height based on data length
        const containerHeight = Math.max(200, data.length * 50 + 60);
        canvas.parentElement.style.height = containerHeight + 'px';

        const labels = data.map(d => d.name);
        const bookings = data.map(d => d.bookings);
        const capacity = data.map(d => d.capacity);

        if (doctorUtilizationChart) doctorUtilizationChart.destroy();

        doctorUtilizationChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '{{ __("messages.bookings_count") }}',
                        data: bookings,
                        backgroundColor: colors.bookings,
                        borderRadius: 6,
                        barPercentage: 0.5,
                        categoryPercentage: 0.7,
                    },
                    {
                        label: '{{ __("messages.capacity") }}',
                        data: capacity,
                        backgroundColor: colors.capacity,
                        borderRadius: 6,
                        barPercentage: 0.5,
                        categoryPercentage: 0.7,
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            color: colors.textColor,
                            padding: 16,
                            usePointStyle: true,
                            pointStyleWidth: 10,
                            font: { size: 11, weight: '500' },
                        }
                    },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(30, 41, 59, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                        titleColor: isDark ? '#e2e8f0' : '#334155',
                        bodyColor: isDark ? '#cbd5e1' : '#475569',
                        borderColor: isDark ? 'rgba(148, 163, 184, 0.2)' : 'rgba(203, 213, 225, 0.5)',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: colors.gridColor, drawBorder: false },
                        ticks: {
                            color: colors.textMuted,
                            font: { size: 11 },
                            stepSize: 1,
                            precision: 0,
                        },
                        border: { display: false },
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: colors.textColor, font: { size: 12, weight: '500' } },
                        border: { display: false },
                    }
                }
            }
        });
    }

    // Fetch chart data from API and render all charts
    function loadChartData() {
        fetch('{{ route("dashboard.chart-data") }}', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }
        })
        .then(response => response.json())
        .then(data => {
            renderBookings7Days(data.bookings_7days || []);
            renderStatusBreakdown(data.status_breakdown || {});
            renderDoctorUtilization(data.doctor_utilization || []);
        })
        .catch(error => {
            console.warn('Failed to load chart data:', error);
            // Hide loading indicators
            ['bookings7daysLoading', 'statusBreakdownLoading', 'doctorUtilizationLoading'].forEach(id => hideLoading(id));
            // Show empty states
            ['bookings7daysEmpty', 'statusBreakdownEmpty', 'doctorUtilizationEmpty'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.remove('hidden');
            });
            ['bookings7daysChart', 'statusBreakdownChart', 'doctorUtilizationChart'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
        });
    }

    // Load charts once DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadChartData);
    } else {
        loadChartData();
    }
})();
</script>
@endsection
