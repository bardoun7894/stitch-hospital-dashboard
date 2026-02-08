<div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-bold text-text-main-light dark:text-white tracking-tight">{{ __('messages.clinics_queues') }}</h3>
    <div class="flex gap-2">
        <button class="px-3 py-1.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-surface-dark text-sm font-medium text-text-main-light dark:text-white hover:bg-background-light dark:hover:bg-white/5 transition-colors shadow-sm card-hover">{{ __('messages.filter') }}</button>
        <button class="px-3 py-1.5 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-hover shadow-sm flex items-center gap-2 transition-all hover:shadow-md active:scale-95">
            <span class="material-symbols-outlined text-sm">download</span> {{ __('messages.export_data') }}
        </button>
    </div>
</div>
<div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-background-light dark:bg-white/5 border-b border-border-light dark:border-border-dark">
                     <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.clinic_name') }}</th>
                    <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.doctors_on_duty') }}</th>
                    <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.patients_waiting') }}</th>
                    <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.avg_wait') }}</th>
                    <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.queue_status') }}</th>
                     <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.alerts') }}</th>
                    <th class="px-6 py-4 w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-light dark:divide-border-dark">
                @forelse($clinics as $clinic)
                    @php
                        $statusColor = match($clinic['status']) {
                            __('messages.running') => 'emerald',
                            'Running' => 'emerald',
                            __('messages.high_load') => 'amber',
                            'High Load' => 'amber',
                            __('messages.paused') => 'rose',
                            'Paused' => 'rose',
                            default => 'slate'
                        };
                        $alertColor = match(true) {
                            str_contains($clinic['alerts'] ?? '', 'Doctor Unavailable') => 'rose',
                            str_contains($clinic['alerts'] ?? '', __('messages.doctor_unavailable')) => 'rose',
                            str_contains($clinic['alerts'] ?? '', 'Staff Break') => 'amber',
                            str_contains($clinic['alerts'] ?? '', __('messages.staff_break')) => 'amber',
                            default => 'slate'
                        };
                        $alertIcon = match(true) {
                            str_contains($clinic['alerts'] ?? '', 'Doctor Unavailable') => 'person_off',
                            str_contains($clinic['alerts'] ?? '', __('messages.doctor_unavailable')) => 'person_off',
                            str_contains($clinic['alerts'] ?? '', 'Staff Break') => 'schedule',
                            str_contains($clinic['alerts'] ?? '', __('messages.staff_break')) => 'schedule',
                            default => null
                        };
                    @endphp
                    <tr class="hover:bg-background-light dark:hover:bg-white/5 transition-colors group">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-xl bg-{{ $clinic['icon_color'] }}-50 dark:bg-{{ $clinic['icon_color'] }}-500/10 flex items-center justify-center text-{{ $clinic['icon_color'] }}-600 dark:text-{{ $clinic['icon_color'] }}-400 group-hover:scale-110 transition-transform">
                                    <span class="material-symbols-outlined text-lg">{{ $clinic['icon'] }}</span>
                                </div>
                                <span class="font-semibold text-text-main-light dark:text-white">{{ $clinic['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-text-sub-light dark:text-text-sub-dark">
                            <div class="flex -space-x-2">
                                {{-- Generate avatars based on doctors_on_duty count --}}
                                @for ($i = 0; $i < min($clinic['doctors_on_duty'], 4); $i++)
                                <img class="size-8 rounded-full border-2 border-white dark:border-surface-dark ring-2 ring-transparent group-hover:ring-{{ $clinic['icon_color'] }}-100 dark:group-hover:ring-{{ $clinic['icon_color'] }}-900/20 transition-all" src="https://ui-avatars.com/api/?name=Doc+{{ $i + 1 }}&background=random&color=fff" alt="Doctor avatar">
                                @endfor
                                @if($clinic['doctors_on_duty'] > 0)
                                <div class="size-8 rounded-full border-2 border-white dark:border-surface-dark bg-background-light dark:bg-slate-700 flex items-center justify-center text-[10px] font-bold text-text-sub-light dark:text-slate-300 shadow-sm">+{{ $clinic['doctors_on_duty'] }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-text-main-light dark:text-white">{{ $clinic['patients_waiting'] }}</td>
                        <td class="px-6 py-4 text-sm text-text-sub-light dark:text-text-sub-dark font-medium">{{ $clinic['avg_wait'] }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-{{ $statusColor }}-50 dark:bg-{{ $statusColor }}-500/10 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-400 border border-{{ $statusColor }}-100 dark:border-{{ $statusColor }}-500/20">
                                <span class="relative flex h-1.5 w-1.5">
                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-{{ $statusColor }}-400 opacity-75"></span>
                                  <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-{{ $statusColor }}-500"></span>
                                </span>
                                {{ $clinic['status'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-text-sub-light dark:text-text-sub-dark">
                            @if($clinic['alerts'])
                                <span class="flex items-center gap-1.5 text-xs text-{{ $alertColor }}-600 dark:text-{{ $alertColor }}-400 font-medium bg-{{ $alertColor }}-50 dark:bg-{{ $alertColor }}-500/10 px-2 py-1 rounded-md w-fit">
                                    <span class="material-symbols-outlined text-sm">{{ $alertIcon }}</span>
                                    {{ $clinic['alerts'] }}
                                </span>
                            @else
                                <span class="text-xs text-text-sub-light/50">{{ __('messages.none') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button class="text-text-sub-light dark:text-text-sub-dark hover:text-primary transition-colors p-1 rounded-md hover:bg-background-light dark:hover:bg-white/5">
                                <span class="material-symbols-outlined">more_vert</span>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <span class="material-symbols-outlined text-4xl text-border-light dark:text-border-dark mb-2">local_hospital</span>
                                <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.no_data') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
     <div class="px-6 py-4 border-t border-border-light dark:border-border-dark flex items-center justify-between bg-background-light/30 dark:bg-white/5">
        <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.showing_clinics', ['count' => count($clinics)]) }}</p>
        <div class="flex gap-2">
            <button class="size-9 flex items-center justify-center rounded-lg border border-border-light dark:border-border-dark text-text-sub-light dark:text-text-sub-dark hover:bg-white dark:hover:bg-white/10 hover:text-primary transition-all shadow-sm">
                <span class="material-symbols-outlined text-sm">chevron_left</span>
            </button>
            <button class="size-9 flex items-center justify-center rounded-lg border border-border-light dark:border-border-dark text-text-sub-light dark:text-text-sub-dark hover:bg-white dark:hover:bg-white/10 hover:text-primary transition-all shadow-sm">
                <span class="material-symbols-outlined text-sm">chevron_right</span>
            </button>
        </div>
    </div>
</div>
