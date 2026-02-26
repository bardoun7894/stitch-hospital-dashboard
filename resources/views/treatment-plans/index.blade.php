@extends('layouts.app')

@section('content')
<main class="flex-1 flex flex-col h-full overflow-hidden bg-background-light dark:bg-background-dark relative" x-data="treatmentPlansPage()">

    <!-- TopNavBar -->
    <div class="flex items-center justify-between whitespace-nowrap border-b border-solid border-b-[#f0f2f4] dark:border-[#2d3748] px-6 py-4 bg-white dark:bg-[#1a2027] shrink-0 mb-6 rounded-xl shadow-sm">
        <div class="flex items-center gap-4">
            <h2 class="text-[#111418] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">{{ __('messages.treatment_plans_nav') }}</h2>
            <span class="hidden md:block text-[#637388] dark:text-gray-500 text-sm">|</span>
            <p class="hidden md:block text-[#637388] dark:text-gray-400 text-sm font-medium">{{ __('messages.treatment_plans_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-4">
            <button @click="showAddModal = true" class="h-10 px-4 bg-primary hover:bg-blue-600 text-white text-sm font-bold rounded-lg flex items-center gap-2 shadow-sm transition-colors">
                <span class="material-symbols-outlined text-lg">person_add</span>
                <span class="hidden sm:inline">{{ __('messages.add_to_treatment_plan') }}</span>
            </button>
        </div>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto pb-6 scroll-smooth">
        <div class="max-w-[1200px] mx-auto">
            <div class="bg-white dark:bg-[#1a2027] border border-[#e5e7eb] dark:border-[#2d3748] rounded-xl shadow-sm">
                @if(count($plans) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#f8fafc] dark:bg-[#1e2530] border-b border-[#e5e7eb] dark:border-[#2d3748]">
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.patient_name') }}</th>
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.phone') }}</th>
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.diagnosis') }}</th>
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.added_date') }}</th>
                                <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400 text-right">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2d3748]">
                            @foreach($plans as $plan)
                            <tr class="hover:bg-[#f8fafc] dark:hover:bg-[#2d3748] transition-colors">
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($plan['patient_name'] ?? 'P') }}&background=random" class="size-8 rounded-full object-cover" loading="lazy">
                                        <span class="text-[#111418] dark:text-white text-sm font-medium">{{ $plan['patient_name'] ?? '-' }}</span>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-sm text-[#637388] dark:text-gray-400">{{ $plan['patient_phone'] ?? '-' }}</td>
                                <td class="py-4 px-6">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300">
                                        {{ $plan['diagnosis'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-sm text-[#111418] dark:text-white">{{ $plan['created_at'] ?? '-' }}</td>
                                <td class="py-4 px-6 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button onclick="completePlan('{{ $plan['id'] }}')" class="bg-green-50 hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/40 text-green-600 dark:text-green-400 p-1.5 rounded-md transition-colors" title="{{ __('messages.complete_treatment_plan') }}">
                                            <span class="material-symbols-outlined text-lg">check_circle</span>
                                        </button>
                                        <button onclick="deletePlan('{{ $plan['id'] }}')" class="bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 p-1.5 rounded-md transition-colors" title="{{ __('messages.delete') }}">
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
                    <span class="material-symbols-outlined text-5xl text-[#d1d5db] dark:text-[#4a5568] mb-3 block">clinical_notes</span>
                    <p class="text-[#637388] dark:text-gray-400 text-lg font-medium">{{ __('messages.no_active_treatment_plans') }}</p>
                    <p class="text-[#9ca3af] dark:text-gray-500 text-sm mt-1">{{ __('messages.treatment_plans_empty_hint') }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Add Patient Modal -->
    <div x-show="showAddModal" x-cloak style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showAddModal = false">
        <div class="bg-white dark:bg-[#1a2027] rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden" @click.stop>
            <div class="p-6 border-b border-[#e5e7eb] dark:border-[#2d3748]">
                <h3 class="text-lg font-bold text-[#111418] dark:text-white">{{ __('messages.add_to_treatment_plan') }}</h3>
            </div>
            <div class="p-6 space-y-4">
                <!-- Today's Patients Quick Select -->
                @if(!empty($todaysPatients))
                <div x-show="!selectedPatient">
                    <label class="block text-sm font-medium text-[#637388] dark:text-gray-400 mb-1">
                        <span class="material-symbols-outlined text-sm align-middle me-1">today</span>
                        {{ __('messages.todays_patients') }}
                    </label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($todaysPatients as $tp)
                        <button type="button" @click="selectPatient({ id: '{{ $tp['id'] }}', name: '{{ addslashes($tp['name']) }}', phone: '{{ $tp['phone'] }}' })" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 border border-indigo-200 dark:border-indigo-800 transition-colors">
                            <span class="material-symbols-outlined text-sm">person</span>
                            {{ $tp['name'] }}
                        </button>
                        @endforeach
                    </div>
                </div>
                @endif

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

                <!-- Doctor Name (fixed - current logged-in doctor) -->
                <div>
                    <label class="block text-sm font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.doctor_name') }}</label>
                    <div class="w-full h-10 px-3 flex items-center rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-[#f8fafc] dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white">
                        <span class="material-symbols-outlined text-sm text-[#637388] me-2">person</span>
                        {{ $currentUser['name'] ?? '' }}
                    </div>
                </div>

                <!-- Doctor ID & Clinic ID (hidden, auto-set from current user) -->
                <input type="hidden" x-model="doctorId">
                <input type="hidden" x-model="clinicId">

                <!-- Diagnosis -->
                <div>
                    <label class="block text-sm font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.diagnosis') }}</label>
                    <input x-model="diagnosis" type="text" class="w-full h-10 px-3 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50">
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-[#637388] dark:text-gray-400 mb-1">{{ __('messages.notes') }}</label>
                    <textarea x-model="notes" rows="2" class="w-full px-3 py-2 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50 resize-none" placeholder="{{ __('messages.notes_optional') }}"></textarea>
                </div>
            </div>
            <div class="p-6 border-t border-[#e5e7eb] dark:border-[#2d3748] flex justify-end gap-3">
                <button @click="showAddModal = false" class="h-10 px-4 bg-gray-100 dark:bg-[#2d3748] text-[#637388] dark:text-gray-400 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-[#384455] transition-colors">
                    {{ __('messages.cancel') }}
                </button>
                <button @click="submitPlan()" :disabled="!selectedPatient || !diagnosis" class="h-10 px-4 bg-primary hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-bold rounded-lg shadow-sm transition-colors">
                    {{ __('messages.save') }}
                </button>
            </div>
        </div>
    </div>
</main>

<script>
function treatmentPlansPage() {
    return {
        showAddModal: false,
        searchQuery: '',
        searchResults: [],
        selectedPatient: null,
        doctorId: '{{ $currentUser['doctor_id'] ?? '' }}',
        clinicId: '{{ $currentUser['clinic_id'] ?? '' }}',
        diagnosis: '',
        notes: '',

        async searchPatients() {
            if (this.searchQuery.length < 2) {
                this.searchResults = [];
                return;
            }

            try {
                const response = await fetch(`/treatment-plans/search-patients?q=${encodeURIComponent(this.searchQuery)}`, {
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

        async submitPlan() {
            if (!this.selectedPatient || !this.doctorId || !this.diagnosis) return;

            try {
                const response = await fetch('/treatment-plans', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        patient_id: this.selectedPatient.id,
                        doctor_id: this.doctorId,
                        clinic_id: this.clinicId,
                        diagnosis: this.diagnosis,
                        notes: this.notes,
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message || 'Treatment plan created', 'success');
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

async function completePlan(planId) {
    if (!confirm('{{ __('messages.complete_treatment_confirm') }}')) return;

    try {
        const response = await fetch(`/treatment-plans/${planId}/complete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message || 'Completed', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred', 'error');
    }
}

async function deletePlan(planId) {
    if (!confirm('{{ __('messages.confirm_delete') }}')) return;

    try {
        const response = await fetch(`/treatment-plans/${planId}`, {
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
