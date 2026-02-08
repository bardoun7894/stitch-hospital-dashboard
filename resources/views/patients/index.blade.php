@extends('layouts.app')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight mb-1">{{ __('messages.patient_records') }}</h1>
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.manage_patient_files') }}</p>
            </div>
             <a href="{{ route('patients.create') }}" class="bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg flex items-center justify-center gap-2 transition-all shadow-md shadow-primary/20 hover:shadow-lg active:scale-95">
                <span class="material-symbols-outlined text-sm">person_add</span>
                {{ __('messages.new_patient_admission') }}
            </a>
        </div>

        <!-- Search Form -->
        <div class="bg-white dark:bg-surface-dark p-4 rounded-2xl border border-border-light dark:border-border-dark shadow-sm">
            <form action="{{ route('patients.index') }}" method="GET" class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-sub-light/50">search</span>
                    <input type="text" name="search" value="{{ $query ?? '' }}"
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-surface-dark text-text-main-light dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder-text-sub-light/50"
                        placeholder="{{ __('messages.search_patient_placeholder') }}">
                </div>
                <button type="submit" class="bg-background-light dark:bg-white/5 hover:bg-border-light dark:hover:bg-white/10 text-text-main-light dark:text-white font-medium px-6 py-2 rounded-lg transition-colors border border-border-light dark:border-border-dark">
                    {{ __('messages.search_button') }}
                </button>
            </form>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl border border-border-light dark:border-border-dark shadow-sm flex items-center card-hover group">
                 <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 mr-4 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-2xl">group</span>
                </div>
                <div>
                    <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-0.5">{{ __('messages.total_patients') }}</p>
                    <h3 class="text-2xl font-bold text-text-main-light dark:text-white">12,345</h3>
                </div>
            </div>
             <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl border border-border-light dark:border-border-dark shadow-sm flex items-center card-hover group">
                 <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 mr-4 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-2xl">trending_up</span>
                </div>
                <div>
                    <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-0.5">{{ __('messages.new_this_month') }}</p>
                    <h3 class="text-2xl font-bold text-text-main-light dark:text-white">+452</h3>
                </div>
            </div>
             <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl border border-border-light dark:border-border-dark shadow-sm flex items-center card-hover group">
                 <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 mr-4 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-2xl">pending_actions</span>
                </div>
                <div>
                    <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-0.5">{{ __('messages.pending_insurance') }}</p>
                    <h3 class="text-2xl font-bold text-text-main-light dark:text-white">28</h3>
                </div>
            </div>
        </div>

        <!-- Patients List -->
         <div class="bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-border-light dark:border-border-dark bg-background-light/30 dark:bg-white/5 flex items-center justify-between">
                <h3 class="font-bold text-text-main-light dark:text-white">{{ __('messages.recent_admissions') }}</h3>
                <a href="#" class="text-sm text-primary hover:text-primary-hover hover:underline font-semibold transition-colors">{{ __('messages.view_directory') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background-light dark:bg-white/5 border-b border-border-light dark:border-border-dark">
                            <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.patient_name') }}</th>
                            <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.phone') }}</th>
                            <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">National ID</th>
                            <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.email') }}</th>
                            <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.registered') }}</th>
                             <th class="px-6 py-4 text-right text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-border-dark">
                         @foreach($patients as $patient)
                        <tr class="hover:bg-background-light dark:hover:bg-white/5 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs uppercase">
                                        {{ substr($patient['name'], 0, 2) }}
                                    </div>
                                    <span class="font-semibold text-text-main-light dark:text-white">{{ $patient['name'] }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-sub-light dark:text-text-sub-dark font-medium">
                                {{ $patient['phone'] }}
                            </td>
                             <td class="px-6 py-4 whitespace-nowrap text-sm text-text-sub-light dark:text-text-sub-dark">
                                <span class="font-mono bg-background-light dark:bg-white/5 px-2 py-0.5 rounded text-xs">{{ $patient['national_id'] ?? '-' }}</span>
                            </td>
                             <td class="px-6 py-4 whitespace-nowrap text-sm text-text-sub-light dark:text-text-sub-dark">
                                {{ $patient['email'] ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-sub-light dark:text-text-sub-dark">
                                {{ $patient['created_at'] }}
                            </td>
                             <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('patients.show', $patient['id']) }}" class="text-text-sub-light dark:text-text-sub-dark hover:text-primary transition-colors p-2 hover:bg-background-light dark:hover:bg-white/10 rounded-lg">
                                    <span class="material-symbols-outlined">chevron_right</span>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
