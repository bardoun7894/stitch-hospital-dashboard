@extends('layouts.app')

@section('content')
    <!-- Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <div class="bg-gradient-to-br from-emerald-500/10 to-green-500/10 rounded-xl p-2 flex items-center justify-center border border-emerald-500/10 shadow-sm">
                    <span class="material-symbols-outlined text-emerald-600 dark:text-emerald-400 text-2xl">account_balance</span>
                </div>
                <h1 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight">
                    {{ __('messages.financial_dashboard') }}
                </h1>
            </div>
            <p class="text-text-sub-light dark:text-text-sub-dark text-sm ltr:ml-14 rtl:mr-14">
                {{ __('messages.financial_dashboard_subtitle') }}
            </p>
        </div>
    </div>

    <!-- Date Range Filter Bar -->
    <div class="bg-white dark:bg-surface-dark p-4 rounded-2xl border border-border-light dark:border-border-dark mb-6 shadow-sm">
        <form method="GET" action="{{ route('financial.index') }}" class="flex flex-col sm:flex-row items-end gap-3">
            <div class="flex-1 w-full sm:w-auto">
                <label for="date_from" class="block text-[10px] font-bold text-text-sub-light uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.date_from') }}</label>
                <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}"
                    class="w-full rounded-xl border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-text-main-light dark:text-text-main-dark shadow-sm focus:border-primary focus:ring-primary text-sm px-3 py-2">
            </div>
            <div class="flex-1 w-full sm:w-auto">
                <label for="date_to" class="block text-[10px] font-bold text-text-sub-light uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.date_to') }}</label>
                <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}"
                    class="w-full rounded-xl border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-text-main-light dark:text-text-main-dark shadow-sm focus:border-primary focus:ring-primary text-sm px-3 py-2">
            </div>
            <div class="flex-1 w-full sm:w-auto">
                <label for="clinic_id" class="block text-[10px] font-bold text-text-sub-light uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.clinic_filter') }}</label>
                <select id="clinic_id" name="clinic_id"
                    class="w-full rounded-xl border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-text-main-light dark:text-text-main-dark shadow-sm focus:border-primary focus:ring-primary text-sm px-3 py-2">
                    <option value="">{{ __('messages.all_clinics') }}</option>
                    @foreach($clinics as $clinic)
                        <option value="{{ $clinic['id'] }}" {{ $clinicId == $clinic['id'] ? 'selected' : '' }}>
                            {{ $clinic['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                    class="bg-primary text-white px-5 py-2 rounded-xl text-sm font-medium shadow-md shadow-primary/20 hover:bg-primary-hover transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">filter_alt</span>
                    {{ __('messages.apply_filter') }}
                </button>
            </div>
        </form>
        <!-- Quick date presets -->
        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-border-light/50 dark:border-border-dark/50">
            <span class="text-[10px] font-bold text-text-sub-light uppercase tracking-widest opacity-60">{{ __('messages.filter') }}:</span>
            <button type="button" onclick="setDateRange('today')" class="text-xs px-3 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-text-sub-light dark:text-text-sub-dark hover:bg-primary/10 hover:text-primary dark:hover:bg-primary/20 dark:hover:text-primary transition-colors font-medium">
                {{ __('messages.today') }}
            </button>
            <button type="button" onclick="setDateRange('week')" class="text-xs px-3 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-text-sub-light dark:text-text-sub-dark hover:bg-primary/10 hover:text-primary dark:hover:bg-primary/20 dark:hover:text-primary transition-colors font-medium">
                {{ __('messages.this_week') }}
            </button>
            <button type="button" onclick="setDateRange('month')" class="text-xs px-3 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-text-sub-light dark:text-text-sub-dark hover:bg-primary/10 hover:text-primary dark:hover:bg-primary/20 dark:hover:text-primary transition-colors font-medium">
                {{ __('messages.this_month') }}
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Revenue -->
        <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl relative overflow-hidden group transition-all duration-300 hover:-translate-y-1 hover:shadow-lg border border-border-light dark:border-border-dark">
            <div class="absolute -right-6 -top-6 size-24 rounded-full bg-emerald-500 opacity-[0.03] pointer-events-none group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-text-sub-light text-[10px] font-bold uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.total_revenue') }}</p>
                    <h3 class="text-3xl font-bold text-text-main-light dark:text-text-main-dark tracking-tight">
                        {{ number_format($stats['total_revenue'], 0) }}
                        <span class="text-sm font-medium text-text-sub-light">{{ __('messages.sar') }}</span>
                    </h3>
                </div>
                <div class="p-3 rounded-xl transition-colors duration-300 shadow-sm bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 group-hover:bg-emerald-100 dark:group-hover:bg-emerald-500/20">
                    <span class="material-symbols-outlined text-2xl">payments</span>
                </div>
            </div>
            <div class="relative z-10 flex items-center gap-2 mt-4">
                <span class="text-text-sub-light text-xs font-medium opacity-80">{{ $stats['total_transactions'] }} {{ __('messages.transactions') }}</span>
            </div>
        </div>

        <!-- Cash Payments -->
        <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl relative overflow-hidden group transition-all duration-300 hover:-translate-y-1 hover:shadow-lg border border-border-light dark:border-border-dark">
            <div class="absolute -right-6 -top-6 size-24 rounded-full bg-slate-500 opacity-[0.03] pointer-events-none group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-text-sub-light text-[10px] font-bold uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.cash_payments') }}</p>
                    <h3 class="text-3xl font-bold text-text-main-light dark:text-text-main-dark tracking-tight">
                        {{ number_format($stats['cash_revenue'], 0) }}
                        <span class="text-sm font-medium text-text-sub-light">{{ __('messages.sar') }}</span>
                    </h3>
                </div>
                <div class="p-3 rounded-xl transition-colors duration-300 shadow-sm bg-slate-50 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400 group-hover:bg-slate-100 dark:group-hover:bg-slate-700/70">
                    <span class="material-symbols-outlined text-2xl">local_atm</span>
                </div>
            </div>
            <div class="relative z-10 flex items-center gap-2 mt-4">
                @if($stats['total_revenue'] > 0)
                    <span class="text-text-sub-light text-xs font-medium opacity-80">{{ round(($stats['cash_revenue'] / $stats['total_revenue']) * 100) }}% {{ __('messages.total_label') }}</span>
                @else
                    <span class="text-text-sub-light text-xs font-medium opacity-80">0% {{ __('messages.total_label') }}</span>
                @endif
            </div>
        </div>

        <!-- Online Payments -->
        <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl relative overflow-hidden group transition-all duration-300 hover:-translate-y-1 hover:shadow-lg border border-border-light dark:border-border-dark">
            <div class="absolute -right-6 -top-6 size-24 rounded-full bg-blue-500 opacity-[0.03] pointer-events-none group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-text-sub-light text-[10px] font-bold uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.online_payments') }}</p>
                    <h3 class="text-3xl font-bold text-text-main-light dark:text-text-main-dark tracking-tight">
                        {{ number_format($stats['online_revenue'], 0) }}
                        <span class="text-sm font-medium text-text-sub-light">{{ __('messages.sar') }}</span>
                    </h3>
                </div>
                <div class="p-3 rounded-xl transition-colors duration-300 shadow-sm bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-500/20">
                    <span class="material-symbols-outlined text-2xl">credit_card</span>
                </div>
            </div>
            <div class="relative z-10 flex items-center gap-2 mt-4">
                @if($stats['total_revenue'] > 0)
                    <span class="text-text-sub-light text-xs font-medium opacity-80">{{ round(($stats['online_revenue'] / $stats['total_revenue']) * 100) }}% {{ __('messages.total_label') }}</span>
                @else
                    <span class="text-text-sub-light text-xs font-medium opacity-80">0% {{ __('messages.total_label') }}</span>
                @endif
            </div>
        </div>

        <!-- Waived (Follow-up) -->
        <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl relative overflow-hidden group transition-all duration-300 hover:-translate-y-1 hover:shadow-lg border border-border-light dark:border-border-dark">
            <div class="absolute -right-6 -top-6 size-24 rounded-full bg-amber-500 opacity-[0.03] pointer-events-none group-hover:scale-150 transition-transform duration-500"></div>
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-text-sub-light text-[10px] font-bold uppercase tracking-widest mb-1.5 opacity-80">{{ __('messages.waived_followup_count') }}</p>
                    <h3 class="text-3xl font-bold text-text-main-light dark:text-text-main-dark tracking-tight">
                        {{ $stats['waived_count'] }}
                    </h3>
                </div>
                <div class="p-3 rounded-xl transition-colors duration-300 shadow-sm bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 group-hover:bg-amber-100 dark:group-hover:bg-amber-500/20">
                    <span class="material-symbols-outlined text-2xl">volunteer_activism</span>
                </div>
            </div>
            <div class="relative z-10 flex items-center gap-2 mt-4">
                <span class="text-text-sub-light text-xs font-medium opacity-80">{{ __('messages.payment_followup_free') }}</span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
        <!-- Line Chart: Daily Revenue Trend -->
        <div class="xl:col-span-2 bg-white dark:bg-surface-dark p-6 rounded-2xl border border-border-light dark:border-border-dark shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-sm font-bold text-text-main-light dark:text-text-main-dark">{{ __('messages.daily_revenue_trend') }}</h3>
                    <p class="text-xs text-text-sub-light mt-0.5">{{ $dateFrom }} - {{ $dateTo }}</p>
                </div>
                <div class="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-500/10">
                    <span class="material-symbols-outlined text-indigo-600 dark:text-indigo-400 text-lg">show_chart</span>
                </div>
            </div>
            <div class="relative" style="height: 300px;">
                <canvas id="revenueLineChart"></canvas>
            </div>
        </div>

        <!-- Doughnut Chart: Payment Breakdown -->
        <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl border border-border-light dark:border-border-dark shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-sm font-bold text-text-main-light dark:text-text-main-dark">{{ __('messages.payment_breakdown') }}</h3>
                    <p class="text-xs text-text-sub-light mt-0.5">{{ $stats['total_transactions'] }} {{ __('messages.transactions') }}</p>
                </div>
                <div class="p-2 rounded-lg bg-violet-50 dark:bg-violet-500/10">
                    <span class="material-symbols-outlined text-violet-600 dark:text-violet-400 text-lg">donut_large</span>
                </div>
            </div>
            <div class="relative flex items-center justify-center" style="height: 220px;">
                <canvas id="paymentDoughnutChart"></canvas>
            </div>
            <!-- Legend -->
            <div class="flex flex-col gap-2 mt-4 pt-4 border-t border-border-light/50 dark:border-border-dark/50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="size-3 rounded-full bg-slate-500"></span>
                        <span class="text-xs text-text-sub-light font-medium">{{ __('messages.cash_payments') }}</span>
                    </div>
                    <span class="text-xs font-bold text-text-main-light dark:text-text-main-dark">{{ number_format($stats['cash_revenue'], 0) }} {{ __('messages.sar') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="size-3 rounded-full bg-blue-500"></span>
                        <span class="text-xs text-text-sub-light font-medium">{{ __('messages.online_payments') }}</span>
                    </div>
                    <span class="text-xs font-bold text-text-main-light dark:text-text-main-dark">{{ number_format($stats['online_revenue'], 0) }} {{ __('messages.sar') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="size-3 rounded-full bg-amber-500"></span>
                        <span class="text-xs text-text-sub-light font-medium">{{ __('messages.waived_followup_count') }}</span>
                    </div>
                    <span class="text-xs font-bold text-text-main-light dark:text-text-main-dark">{{ $stats['waived_count'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions Table -->
    <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-border-light dark:border-border-dark flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-text-main-light dark:text-text-main-dark">{{ __('messages.recent_transactions') }}</h3>
                <p class="text-xs text-text-sub-light mt-0.5">{{ $stats['avg_transaction_value'] > 0 ? number_format($stats['avg_transaction_value'], 0) . ' ' . __('messages.sar') . ' ' . __('messages.avg_per_transaction') : '' }}</p>
            </div>
            <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-500/10">
                <span class="material-symbols-outlined text-emerald-600 dark:text-emerald-400 text-lg">receipt_long</span>
            </div>
        </div>

        @if(count($stats['recent_transactions']) > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-800/50">
                        <th class="text-start px-6 py-3 text-[10px] font-bold text-text-sub-light uppercase tracking-widest">{{ __('messages.date_label') }}</th>
                        <th class="text-start px-6 py-3 text-[10px] font-bold text-text-sub-light uppercase tracking-widest">{{ __('messages.patient_label') }}</th>
                        <th class="text-start px-6 py-3 text-[10px] font-bold text-text-sub-light uppercase tracking-widest">{{ __('messages.doctor_label') }}</th>
                        <th class="text-start px-6 py-3 text-[10px] font-bold text-text-sub-light uppercase tracking-widest">{{ __('messages.clinic') }}</th>
                        <th class="text-start px-6 py-3 text-[10px] font-bold text-text-sub-light uppercase tracking-widest">{{ __('messages.amount') }}</th>
                        <th class="text-start px-6 py-3 text-[10px] font-bold text-text-sub-light uppercase tracking-widest">{{ __('messages.payment_method') }}</th>
                        <th class="text-start px-6 py-3 text-[10px] font-bold text-text-sub-light uppercase tracking-widest">{{ __('messages.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-light/50 dark:divide-border-dark/50">
                    @foreach($stats['recent_transactions'] as $transaction)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-3 text-text-main-light dark:text-text-main-dark font-medium whitespace-nowrap">
                            {{ $transaction['date'] }}
                        </td>
                        <td class="px-6 py-3 text-text-main-light dark:text-text-main-dark whitespace-nowrap">
                            {{ $transaction['patient'] }}
                        </td>
                        <td class="px-6 py-3 text-text-sub-light dark:text-text-sub-dark whitespace-nowrap">
                            {{ $transaction['doctor'] }}
                        </td>
                        <td class="px-6 py-3 text-text-sub-light dark:text-text-sub-dark whitespace-nowrap">
                            {{ $transaction['clinic'] }}
                        </td>
                        <td class="px-6 py-3 text-text-main-light dark:text-text-main-dark font-bold whitespace-nowrap">
                            {{ number_format($transaction['amount'], 0) }} {{ __('messages.sar') }}
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap">
                            @php
                                $paymentBadge = match($transaction['payment_status']) {
                                    'cash' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
                                    'stripe', 'paid' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
                                    'pay_on_arrival' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                };
                                $paymentLabel = match($transaction['payment_status']) {
                                    'cash' => __('messages.payment_cash'),
                                    'stripe', 'paid' => __('messages.payment_online'),
                                    'pay_on_arrival' => __('messages.payment_pay_on_arrival'),
                                    default => $transaction['payment_status'],
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $paymentBadge }}">
                                {{ $paymentLabel }}
                            </span>
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap">
                            @php
                                $statusBadge = match($transaction['status']) {
                                    'confirmed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
                                    'completed' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300',
                                    'arrived' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300',
                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $statusBadge }}">
                                {{ __('messages.' . $transaction['status']) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="px-6 py-16 text-center">
            <span class="material-symbols-outlined text-4xl text-text-sub-light/30 mb-3 block">receipt_long</span>
            <p class="text-text-sub-light text-sm font-medium">{{ __('messages.no_transactions') }}</p>
        </div>
        @endif
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const isDark = document.documentElement.classList.contains('dark');
        const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        const textColor = isDark ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.4)';

        // Daily breakdown data from server
        const dailyBreakdown = @json($stats['daily_breakdown']);
        const dates = Object.keys(dailyBreakdown);
        const totals = dates.map(d => dailyBreakdown[d].total);
        const cashData = dates.map(d => dailyBreakdown[d].cash);
        const onlineData = dates.map(d => dailyBreakdown[d].online);

        // Format dates for display
        const labels = dates.map(d => {
            const parts = d.split('-');
            return parts[2] + '/' + parts[1];
        });

        // Line Chart
        const lineCtx = document.getElementById('revenueLineChart').getContext('2d');
        new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '{{ __("messages.total_revenue") }}',
                        data: totals,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.08)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6,
                    },
                    {
                        label: '{{ __("messages.cash_payments") }}',
                        data: cashData,
                        borderColor: '#64748b',
                        backgroundColor: 'rgba(100, 116, 139, 0.05)',
                        borderWidth: 1.5,
                        fill: false,
                        tension: 0.4,
                        pointRadius: 2,
                        pointBackgroundColor: '#64748b',
                        borderDash: [4, 4],
                    },
                    {
                        label: '{{ __("messages.online_payments") }}',
                        data: onlineData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.05)',
                        borderWidth: 1.5,
                        fill: false,
                        tension: 0.4,
                        pointRadius: 2,
                        pointBackgroundColor: '#3b82f6',
                        borderDash: [4, 4],
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            color: textColor,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#fff',
                        titleColor: isDark ? '#f1f5f9' : '#1e293b',
                        bodyColor: isDark ? '#94a3b8' : '#64748b',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1,
                        cornerRadius: 12,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString() + ' {{ __("messages.sar") }}';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: gridColor, drawBorder: false },
                        ticks: { color: textColor, font: { size: 10 } }
                    },
                    y: {
                        grid: { color: gridColor, drawBorder: false },
                        ticks: {
                            color: textColor,
                            font: { size: 10 },
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        },
                        beginAtZero: true,
                    }
                }
            }
        });

        // Doughnut Chart
        const doughnutCtx = document.getElementById('paymentDoughnutChart').getContext('2d');
        const cashTotal = {{ $stats['cash_revenue'] }};
        const onlineTotal = {{ $stats['online_revenue'] }};
        const waivedTotal = {{ $stats['waived_count'] }};
        const hasDoughnutData = cashTotal > 0 || onlineTotal > 0 || waivedTotal > 0;

        new Chart(doughnutCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    '{{ __("messages.cash_payments") }}',
                    '{{ __("messages.online_payments") }}',
                    '{{ __("messages.waived_followup_count") }}'
                ],
                datasets: [{
                    data: hasDoughnutData ? [cashTotal, onlineTotal, waivedTotal] : [1],
                    backgroundColor: hasDoughnutData
                        ? ['#64748b', '#3b82f6', '#f59e0b']
                        : [isDark ? '#334155' : '#e2e8f0'],
                    borderColor: isDark ? '#1e293b' : '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: hasDoughnutData,
                        backgroundColor: isDark ? '#1e293b' : '#fff',
                        titleColor: isDark ? '#f1f5f9' : '#1e293b',
                        bodyColor: isDark ? '#94a3b8' : '#64748b',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1,
                        cornerRadius: 12,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                if (context.dataIndex === 2) {
                                    return context.label + ': ' + context.parsed;
                                }
                                return context.label + ': ' + context.parsed.toLocaleString() + ' {{ __("messages.sar") }}';
                            }
                        }
                    }
                }
            }
        });
    });

    // Quick date preset helper
    function setDateRange(preset) {
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');
        const today = new Date();

        switch (preset) {
            case 'today':
                dateFrom.value = formatDate(today);
                dateTo.value = formatDate(today);
                break;
            case 'week':
                const weekStart = new Date(today);
                weekStart.setDate(today.getDate() - today.getDay());
                dateFrom.value = formatDate(weekStart);
                dateTo.value = formatDate(today);
                break;
            case 'month':
                const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                dateFrom.value = formatDate(monthStart);
                dateTo.value = formatDate(today);
                break;
        }

        // Auto-submit the form
        dateFrom.closest('form').submit();
    }

    function formatDate(date) {
        return date.getFullYear() + '-' +
            String(date.getMonth() + 1).padStart(2, '0') + '-' +
            String(date.getDate()).padStart(2, '0');
    }
</script>
@endsection
