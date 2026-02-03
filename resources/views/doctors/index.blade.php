@extends('layouts.app')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-[#111418] dark:text-white leading-tight">{{ __('messages.medical_staff') }}</h1>
                <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ __('messages.doctors_directory') }}</p>
            </div>
            <div class="flex space-x-3">
                 <div class="relative">
                     <span class="absolute left-3 top-2.5 material-symbols-outlined text-[#9ca3af] text-xl">search</span>
                     <input type="text" placeholder="{{ __('messages.search_doctors') }}" class="pl-10 pr-4 py-2 bg-white dark:bg-[#1a222e] border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary text-[#111418] dark:text-white placeholder-[#9ca3af]">
                </div>
                <button class="bg-primary hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-colors shadow-sm">
                    <span class="material-symbols-outlined">add</span>
                    {{ __('messages.add_doctor') }}
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1a222e] border border-[#e5e7eb] dark:border-[#2d3748] rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-background-light dark:bg-[#111821] border-b border-[#e5e7eb] dark:border-[#2d3748]">
                        <tr>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.name') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.specialty') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.clinic') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.status') }}</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.contact') }}</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2d3748]">
                        @foreach($doctors as $index => $doctor)
                        @php
                            $statusColor = match($doctor['status']) {
                                'On Duty' => 'green',
                                __('messages.on_duty') => 'green',
                                'On Call' => 'amber',
                                __('messages.on_call') => 'amber',
                                'Off Duty' => 'gray',
                                __('messages.off_duty') => 'gray',
                                default => 'gray'
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-[#202a37] transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <img class="size-10 rounded-full border border-[#e5e7eb] dark:border-[#2d3748]" src="https://ui-avatars.com/api/?name={{ urlencode($doctor['name']) }}&background=random" alt="">
                                    <div>
                                        <div class="text-sm font-semibold text-[#111418] dark:text-white">{{ $doctor['name'] }}</div>
                                        <div class="text-xs text-[#637388] dark:text-[#9ca3af]">{{ __('messages.id_prefix') }}DOC-00{{ $index + 1 }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-[#111418] dark:text-white font-medium">
                                {{ $doctor['specialty'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 inline-flex text-xs font-medium rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border border-blue-100 dark:border-blue-800">
                                    {{ $doctor['clinic'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 dark:bg-{{ $statusColor }}-900/30 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-400 border border-{{ $statusColor }}-200 dark:border-{{ $statusColor }}-800">
                                    <span class="size-1.5 rounded-full bg-{{ $statusColor }}-500"></span>
                                    {{ $doctor['status'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-[#637388] dark:text-[#9ca3af]">
                                <div class="flex items-center gap-2">
                                     <span class="material-symbols-outlined text-sm">call</span>
                                     +1 (555) 123-456{{ $index }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <a href="{{ route('doctors.schedule', $doctor['id']) }}" class="text-[#637388] dark:text-[#9ca3af] hover:text-primary transition-colors" title="{{ __('messages.manage_schedule') }}">
                                    <span class="material-symbols-outlined">calendar_month</span>
                                </a>
                                <button class="text-[#637388] dark:text-[#9ca3af] hover:text-primary transition-colors" title="{{ __('messages.edit_profile') }}">
                                    <span class="material-symbols-outlined">edit</span>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
