@php
    $clinicsJson = json_encode($clinics);
@endphp

<div x-data="clinicTable({{ $clinicsJson }})" class="flex flex-col gap-4">
    {{-- Header with Filters --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h3 class="text-lg font-bold text-text-main-light dark:text-white tracking-tight">{{ __('messages.clinics_queues') }}</h3>
        <div class="flex flex-wrap items-center gap-2">
            {{-- Hospital Status Filter --}}
            <div class="flex items-center bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-lg overflow-hidden shadow-sm">
                <button @click="hospitalFilter = 'active'" class="px-3 py-1.5 text-xs font-bold transition-colors"
                        :class="hospitalFilter === 'active' ? 'bg-primary text-white' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5'">
                    {{ __('messages.active') }}
                </button>
                <button @click="hospitalFilter = 'all'" class="px-3 py-1.5 text-xs font-bold transition-colors border-s border-border-light dark:border-border-dark"
                        :class="hospitalFilter === 'all' ? 'bg-primary text-white' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5'">
                    {{ __('messages.all') }}
                </button>
            </div>

            {{-- Queue Status Filter --}}
            <div class="flex items-center bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-lg overflow-hidden shadow-sm">
                <button @click="queueFilter = 'all'" class="px-3 py-1.5 text-xs font-bold transition-colors"
                        :class="queueFilter === 'all' ? 'bg-primary text-white' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5'">
                    {{ __('messages.all') }}
                </button>
                <button @click="queueFilter = 'running'" class="px-3 py-1.5 text-xs font-bold transition-colors border-s border-border-light dark:border-border-dark"
                        :class="queueFilter === 'running' ? 'bg-emerald-600 text-white' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5'">
                    {{ __('messages.running') }}
                </button>
                <button @click="queueFilter = 'high_load'" class="px-3 py-1.5 text-xs font-bold transition-colors border-s border-border-light dark:border-border-dark"
                        :class="queueFilter === 'high_load' ? 'bg-amber-600 text-white' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5'">
                    {{ __('messages.high_load') }}
                </button>
                <button @click="queueFilter = 'paused'" class="px-3 py-1.5 text-xs font-bold transition-colors border-s border-border-light dark:border-border-dark"
                        :class="queueFilter === 'paused' ? 'bg-rose-600 text-white' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5'">
                    {{ __('messages.paused') }}
                </button>
            </div>

            <button class="px-3 py-1.5 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-hover shadow-sm flex items-center gap-2 transition-all hover:shadow-md active:scale-95">
                <span class="material-symbols-outlined text-sm">download</span> {{ __('messages.export_data') }}
            </button>
        </div>
    </div>

    {{-- Filter Summary --}}
    <div class="flex items-center gap-2 text-xs text-text-sub-light dark:text-text-sub-dark" x-show="hospitalFilter !== 'all' || queueFilter !== 'all'" style="display:none">
        <span class="material-symbols-outlined text-sm">filter_list</span>
        <span>
            {{ __('messages.filter') }}:
            <template x-if="hospitalFilter === 'active'">
                <span class="font-bold text-primary">{{ __('messages.active') }} {{ __('messages.hospitals') }}</span>
            </template>
            <template x-if="queueFilter !== 'all'">
                <span class="font-bold text-primary" x-text="'• ' + queueFilter"></span>
            </template>
        </span>
        <button @click="hospitalFilter = 'all'; queueFilter = 'all'" class="ms-1 text-primary hover:underline font-bold">{{ __('messages.clear') }}</button>
    </div>

    {{-- Table --}}
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
                    <template x-for="clinic in filtered" :key="clinic.id">
                        <tr class="hover:bg-background-light dark:hover:bg-white/5 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform"
                                         :class="'bg-' + clinic.icon_color + '-50 dark:bg-' + clinic.icon_color + '-500/10 text-' + clinic.icon_color + '-600 dark:text-' + clinic.icon_color + '-400'">
                                        <span class="material-symbols-outlined text-lg" x-text="clinic.icon"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="font-semibold text-text-main-light dark:text-white block" x-text="clinic.name"></span>
                                        <span class="text-[10px] text-text-sub-light dark:text-text-sub-dark" x-show="clinic.hospital_name" x-text="clinic.hospital_name"></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-text-sub-light dark:text-text-sub-dark">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">stethoscope</span>
                                    <span x-text="clinic.doctors_on_duty"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-text-main-light dark:text-white" x-text="clinic.patients_waiting"></td>
                            <td class="px-6 py-4 text-sm text-text-sub-light dark:text-text-sub-dark font-medium" x-text="clinic.avg_wait"></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                                      :class="statusClass(clinic.status)">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="statusDotClass(clinic.status)"></span>
                                        <span class="relative inline-flex rounded-full h-1.5 w-1.5" :class="statusDotClass(clinic.status)"></span>
                                    </span>
                                    <span x-text="clinic.status"></span>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <template x-if="clinic.alerts">
                                    <span class="flex items-center gap-1.5 text-xs font-medium px-2 py-1 rounded-md w-fit"
                                          :class="alertClass(clinic.alerts)">
                                        <span class="material-symbols-outlined text-sm" x-text="alertIcon(clinic.alerts)"></span>
                                        <span x-text="clinic.alerts"></span>
                                    </span>
                                </template>
                                <template x-if="!clinic.alerts">
                                    <span class="text-xs text-text-sub-light/50">{{ __('messages.none') }}</span>
                                </template>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button class="text-text-sub-light dark:text-text-sub-dark hover:text-primary transition-colors p-1 rounded-md hover:bg-background-light dark:hover:bg-white/5">
                                    <span class="material-symbols-outlined">more_vert</span>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filtered.length === 0" style="display:none">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <span class="material-symbols-outlined text-4xl text-border-light dark:text-border-dark mb-2">local_hospital</span>
                                <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.no_data') }}</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-border-light dark:border-border-dark flex items-center justify-between bg-background-light/30 dark:bg-white/5">
            <p class="text-sm text-text-sub-light dark:text-text-sub-dark">
                {{ __('messages.showing_clinics', ['count' => '']) }}<span x-text="filtered.length"></span>
                <span class="text-text-sub-light/50" x-show="filtered.length !== allClinics.length" style="display:none" x-text="' / ' + allClinics.length"></span>
            </p>
        </div>
    </div>
</div>

<script>
function clinicTable(clinics) {
    const runningLabel = @json(__('messages.running'));
    const highLoadLabel = @json(__('messages.high_load'));
    const pausedLabel = @json(__('messages.paused'));

    return {
        allClinics: clinics,
        hospitalFilter: 'active',
        queueFilter: 'all',

        get filtered() {
            return this.allClinics.filter(c => {
                // Hospital status filter
                if (this.hospitalFilter === 'active') {
                    if ((c.hospital_status || 'active') !== 'active') return false;
                    if ((c.clinic_status || 'active') !== 'active') return false;
                }
                // Queue status filter
                if (this.queueFilter === 'all') return true;
                if (this.queueFilter === 'running') return c.status === runningLabel;
                if (this.queueFilter === 'high_load') return c.status === highLoadLabel;
                if (this.queueFilter === 'paused') return c.status === pausedLabel;
                return true;
            });
        },

        statusClass(status) {
            if (status === runningLabel) return 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20';
            if (status === highLoadLabel) return 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-100 dark:border-amber-500/20';
            if (status === pausedLabel) return 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-100 dark:border-rose-500/20';
            return 'bg-slate-50 dark:bg-slate-500/10 text-slate-700 dark:text-slate-400 border border-slate-100 dark:border-slate-500/20';
        },

        statusDotClass(status) {
            if (status === runningLabel) return 'bg-emerald-500';
            if (status === highLoadLabel) return 'bg-amber-500';
            if (status === pausedLabel) return 'bg-rose-500';
            return 'bg-slate-400';
        },

        alertClass(alert) {
            if (!alert) return '';
            return 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10';
        },

        alertIcon(alert) {
            if (!alert) return '';
            return 'warning';
        }
    }
}
</script>
