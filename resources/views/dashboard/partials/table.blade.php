<div class="flex items-center justify-between">
    <h3 class="text-lg font-bold text-[#111418] dark:text-white">{{ __('messages.clinics_queues') }}</h3>
    <div class="flex gap-2">
        <button class="px-3 py-1.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1a222e] text-sm font-medium text-[#111418] dark:text-white hover:bg-gray-50 dark:hover:bg-[#2d3748]">{{ __('messages.filter') }}</button>
        <button class="px-3 py-1.5 rounded-lg bg-primary text-white text-sm font-medium hover:bg-blue-600 shadow-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">download</span> {{ __('messages.export_data') }}
        </button>
    </div>
</div>
<div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-background-light dark:bg-[#111821] border-b border-[#e5e7eb] dark:border-[#2d3748]">
                     <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.clinic_name') }}</th>
                    <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.doctors_on_duty') }}</th>
                    <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.patients_waiting') }}</th>
                    <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.avg_wait') }}</th>
                    <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.queue_status') }}</th>
                     <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-[#9ca3af]">{{ __('messages.alerts') }}</th>
                    <th class="px-6 py-4 w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2d3748]">
                @forelse($clinics as $clinic)
                    @php
                        $statusColor = match($clinic['status']) {
                            __('messages.running') => 'green',
                            'Running' => 'green',
                            __('messages.high_load') => 'amber',
                            'High Load' => 'amber',
                            __('messages.paused') => 'red',
                            'Paused' => 'red',
                            default => 'gray'
                        };
                        $alertColor = match(true) {
                            str_contains($clinic['alerts'] ?? '', 'Doctor Unavailable') => 'red',
                            str_contains($clinic['alerts'] ?? '', __('messages.doctor_unavailable')) => 'red',
                            str_contains($clinic['alerts'] ?? '', 'Staff Break') => 'amber',
                            str_contains($clinic['alerts'] ?? '', __('messages.staff_break')) => 'amber',
                            default => 'gray'
                        };
                        $alertIcon = match(true) {
                            str_contains($clinic['alerts'] ?? '', 'Doctor Unavailable') => 'person_off',
                            str_contains($clinic['alerts'] ?? '', __('messages.doctor_unavailable')) => 'person_off',
                            str_contains($clinic['alerts'] ?? '', 'Staff Break') => 'schedule',
                            str_contains($clinic['alerts'] ?? '', __('messages.staff_break')) => 'schedule',
                            default => null
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-[#202a37] transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full bg-{{ $clinic['icon_color'] }}-100 dark:bg-{{ $clinic['icon_color'] }}-900/40 flex items-center justify-center text-{{ $clinic['icon_color'] }}-600">
                                    <span class="material-symbols-outlined text-sm">{{ $clinic['icon'] }}</span>
                                </div>
                                <span class="font-medium text-[#111418] dark:text-white">{{ $clinic['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[#637388] dark:text-[#9ca3af]">
                            <div class="flex -space-x-2">
                                {{-- Generate avatars based on doctors_on_duty count --}}
                                @for ($i = 0; $i < min($clinic['doctors_on_duty'], 4); $i++)
                                <img class="size-7 rounded-full border-2 border-white dark:border-[#1a222e]" src="https://ui-avatars.com/api/?name=Doc+{{ $i + 1 }}&background=random&color=fff" alt="Doctor avatar">
                                @endfor
                                @if($clinic['doctors_on_duty'] > 0)
                                <div class="size-7 rounded-full border-2 border-white dark:border-[#1a222e] bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-[10px] font-bold text-gray-500 dark:text-gray-300">{{ $clinic['doctors_on_duty'] }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-semibold text-[#111418] dark:text-white">{{ $clinic['patients_waiting'] }}</td>
                        <td class="px-6 py-4 text-sm text-[#637388] dark:text-[#9ca3af]">{{ $clinic['avg_wait'] }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 dark:bg-{{ $statusColor }}-900/30 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-400 border border-{{ $statusColor }}-200 dark:border-{{ $statusColor }}-800">
                                <span class="size-1.5 rounded-full bg-{{ $statusColor }}-500"></span>
                                {{ $clinic['status'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[#637388] dark:text-[#9ca3af]">
                            @if($clinic['alerts'])
                                <span class="flex items-center gap-1 text-xs text-{{ $alertColor }}-600 dark:text-{{ $alertColor }}-400 font-medium">
                                    <span class="material-symbols-outlined text-sm">{{ $alertIcon }}</span>
                                    {{ $clinic['alerts'] }}
                                </span>
                            @else
                                <span class="text-xs text-gray-400">{{ __('messages.none') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button class="text-[#637388] dark:text-[#9ca3af] hover:text-primary transition-colors">
                                <span class="material-symbols-outlined">more_vert</span>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <span class="material-symbols-outlined text-4xl text-gray-300 dark:text-gray-600 mb-2">local_hospital</span>
                            <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ __('messages.no_data') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
     <div class="px-6 py-4 border-t border-[#e5e7eb] dark:border-[#2d3748] flex items-center justify-between">
        <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ __('messages.showing_clinics', ['count' => count($clinics)]) }}</p>
        <div class="flex gap-2">
            <button class="size-8 flex items-center justify-center rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-[#637388] dark:text-[#9ca3af] hover:bg-gray-50 dark:hover:bg-[#202a37]">
                <span class="material-symbols-outlined text-sm">chevron_left</span>
            </button>
            <button class="size-8 flex items-center justify-center rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-[#637388] dark:text-[#9ca3af] hover:bg-gray-50 dark:hover:bg-[#202a37]">
                <span class="material-symbols-outlined text-sm">chevron_right</span>
            </button>
        </div>
    </div>
</div>
