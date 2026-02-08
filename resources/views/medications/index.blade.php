@extends('layouts.app')

@section('content')
<main class="flex-1 flex flex-col h-full overflow-hidden bg-background-light dark:bg-background-dark relative" x-data="medicationsPage()">

    <!-- TopNavBar -->
    <div class="flex items-center justify-between whitespace-nowrap border-b border-solid border-b-[#f0f2f4] dark:border-[#2d3748] px-6 py-4 bg-white dark:bg-[#1a2027] shrink-0 mb-6 rounded-xl shadow-sm">
        <div class="flex items-center gap-4">
            <h2 class="text-[#111418] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">{{ __('messages.medications_nav') }}</h2>
            <span class="hidden md:block text-[#637388] dark:text-gray-500 text-sm">|</span>
            <p class="hidden md:block text-[#637388] dark:text-gray-400 text-sm font-medium">{{ __('messages.medications_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-4">
            <button @click="showAddModal = true" class="h-10 px-4 bg-primary hover:bg-blue-600 text-white text-sm font-bold rounded-lg flex items-center gap-2 shadow-sm transition-colors">
                <span class="material-symbols-outlined text-lg">add_circle</span>
                <span class="hidden sm:inline">{{ __('messages.add_prescription') }}</span>
            </button>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto pb-6 scroll-smooth">
        <div class="max-w-[1200px] mx-auto">
            <div class="bg-white dark:bg-[#1a2027] border border-[#e5e7eb] dark:border-[#2d3748] rounded-xl shadow-sm">
                @if(count($prescriptions) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#f8fafc] dark:bg-[#1e2530] border-b border-[#e5e7eb] dark:border-[#2d3748]">
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.patient_name') }}</th>
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.doctor_name') }}</th>
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.medications_count') }}</th>
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.status') }}</th>
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.reminders') }}</th>
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.added_date') }}</th>
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400 text-right">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2d3748]">
                            @foreach($prescriptions as $prescription)
                            <tr class="hover:bg-[#f8fafc] dark:hover:bg-[#2d3748] transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($prescription['patient_name'] ?? 'P') }}&background=random" class="size-8 rounded-full object-cover" loading="lazy">
                                        <div>
                                            <span class="text-[#111418] dark:text-white text-sm font-medium">{{ $prescription['patient_name'] ?? '-' }}</span>
                                            <p class="text-xs text-[#637388] dark:text-gray-400">{{ $prescription['patient_phone'] ?? '' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-sm text-[#637388] dark:text-gray-400">{{ $prescription['doctor_name'] ?? '-' }}</td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                                        <span class="material-symbols-outlined text-xs">medication</span>
                                        {{ $prescription['medications_count'] ?? 0 }} {{ __('messages.medications_label') }}
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    @if(($prescription['status'] ?? '') === 'active')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">
                                        <span class="size-1.5 rounded-full bg-green-500"></span>
                                        {{ __('messages.active') }}
                                    </span>
                                    @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                        <span class="size-1.5 rounded-full bg-gray-400"></span>
                                        {{ __('messages.inactive') }}
                                    </span>
                                    @endif
                                </td>
                                <td class="py-4 px-6">
                                    @if($prescription['reminders_enabled'] ?? false)
                                    <span class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                        <span class="material-symbols-outlined text-sm">notifications_active</span>
                                        {{ __('messages.reminders_on') }}
                                    </span>
                                    @else
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                                        <span class="material-symbols-outlined text-sm">notifications_off</span>
                                        {{ __('messages.reminders_off') }}
                                    </span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-sm text-[#111418] dark:text-white">
                                    @php
                                        $createdAt = $prescription['created_at'] ?? '-';
                                        if (is_string($createdAt) && $createdAt !== '-') {
                                            try {
                                                $createdAt = (new \DateTime($createdAt))->format('Y-m-d H:i');
                                            } catch (\Exception $e) {
                                                // keep as-is
                                            }
                                        }
                                    @endphp
                                    {{ $createdAt }}
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button onclick="viewPrescription('{{ $prescription['id'] }}')" class="bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/40 text-blue-600 dark:text-blue-400 p-1.5 rounded-md transition-colors" title="{{ __('messages.view_details') }}">
                                            <span class="material-symbols-outlined text-lg">visibility</span>
                                        </button>
                                        <button onclick="deletePrescription('{{ $prescription['id'] }}')" class="bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 p-1.5 rounded-md transition-colors" title="{{ __('messages.delete') }}">
                                            <span class="material-symbols-outlined text-lg">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="py-16 text-center">
                    <span class="material-symbols-outlined text-5xl text-[#d1d5db] dark:text-[#4a5568] mb-3 block">medication</span>
                    <p class="text-[#637388] dark:text-gray-400 text-lg font-medium">{{ __('messages.no_prescriptions') }}</p>
                    <p class="text-[#9ca3af] dark:text-gray-500 text-sm mt-1">{{ __('messages.prescriptions_empty_hint') }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Add Prescription Modal -->
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showAddModal = false">
        <div class="bg-white dark:bg-[#1a2027] rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] flex flex-col overflow-hidden" @click.stop>
            <div class="p-6 border-b border-[#e5e7eb] dark:border-[#2d3748] shrink-0">
                <h3 class="text-lg font-bold text-[#111418] dark:text-white">{{ __('messages.add_prescription') }}</h3>
                <p class="text-sm text-[#637388] dark:text-gray-400 mt-1">{{ __('messages.prescription_form_hint') }}</p>
            </div>
            <div class="p-6 space-y-4 overflow-y-auto flex-1">
                <!-- Patient Search -->
                <div>
                    <label class="block text-sm font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.search_patients') }}</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-2.5 text-[#637388]">search</span>
                        <input
                            x-model="searchQuery"
                            @input.debounce.300ms="searchPatients()"
                            type="text"
                            class="w-full h-10 pl-10 pr-4 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50"
                            :placeholder="'{{ __('messages.search_by_name_phone') }}'"
                        >
                    </div>
                    <!-- Search Results Dropdown -->
                    <div x-show="searchResults.length > 0 && !selectedPatient" class="mt-1 border border-[#e5e7eb] dark:border-[#4a5568] rounded-lg bg-white dark:bg-[#2d3748] max-h-40 overflow-y-auto shadow-lg">
                        <template x-for="patient in searchResults" :key="patient.id">
                            <button @click="selectPatient(patient)" class="w-full text-left px-4 py-2 hover:bg-[#f0f2f4] dark:hover:bg-[#384455] text-sm transition-colors flex items-center justify-between">
                                <span class="text-[#111418] dark:text-white font-medium" x-text="patient.name"></span>
                                <span class="text-[#637388] dark:text-gray-400 text-xs" x-text="patient.phone"></span>
                            </button>
                        </template>
                    </div>
                    <!-- Selected Patient -->
                    <div x-show="selectedPatient" class="mt-2 flex items-center gap-2 bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300 px-3 py-2 rounded-lg text-sm">
                        <span class="material-symbols-outlined text-sm">person</span>
                        <span x-text="selectedPatient?.name"></span>
                        <button @click="selectedPatient = null; searchQuery = ''" class="ms-auto text-blue-500 hover:text-blue-700">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    </div>
                </div>

                <!-- Doctor Select -->
                <div>
                    <label class="block text-sm font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.doctor_name') }}</label>
                    <select x-model="doctorId" class="w-full h-10 px-3 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50">
                        <option value="">{{ __('messages.select_doctor') }}</option>
                        @foreach($doctors ?? [] as $doctor)
                        <option value="{{ $doctor['id'] }}">{{ $doctor['name'] }} — {{ $doctor['specialty'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Clinic ID (hidden, populated from doctor selection) -->
                <input type="hidden" x-model="clinicId">

                <!-- Medications Section -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-[#637388] dark:text-gray-400">{{ __('messages.medications_label') }}</label>
                        <button @click="addMedication()" class="text-primary hover:text-blue-700 text-sm font-medium flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">add</span>
                            {{ __('messages.add_medication') }}
                        </button>
                    </div>

                    <template x-for="(med, index) in medications" :key="index">
                        <div class="border border-[#e5e7eb] dark:border-[#4a5568] rounded-lg p-4 mb-3 relative">
                            <!-- Remove button -->
                            <button x-show="medications.length > 1" @click="removeMedication(index)" class="absolute top-2 right-2 rtl:right-auto rtl:left-2 text-red-400 hover:text-red-600 p-1">
                                <span class="material-symbols-outlined text-sm">close</span>
                            </button>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- Medication Name -->
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-[#637388] dark:text-gray-400 mb-1" x-text="'{{ __('messages.medication_name') }} ' + (index + 1)"></label>
                                    <input x-model="med.name" type="text" class="w-full h-9 px-3 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50" placeholder="{{ __('messages.medication_name_placeholder') }}">
                                </div>

                                <!-- Duration Days -->
                                <div>
                                    <label class="block text-xs font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.duration_days') }}</label>
                                    <input x-model.number="med.duration_days" type="number" min="1" max="365" class="w-full h-9 px-3 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50" placeholder="3">
                                </div>

                                <!-- Interval Hours -->
                                <div>
                                    <label class="block text-xs font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.interval_hours') }}</label>
                                    <input x-model.number="med.interval_hours" type="number" min="0.5" max="24" step="0.5" class="w-full h-9 px-3 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50" placeholder="4">
                                </div>

                                <!-- Dose Amount -->
                                <div>
                                    <label class="block text-xs font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.dose_amount') }}</label>
                                    <input x-model="med.dose_amount" type="text" class="w-full h-9 px-3 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50" placeholder="{{ __('messages.dose_amount_placeholder') }}">
                                </div>

                                <!-- Dose Unit -->
                                <div>
                                    <label class="block text-xs font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.dose_unit') }}</label>
                                    <select x-model="med.dose_unit" class="w-full h-9 px-3 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50">
                                        <option value="pill">{{ __('messages.unit_pill') }}</option>
                                        <option value="tablet">{{ __('messages.unit_tablet') }}</option>
                                        <option value="capsule">{{ __('messages.unit_capsule') }}</option>
                                        <option value="ml">{{ __('messages.unit_ml') }}</option>
                                        <option value="mg">{{ __('messages.unit_mg') }}</option>
                                        <option value="drop">{{ __('messages.unit_drop') }}</option>
                                        <option value="spoon">{{ __('messages.unit_spoon') }}</option>
                                    </select>
                                </div>

                                <!-- First Dose Time -->
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.first_dose_time') }}</label>
                                    <input x-model="med.first_dose_time" type="time" class="w-full h-9 px-3 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50">
                                </div>
                            </div>

                            <!-- Calculated Schedule Preview -->
                            <div x-show="med.first_dose_time && med.interval_hours" class="mt-3 p-2 bg-[#f8fafc] dark:bg-[#2d3748] rounded-lg">
                                <p class="text-xs font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.daily_schedule_preview') }}:</p>
                                <p class="text-xs text-[#111418] dark:text-white" x-text="calculateSchedulePreview(med)"></p>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.notes') }}</label>
                    <textarea x-model="notes" rows="2" class="w-full px-3 py-2 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50 resize-none" placeholder="{{ __('messages.notes_optional') }}"></textarea>
                </div>
            </div>
            <div class="p-6 border-t border-[#e5e7eb] dark:border-[#2d3748] flex justify-end gap-3 shrink-0">
                <button @click="showAddModal = false; resetForm()" class="h-10 px-4 bg-gray-100 dark:bg-[#2d3748] text-[#637388] dark:text-gray-400 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-[#384455] transition-colors">
                    {{ __('messages.cancel') }}
                </button>
                <button @click="submitPrescription()" :disabled="!canSubmit" class="h-10 px-4 bg-primary hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-bold rounded-lg shadow-sm transition-colors">
                    {{ __('messages.save') }}
                </button>
            </div>
        </div>
    </div>

    <!-- View Prescription Detail Modal -->
    <div x-show="showDetailModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showDetailModal = false">
        <div class="bg-white dark:bg-[#1a2027] rounded-2xl shadow-2xl w-full max-w-lg mx-4 max-h-[80vh] flex flex-col overflow-hidden" @click.stop>
            <div class="p-6 border-b border-[#e5e7eb] dark:border-[#2d3748] shrink-0">
                <h3 class="text-lg font-bold text-[#111418] dark:text-white">{{ __('messages.prescription_details') }}</h3>
            </div>
            <div class="p-6 overflow-y-auto flex-1">
                <template x-if="detailPrescription">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-[#637388] dark:text-gray-400 text-xs">{{ __('messages.patient_name') }}</p>
                                <p class="text-[#111418] dark:text-white font-medium" x-text="detailPrescription.patient_name"></p>
                            </div>
                            <div>
                                <p class="text-[#637388] dark:text-gray-400 text-xs">{{ __('messages.doctor_name') }}</p>
                                <p class="text-[#111418] dark:text-white font-medium" x-text="detailPrescription.doctor_name"></p>
                            </div>
                        </div>

                        <div class="border-t border-[#e5e7eb] dark:border-[#2d3748] pt-4">
                            <h4 class="text-sm font-semibold text-[#111418] dark:text-white mb-3">{{ __('messages.medications_label') }}</h4>
                            <template x-for="(med, idx) in (detailPrescription.medications || [])" :key="idx">
                                <div class="bg-[#f8fafc] dark:bg-[#2d3748] rounded-lg p-3 mb-2">
                                    <p class="text-sm font-medium text-[#111418] dark:text-white" x-text="med.name"></p>
                                    <div class="grid grid-cols-2 gap-2 mt-2 text-xs text-[#637388] dark:text-gray-400">
                                        <p>{{ __('messages.dose_amount') }}: <span class="text-[#111418] dark:text-white" x-text="med.dose_amount + ' ' + med.dose_unit"></span></p>
                                        <p>{{ __('messages.duration_days') }}: <span class="text-[#111418] dark:text-white" x-text="med.duration_days + ' {{ __('messages.days') }}'"></span></p>
                                        <p>{{ __('messages.interval_hours') }}: <span class="text-[#111418] dark:text-white" x-text="'{{ __('messages.every') }} ' + med.interval_hours + ' {{ __('messages.hours') }}'"></span></p>
                                        <p>{{ __('messages.first_dose_time') }}: <span class="text-[#111418] dark:text-white" x-text="med.first_dose_time"></span></p>
                                    </div>
                                    <div x-show="med.dose_schedule && med.dose_schedule.length > 0" class="mt-2 text-xs">
                                        <p class="text-[#637388] dark:text-gray-400">{{ __('messages.daily_schedule_preview') }}:</p>
                                        <p class="text-primary font-medium" x-text="(med.dose_schedule || []).join(' , ')"></p>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div x-show="detailPrescription.notes" class="border-t border-[#e5e7eb] dark:border-[#2d3748] pt-4">
                            <p class="text-xs text-[#637388] dark:text-gray-400">{{ __('messages.notes') }}</p>
                            <p class="text-sm text-[#111418] dark:text-white mt-1" x-text="detailPrescription.notes"></p>
                        </div>
                    </div>
                </template>
            </div>
            <div class="p-6 border-t border-[#e5e7eb] dark:border-[#2d3748] flex justify-end shrink-0">
                <button @click="showDetailModal = false" class="h-10 px-4 bg-gray-100 dark:bg-[#2d3748] text-[#637388] dark:text-gray-400 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-[#384455] transition-colors">
                    {{ __('messages.close') }}
                </button>
            </div>
        </div>
    </div>
</main>

<script>
// Doctor clinic mapping
const doctorClinicMap = {
    @foreach($doctors ?? [] as $doctor)
    '{{ $doctor['id'] }}': '{{ $doctor['clinic_id'] ?? '' }}',
    @endforeach
};

function medicationsPage() {
    return {
        showAddModal: false,
        showDetailModal: false,
        detailPrescription: null,
        searchQuery: '',
        searchResults: [],
        selectedPatient: null,
        doctorId: '',
        clinicId: '',
        notes: '',
        medications: [
            { name: '', duration_days: 3, interval_hours: 8, dose_amount: '', dose_unit: 'pill', first_dose_time: '08:00' }
        ],

        get canSubmit() {
            if (!this.selectedPatient || !this.doctorId) return false;
            return this.medications.every(med =>
                med.name && med.duration_days > 0 && med.interval_hours > 0 && med.dose_amount && med.first_dose_time
            );
        },

        addMedication() {
            this.medications.push({
                name: '', duration_days: 3, interval_hours: 8, dose_amount: '', dose_unit: 'pill', first_dose_time: '08:00'
            });
        },

        removeMedication(index) {
            if (this.medications.length > 1) {
                this.medications.splice(index, 1);
            }
        },

        resetForm() {
            this.searchQuery = '';
            this.searchResults = [];
            this.selectedPatient = null;
            this.doctorId = '';
            this.clinicId = '';
            this.notes = '';
            this.medications = [
                { name: '', duration_days: 3, interval_hours: 8, dose_amount: '', dose_unit: 'pill', first_dose_time: '08:00' }
            ];
        },

        calculateSchedulePreview(med) {
            if (!med.first_dose_time || !med.interval_hours || med.interval_hours <= 0) return '';

            const times = [];
            const [hours, minutes] = med.first_dose_time.split(':').map(Number);
            let currentMinutes = hours * 60 + minutes;
            const intervalMinutes = med.interval_hours * 60;
            const dosesPerDay = Math.floor((24 * 60) / intervalMinutes);

            for (let i = 0; i < dosesPerDay; i++) {
                const h = Math.floor((currentMinutes % (24 * 60)) / 60);
                const m = Math.round(currentMinutes % 60);
                times.push(String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0'));
                currentMinutes += intervalMinutes;
            }

            return times.join(' , ');
        },

        async searchPatients() {
            if (this.searchQuery.length < 2) {
                this.searchResults = [];
                return;
            }

            try {
                const response = await fetch(`/medications/search-patients?q=${encodeURIComponent(this.searchQuery)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                this.searchResults = data.data || [];
            } catch (error) {
                console.error('Search error:', error);
            }
        },

        selectPatient(patient) {
            this.selectedPatient = patient;
            this.searchQuery = patient.name;
            this.searchResults = [];
        },

        async submitPrescription() {
            if (!this.canSubmit) return;

            this.clinicId = doctorClinicMap[this.doctorId] || '';

            try {
                const response = await fetch('/medications', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        patient_id: this.selectedPatient.id,
                        doctor_id: this.doctorId,
                        clinic_id: this.clinicId,
                        medications: this.medications,
                        notes: this.notes,
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message || 'Prescription created', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred', 'error');
            }
        }
    };
}

async function viewPrescription(id) {
    try {
        const response = await fetch(`/medications/${id}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();

        if (data.success) {
            const page = document.querySelector('[x-data]').__x.$data;
            page.detailPrescription = data.data;
            page.showDetailModal = true;
        } else {
            showNotification('Error loading prescription', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred', 'error');
    }
}

async function deletePrescription(id) {
    if (!confirm('{{ __('messages.confirm_delete_prescription') }}')) return;

    try {
        const response = await fetch(`/medications/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message || 'Deleted', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred', 'error');
    }
}

function showNotification(message, type = 'info') {
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';

    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-[60] transition-opacity`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>
@endsection
