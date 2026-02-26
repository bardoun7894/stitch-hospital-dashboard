@extends('layouts.app')

@section('content')
    <div class="flex flex-col gap-6" x-data="clinicsPage()">
        {{-- Alerts --}}
        @if(session('success'))
        <div class="px-4 py-3 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-lg text-sm">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm">
            {{ session('error') }}
        </div>
        @endif

        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-[#111418] dark:text-white leading-tight">{{ __('messages.clinics_management') }}</h1>
                <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ __('messages.manage_hospital_clinics') }}</p>
            </div>
            <a href="{{ route('clinics.create') }}" class="bg-primary hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-colors shadow-sm">
                <span class="material-symbols-outlined">add</span>
                {{ __('messages.add_clinic') }}
            </a>
        </div>

        {{-- Search & Filter Toolbar --}}
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
            <div class="relative flex items-center flex-1 w-full sm:max-w-sm">
                <span class="material-symbols-outlined absolute left-3 text-[#637388]">search</span>
                <input x-model="searchQuery" type="text" class="h-10 w-full rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1a222e] dark:text-white pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary/50 focus:border-primary" placeholder="{{ __('messages.search_clinics_placeholder') }}"/>
            </div>
            @if(count($hospitalsMap) > 1)
            <select x-model="selectedHospital" class="h-10 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1a222e] dark:text-white px-3 text-sm focus:ring-2 focus:ring-primary/50 focus:border-primary">
                <option value="">{{ __('messages.all_hospitals') }}</option>
                @foreach($hospitalsMap as $hId => $h)
                <option value="{{ $hId }}">{{ $h['name'] ?? $hId }}</option>
                @endforeach
            </select>
            @endif
            <template x-if="searchQuery.trim() || selectedHospital">
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-[#637388] dark:text-[#9ca3af]">
                        <span x-text="visibleCount"></span> {{ __('messages.clinics_found') }}
                    </span>
                    <button @click="searchQuery = ''; selectedHospital = ''" type="button" class="text-primary hover:text-blue-700 font-medium transition-colors flex items-center gap-1">
                        <span class="material-symbols-outlined text-base">close</span>
                        {{ __('messages.clear_filters') }}
                    </button>
                </div>
            </template>
        </div>

        <!-- Clinics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($clinics as $clinic)
            @php
                $statusColor = match($clinic['status']) {
                    'Running' => 'green',
                    'High Load' => 'amber',
                    'Paused' => 'red',
                    default => 'gray'
                };
            @endphp
            <div x-show="matchesFilter('{{ $clinic['hospital_id'] ?? '' }}', '{{ addslashes($clinic['name']) }}')" class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm overflow-hidden hover:shadow-md transition-shadow group">
                <div class="p-5 border-b border-[#e5e7eb] dark:border-[#2d3748] flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        <div class="size-12 rounded-xl bg-{{ $clinic['icon_color'] }}-100 dark:bg-{{ $clinic['icon_color'] }}-900/40 text-{{ $clinic['icon_color'] }}-600 flex items-center justify-center">
                            <span class="material-symbols-outlined">{{ $clinic['icon'] }}</span>
                        </div>
                        <div>
                            <a href="{{ route('clinics.show', $clinic['id']) }}" class="text-lg font-bold text-[#111418] dark:text-white hover:text-primary transition-colors">{{ $clinic['name'] }}</a>
                            <p class="text-xs text-[#637388] dark:text-[#9ca3af]">{{ $clinic['patients_waiting'] ?? 0 }} {{ __('messages.patients_waiting_text') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 dark:bg-{{ $statusColor }}-900/30 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-400 border border-{{ $statusColor }}-200 dark:border-{{ $statusColor }}-800">
                        <span class="size-1.5 rounded-full bg-{{ $statusColor }}-500"></span>
                        @php
                            $statusKey = strtolower(str_replace(' ', '_', $clinic['status']));
                            $displayStatus = __('messages.' . $statusKey);
                            if ($displayStatus === 'messages.' . $statusKey) {
                                $displayStatus = $clinic['status'];
                            }
                        @endphp
                        {{ $displayStatus }}
                    </span>
                </div>
                {{-- Hospital Card --}}
                @if(!empty($clinic['hospital_id']) && isset($hospitalsMap[$clinic['hospital_id']]))
                @php $hospital = $hospitalsMap[$clinic['hospital_id']]; @endphp
                <div class="px-5 pt-4 pb-2">
                    <a href="{{ route('hospital.show', $hospital['id']) }}" class="flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors group/hospital">
                        @if(!empty($hospital['logo_url']))
                        <img src="{{ $hospital['logo_url'] }}" alt="{{ $hospital['name'] ?? '' }}" class="size-8 rounded-lg object-cover border border-blue-200 dark:border-blue-800">
                        @else
                        <div class="size-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <span class="material-symbols-outlined text-base">domain</span>
                        </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-blue-700 dark:text-blue-400 truncate group-hover/hospital:underline">{{ $hospital['name'] ?? '' }}</p>
                            @if(!empty($hospital['name_en']))
                            <p class="text-[10px] text-blue-500 dark:text-blue-500 truncate">{{ $hospital['name_en'] }}</p>
                            @endif
                        </div>
                        <span class="material-symbols-outlined text-blue-400 dark:text-blue-500 text-sm">open_in_new</span>
                    </a>
                </div>
                @endif

                <div class="p-5 pt-3 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-[#637388] dark:text-[#9ca3af]">{{ __('messages.doctors_on_duty_label') }}</span>
                        <span class="font-medium text-[#111418] dark:text-white">{{ $clinic['doctors_on_duty'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-[#637388] dark:text-[#9ca3af]">{{ __('messages.average_wait_label') }}</span>
                        <span class="font-medium text-[#111418] dark:text-white">{{ $clinic['avg_wait'] ?? '0m' }}</span>
                    </div>
                     <div class="flex justify-between text-sm">
                        <span class="text-[#637388] dark:text-[#9ca3af]">{{ __('messages.patients_waiting_label') }}</span>
                        <span class="font-medium text-[#111418] dark:text-white">{{ $clinic['patients_waiting'] ?? 0 }}</span>
                    </div>
                    @if(!empty($clinic['accepted_insurance']) && is_array($clinic['accepted_insurance']) && count($clinic['accepted_insurance']) > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-[#637388] dark:text-[#9ca3af] flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">health_and_safety</span>
                            {{ __('messages.insurance_providers') }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-primary/10 text-primary rounded-full text-xs font-medium">
                            {{ count($clinic['accepted_insurance']) }} {{ __('messages.insurers_count') }}
                        </span>
                    </div>
                    @endif
                </div>
                <div class="bg-gray-50 dark:bg-[#202a37] p-4 border-t border-[#e5e7eb] dark:border-[#2d3748] flex justify-end gap-3 items-center">
                    <a href="{{ route('doctors.create', ['clinic_id' => $clinic['id']]) }}" class="text-[#637388] dark:text-[#9ca3af] hover:text-primary text-sm font-medium transition-colors flex items-center gap-1">
                        <span class="material-symbols-outlined text-base">person_add</span>
                        {{ __('messages.doctors') }}
                    </a>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <a href="{{ route('bookings.index', ['clinic_id' => $clinic['id']]) }}" class="text-[#637388] dark:text-[#9ca3af] hover:text-primary text-sm font-medium transition-colors">{{ __('messages.view_queue') }}</a>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <a href="{{ route('clinics.edit', $clinic['id']) }}" class="text-[#637388] dark:text-[#9ca3af] hover:text-primary text-sm font-medium transition-colors">{{ __('messages.details') }}</a>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <button type="button" onclick="deleteClinic('{{ $clinic['id'] }}', '{{ addslashes($clinic['name']) }}')" class="text-red-400 hover:text-red-600 text-sm font-medium transition-colors">{{ __('messages.delete') }}</button>
                </div>
            </div>
            @endforeach

             <!-- Add Grid Item (always visible) -->
            <a href="{{ route('clinics.create') }}" class="border-2 border-dashed border-[#e5e7eb] dark:border-[#2d3748] rounded-xl p-6 flex flex-col items-center justify-center text-[#637388] dark:text-[#9ca3af] hover:border-primary hover:text-primary transition-colors h-full min-h-[250px] bg-gray-50 dark:bg-[#1a222e]/50">
                <span class="material-symbols-outlined text-4xl mb-3">add_circle</span>
                <span class="font-medium">{{ __('messages.add_new_clinic') }}</span>
            </a>
        </div>

        {{-- No results empty state (only when filters are active) --}}
        <template x-if="(searchQuery.trim() || selectedHospital) && visibleCount === 0">
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <span class="material-symbols-outlined text-5xl text-[#637388] dark:text-[#9ca3af] mb-3">search_off</span>
                <p class="text-lg font-medium text-[#111418] dark:text-white">{{ __('messages.no_clinics_match') }}</p>
                <p class="text-sm text-[#637388] dark:text-[#9ca3af] mt-1">{{ __('messages.try_different_search') }}</p>
            </div>
        </template>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div id="deleteModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
        <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-6 max-w-sm mx-4 w-full">
            <h3 class="text-lg font-bold text-[#111418] dark:text-white mb-2">{{ __('messages.confirm_delete') }}</h3>
            <p class="text-sm text-[#637388] dark:text-[#9ca3af] mb-6" id="deleteModalText"></p>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 text-[#637388] hover:text-[#111418] dark:hover:text-white font-medium text-sm transition-colors">
                    {{ __('messages.cancel') }}
                </button>
                <button type="button" id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium text-sm transition-colors">
                    {{ __('messages.delete') }}
                </button>
            </div>
        </div>
    </div>

    <script>
        function clinicsPage() {
            return {
                searchQuery: '',
                selectedHospital: '',
                clinics: @json(array_map(fn($c) => ['hospital_id' => $c['hospital_id'] ?? '', 'name' => $c['name']], $clinics)),
                get visibleCount() {
                    return this.clinics.filter(c => this.matchesFilter(c.hospital_id, c.name)).length;
                },
                matchesFilter(hospitalId, clinicName) {
                    if (this.selectedHospital && hospitalId !== this.selectedHospital) return false;
                    if (this.searchQuery.trim()) {
                        return clinicName.toLowerCase().includes(this.searchQuery.toLowerCase());
                    }
                    return true;
                }
            }
        }

        let deleteClinicId = null;

        function deleteClinic(id, name) {
            deleteClinicId = id;
            document.getElementById('deleteModalText').textContent = '{{ __("messages.delete_clinic_confirm") }}: ' + name + '?';
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            deleteClinicId = null;
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (!deleteClinicId) return;

            fetch('/clinics/' + deleteClinicId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                closeDeleteModal();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to delete clinic');
                }
            })
            .catch(error => {
                closeDeleteModal();
                alert('An error occurred while deleting the clinic.');
                console.error(error);
            });
        });

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
@endsection
