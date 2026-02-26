@extends('layouts.app')

@php
    use App\Http\Middleware\RoleMiddleware;
    $isSuperAdmin = RoleMiddleware::hasRole('super_admin');
@endphp

@section('content')
<div class="max-w-3xl mx-auto" x-data="hospitalWizard()">
    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('hospital.index') }}" class="p-2 rounded-lg hover:bg-background-light dark:hover:bg-white/5 transition-colors">
            <span class="material-symbols-outlined text-text-sub-light dark:text-text-sub-dark">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-text-main-light dark:text-white">{{ __('messages.add_hospital') }}</h1>
        </div>
    </div>

    {{-- Pending Notice (non-super_admin) --}}
    @if(!$isSuperAdmin)
    <div class="mb-4 px-4 py-3 bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 rounded-2xl text-sm border border-amber-100 dark:border-amber-900/30 flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">info</span>
        {{ __('messages.hospital_submit_note') }}
    </div>
    @endif

    {{-- Alerts --}}
    @if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-2xl text-sm border border-red-100 dark:border-red-900/30 flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">error</span> {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-2xl text-sm border border-red-100 dark:border-red-900/30">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Step Indicator --}}
    <div class="mb-6 flex items-center gap-0">
        <template x-for="(s, i) in steps" :key="i">
            <div class="flex items-center flex-1">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="size-8 rounded-full flex items-center justify-center shrink-0 text-sm font-bold transition-colors"
                         :class="step > i + 1 ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600' : (step === i + 1 ? 'bg-primary text-white' : 'bg-background-light dark:bg-white/5 text-text-sub-light')">
                        <span x-show="step > i + 1" class="material-symbols-outlined text-lg">check</span>
                        <span x-show="step <= i + 1" x-text="i + 1"></span>
                    </div>
                    <span class="text-sm font-medium truncate"
                          :class="step === i + 1 ? 'text-text-main-light dark:text-white' : 'text-text-sub-light dark:text-text-sub-dark'"
                          x-text="s"></span>
                </div>
                <div x-show="i < steps.length - 1" class="flex-1 h-0.5 mx-3"
                     :class="step > i + 1 ? 'bg-emerald-300 dark:bg-emerald-700' : 'bg-border-light dark:bg-border-dark'"></div>
            </div>
        </template>
    </div>

    {{-- Form --}}
    <form action="{{ route('hospital.store') }}" method="POST" @submit="prepareSubmit" x-ref="mainForm"
          class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-6">
        @csrf

        {{-- Hidden fields for clinics & doctors JSON --}}
        <input type="hidden" name="clinics_json" :value="JSON.stringify(clinics)">
        <input type="hidden" name="doctors_json" :value="JSON.stringify(doctors)">

        {{-- ═══════ STEP 1: Hospital Info ═══════ --}}
        <div x-show="step === 1" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.name') }} *</label>
                    <input type="text" name="name" x-model="hospital.name" required class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.hospital_name_ar') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.name_en') }}</label>
                    <input type="text" name="name_en" x-model="hospital.name_en" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.hospital_name_en') }}">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.address') }}</label>
                <input type="text" name="address" x-model="hospital.address" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.address_placeholder') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.phone') }}</label>
                <input type="text" name="phone" x-model="hospital.phone" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="+966-11-0000000" dir="ltr">
            </div>

            <div class="border-t border-border-light dark:border-border-dark pt-5">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-3">{{ __('messages.location') }}</h3>
                <x-map-picker :latitude="old('latitude', '')" :longitude="old('longitude', '')" />
            </div>
        </div>

        {{-- ═══════ STEP 2: Clinics ═══════ --}}
        <div x-show="step === 2" style="display:none" class="space-y-5">
            <div class="flex items-center justify-between">
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark">
                    {{ __('messages.add_first_clinic') }}
                </p>
            </div>

            {{-- Clinics List --}}
            <template x-for="(clinic, ci) in clinics" :key="ci">
                <div class="bg-background-light dark:bg-white/[0.03] rounded-xl border border-border-light dark:border-border-dark p-4">
                    <div class="flex items-start gap-3">
                        <div class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 shrink-0">
                            <span class="material-symbols-outlined">emergency_home</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-text-main-light dark:text-white truncate" x-text="clinic.name || '{{ __('messages.clinic_name') }}'"></p>
                            <p class="text-xs text-text-sub-light dark:text-text-sub-dark" x-text="clinic.specialty || ''"></p>
                            {{-- Show doctor count for this clinic --}}
                            <p class="text-xs text-text-sub-light dark:text-text-sub-dark mt-1" x-show="getDoctorsForClinic(ci).length > 0">
                                <span class="material-symbols-outlined text-sm align-middle">stethoscope</span>
                                <span x-text="getDoctorsForClinic(ci).length + ' {{ __('messages.total_doctors') }}'"></span>
                            </p>
                        </div>
                        <button type="button" @click="removeClinic(ci)" class="p-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 text-red-500 transition-colors">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                    </div>
                </div>
            </template>

            {{-- Add Clinic Inline Form --}}
            <div x-show="showClinicForm" style="display:none" class="bg-white dark:bg-surface-dark rounded-xl border-2 border-dashed border-primary/30 p-4 space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-text-main-light dark:text-white mb-1">{{ __('messages.clinic_name') }} *</label>
                        <input type="text" x-model="newClinic.name" class="w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.clinic_name_placeholder') }}">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-main-light dark:text-white mb-1">{{ __('messages.clinic_name_en') }}</label>
                        <input type="text" x-model="newClinic.name_en" class="w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.clinic_name_en_placeholder') }}">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main-light dark:text-white mb-1">{{ __('messages.specialty') }}</label>
                    <input type="text" x-model="newClinic.specialty" class="w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.specialty') }}">
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="addClinic()" class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-bold transition-colors">
                        <span class="material-symbols-outlined text-sm align-middle">add</span> {{ __('messages.save') }}
                    </button>
                    <button type="button" @click="showClinicForm = false" class="px-4 py-2 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium hover:bg-background-light dark:hover:bg-white/5 transition-colors">
                        {{ __('messages.cancel') }}
                    </button>
                </div>
            </div>

            {{-- Add Clinic Button --}}
            <button type="button" x-show="!showClinicForm" @click="showClinicForm = true"
                    class="w-full flex items-center justify-center gap-2 py-4 rounded-xl border-2 border-dashed border-border-light dark:border-border-dark text-text-sub-light dark:text-text-sub-dark hover:text-primary hover:border-primary/50 transition-colors">
                <span class="material-symbols-outlined">add_circle</span>
                <span class="text-sm font-medium">{{ __('messages.add_clinic') }}</span>
            </button>

            {{-- Empty State --}}
            <div x-show="clinics.length === 0 && !showClinicForm" class="text-center py-6">
                <span class="material-symbols-outlined text-4xl text-border-light dark:text-border-dark mb-2 block">emergency_home</span>
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.no_clinics_yet') }}</p>
            </div>
        </div>

        {{-- ═══════ STEP 3: Doctors ═══════ --}}
        <div x-show="step === 3" style="display:none" class="space-y-5">
            <template x-if="clinics.length === 0">
                <div class="text-center py-8">
                    <span class="material-symbols-outlined text-4xl text-border-light dark:text-border-dark mb-2 block">stethoscope</span>
                    <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.no_clinics_yet') }}</p>
                    <p class="text-xs text-text-sub-light/70 dark:text-text-sub-dark/70 mt-1">{{ __('messages.setup_add_clinic_hint') }}</p>
                </div>
            </template>

            {{-- Per-clinic doctor sections --}}
            <template x-for="(clinic, ci) in clinics" :key="'doc-' + ci">
                <div class="bg-background-light dark:bg-white/[0.03] rounded-xl border border-border-light dark:border-border-dark overflow-hidden">
                    {{-- Clinic Header --}}
                    <div class="px-4 py-3 border-b border-border-light dark:border-border-dark flex items-center gap-3">
                        <div class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                            <span class="material-symbols-outlined text-lg">emergency_home</span>
                        </div>
                        <span class="font-bold text-sm text-text-main-light dark:text-white" x-text="clinic.name"></span>
                        <span class="text-xs text-text-sub-light dark:text-text-sub-dark" x-text="clinic.specialty ? '(' + clinic.specialty + ')' : ''"></span>
                    </div>

                    <div class="p-4 space-y-2">
                        {{-- Doctors List --}}
                        <template x-for="(doc, di) in getDoctorsForClinic(ci)" :key="'d-' + ci + '-' + di">
                            <div class="flex items-center gap-3 p-2 rounded-lg bg-white dark:bg-surface-dark border border-border-light/50 dark:border-border-dark/50">
                                <div class="size-8 rounded-full bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-sm">person</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-text-main-light dark:text-white truncate" x-text="doc.name"></p>
                                    <p class="text-xs text-text-sub-light dark:text-text-sub-dark truncate" x-text="doc.specialty"></p>
                                </div>
                                <button type="button" @click="removeDoctor(doc._index)" class="p-1 rounded hover:bg-red-50 dark:hover:bg-red-500/10 text-red-500 transition-colors">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </button>
                            </div>
                        </template>

                        {{-- Inline Add Doctor Form --}}
                        <div x-show="addingDoctorForClinic === ci" style="display:none" class="bg-white dark:bg-surface-dark rounded-lg border-2 border-dashed border-teal-300/50 p-3 space-y-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-text-main-light dark:text-white mb-1">{{ __('messages.doctor_name') }} *</label>
                                    <input type="text" x-model="newDoctor.name" class="w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.doctor_name') }}">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-text-main-light dark:text-white mb-1">{{ __('messages.doctor_name_en') }}</label>
                                    <input type="text" x-model="newDoctor.name_en" class="w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.doctor_name_en') }}">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-text-main-light dark:text-white mb-1">{{ __('messages.specialty') }} *</label>
                                    <input type="text" x-model="newDoctor.specialty" class="w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.specialty') }}">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-text-main-light dark:text-white mb-1">{{ __('messages.phone') }}</label>
                                    <input type="text" x-model="newDoctor.phone" class="w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" dir="ltr" placeholder="{{ __('messages.phone') }}">
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="addDoctor(ci)" class="px-3 py-1.5 rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-bold transition-colors">
                                    <span class="material-symbols-outlined text-sm align-middle">add</span> {{ __('messages.save') }}
                                </button>
                                <button type="button" @click="addingDoctorForClinic = -1" class="px-3 py-1.5 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium hover:bg-background-light dark:hover:bg-white/5 transition-colors">
                                    {{ __('messages.cancel') }}
                                </button>
                            </div>
                        </div>

                        {{-- Add Doctor Button --}}
                        <button type="button" x-show="addingDoctorForClinic !== ci" @click="addingDoctorForClinic = ci; resetNewDoctor()"
                                class="w-full flex items-center justify-center gap-1.5 py-2 rounded-lg border border-dashed border-border-light dark:border-border-dark text-xs font-medium text-text-sub-light dark:text-text-sub-dark hover:text-teal-600 hover:border-teal-400/50 transition-colors">
                            <span class="material-symbols-outlined text-sm">add</span> {{ __('messages.add_doctor') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>

        {{-- ═══════ Navigation Buttons ═══════ --}}
        <div class="flex justify-between items-center pt-5 mt-5 border-t border-border-light dark:border-border-dark">
            <div>
                <button type="button" x-show="step > 1" style="display:none" @click="step--"
                        class="px-5 py-2.5 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium hover:bg-background-light dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">arrow_back</span> {{ __('messages.previous') }}
                </button>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('hospital.index') }}" class="px-5 py-2.5 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium hover:bg-background-light dark:hover:bg-white/5 transition-colors">{{ __('messages.cancel') }}</a>

                {{-- Next Step --}}
                <button type="button" x-show="step < 3" @click="nextStep()"
                        class="px-5 py-2.5 rounded-lg bg-primary hover:bg-primary-hover text-white text-sm font-bold transition-colors shadow-md shadow-primary/20 flex items-center gap-2">
                    {{ __('messages.next') }} <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </button>

                {{-- Submit --}}
                <button type="submit" x-show="step === 3" x-cloak style="display:none"
                        class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition-colors shadow-md shadow-emerald-600/20 flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">check_circle</span> {{ __('messages.save') }}
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function hospitalWizard() {
    return {
        step: 1,
        steps: ['{{ __("messages.hospital_info") }}', '{{ __("messages.hospital_clinics") }}', '{{ __("messages.total_doctors") }}'],
        hospital: {
            name: '{{ old("name", "") }}',
            name_en: '{{ old("name_en", "") }}',
            address: '{{ old("address", "") }}',
            phone: '{{ old("phone", "") }}'
        },
        clinics: [],
        doctors: [],
        showClinicForm: false,
        newClinic: { name: '', name_en: '', specialty: '' },
        addingDoctorForClinic: -1,
        newDoctor: { name: '', name_en: '', specialty: '', phone: '' },

        nextStep() {
            if (this.step === 1) {
                if (!this.hospital.name.trim()) {
                    this.$refs.mainForm.querySelector('[name="name"]').focus();
                    return;
                }
            }
            this.step++;
        },

        addClinic() {
            if (!this.newClinic.name.trim()) return;
            this.clinics.push({ ...this.newClinic });
            this.newClinic = { name: '', name_en: '', specialty: '' };
            this.showClinicForm = false;
        },

        removeClinic(index) {
            // Remove doctors for this clinic too
            this.doctors = this.doctors.filter(d => d.clinic_index !== index);
            // Adjust clinic_index for remaining doctors
            this.doctors = this.doctors.map(d => {
                if (d.clinic_index > index) d.clinic_index--;
                return d;
            });
            this.clinics.splice(index, 1);
        },

        getDoctorsForClinic(clinicIndex) {
            return this.doctors
                .map((d, i) => ({ ...d, _index: i }))
                .filter(d => d.clinic_index === clinicIndex);
        },

        resetNewDoctor() {
            this.newDoctor = { name: '', name_en: '', specialty: '', phone: '' };
        },

        addDoctor(clinicIndex) {
            if (!this.newDoctor.name.trim() || !this.newDoctor.specialty.trim()) return;
            this.doctors.push({ ...this.newDoctor, clinic_index: clinicIndex });
            this.resetNewDoctor();
            this.addingDoctorForClinic = -1;
        },

        removeDoctor(index) {
            this.doctors.splice(index, 1);
        },

        prepareSubmit() {
            // Data is already synced via x-model and :value bindings
        }
    }
}
</script>
@endsection
