@extends('layouts.app')

@section('content')
@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-[#111418] dark:text-white leading-tight">{{ __('messages.patient_records') }}</h1>
                <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ __('messages.manage_patient_files') }}</p>
            </div>
             <a href="{{ route('patients.create') }}" class="bg-primary hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-colors shadow-sm">
                <span class="material-symbols-outlined">person_add</span>
                {{ __('messages.new_patient_admission') }}
            </a>
        </div>

        <!-- Search Form -->
        <div class="bg-white dark:bg-[#1a222e] p-4 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm">
            <form action="{{ route('patients.index') }}" method="GET" class="flex gap-4">
                <div class="relative flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                    <input type="text" name="search" value="{{ $query ?? '' }}"
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#111821] text-[#111418] dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/50"
                        placeholder="{{ __('messages.search_patient_placeholder') }}">
                </div>
                <button type="submit" class="bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-[#111418] dark:text-white font-medium px-4 py-2 rounded-lg transition-colors">
                    {{ __('messages.search_button') }}
                </button>
            </form>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-[#1a222e] p-6 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm flex items-center">
                 <div class="p-3 rounded-xl bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 mr-4">
                    <span class="material-symbols-outlined">group</span>
                </div>
                <div>
                    <p class="text-sm text-[#637388] dark:text-[#9ca3af] font-medium">{{ __('messages.total_patients') }}</p>
                    <h3 class="text-2xl font-bold text-[#111418] dark:text-white">12,345</h3>
                </div>
            </div>
             <div class="bg-white dark:bg-[#1a222e] p-6 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm flex items-center">
                 <div class="p-3 rounded-xl bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 mr-4">
                    <span class="material-symbols-outlined">trending_up</span>
                </div>
                <div>
                    <p class="text-sm text-[#637388] dark:text-[#9ca3af] font-medium">{{ __('messages.new_this_month') }}</p>
                    <h3 class="text-2xl font-bold text-[#111418] dark:text-white">+452</h3>
                </div>
            </div>
             <div class="bg-white dark:bg-[#1a222e] p-6 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm flex items-center">
                 <div class="p-3 rounded-xl bg-alert-amber/20 text-alert-amber mr-4">
                    <span class="material-symbols-outlined">pending_actions</span>
                </div>
                <div>
                    <p class="text-sm text-[#637388] dark:text-[#9ca3af] font-medium">{{ __('messages.pending_insurance') }}</p>
                    <h3 class="text-2xl font-bold text-[#111418] dark:text-white">28</h3>
                </div>
            </div>
        </div>

        <!-- Patients List -->
         <div class="bg-white dark:bg-[#1a222e] border border-[#e5e7eb] dark:border-[#2d3748] rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-[#e5e7eb] dark:border-[#2d3748] bg-background-light dark:bg-[#111821] flex items-center justify-between">
                <h3 class="font-bold text-[#111418] dark:text-white">{{ __('messages.recent_admissions') }}</h3>
                <a href="#" class="text-sm text-primary hover:underline font-medium">{{ __('messages.view_directory') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-white dark:bg-[#1a222e]">
                        <tr>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.patient_name') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.phone') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.email') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.registered') }}</th>
                             <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2d3748]">
                         @foreach($patients as $patient)
                        <tr class="hover:bg-gray-50 dark:hover:bg-[#202a37] transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-[#111418] dark:text-white">
                                {{ $patient['name'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-[#637388] dark:text-[#9ca3af]">
                                {{ $patient['phone'] }}
                            </td>
                             <td class="px-6 py-4 whitespace-nowrap text-sm text-[#637388] dark:text-[#9ca3af]">
                                {{ $patient['email'] ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-[#637388] dark:text-[#9ca3af]">
                                {{ $patient['created_at'] }}
                            </td>
                             <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('patients.show', $patient['id']) }}" class="text-[#637388] dark:text-[#9ca3af] hover:text-primary transition-colors">
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
