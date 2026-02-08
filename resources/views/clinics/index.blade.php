@extends('layouts.app')

@section('content')
    <div class="flex flex-col gap-6">
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
            <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm overflow-hidden hover:shadow-md transition-shadow group">
                <div class="p-5 border-b border-[#e5e7eb] dark:border-[#2d3748] flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        <div class="size-12 rounded-xl bg-{{ $clinic['icon_color'] }}-100 dark:bg-{{ $clinic['icon_color'] }}-900/40 text-{{ $clinic['icon_color'] }}-600 flex items-center justify-center">
                            <span class="material-symbols-outlined">{{ $clinic['icon'] }}</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-[#111418] dark:text-white">{{ $clinic['name'] }}</h3>
                            <p class="text-xs text-[#637388] dark:text-[#9ca3af]">{{ $clinic['patients_waiting'] ?? 0 }} {{ __('messages.patients_waiting_text') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 dark:bg-{{ $statusColor }}-900/30 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-400 border border-{{ $statusColor }}-200 dark:border-{{ $statusColor }}-800">
                        <span class="size-1.5 rounded-full bg-{{ $statusColor }}-500"></span>
                        {{ $clinic['status'] }}
                    </span>
                </div>
                <div class="p-5 space-y-3">
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
                </div>
                <div class="bg-gray-50 dark:bg-[#202a37] p-4 border-t border-[#e5e7eb] dark:border-[#2d3748] flex justify-end gap-3 items-center">
                    <a href="{{ route('bookings.index', ['clinic_id' => $clinic['id']]) }}" class="text-[#637388] dark:text-[#9ca3af] hover:text-primary text-sm font-medium transition-colors">{{ __('messages.view_queue') }}</a>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <a href="{{ route('clinics.edit', $clinic['id']) }}" class="text-[#637388] dark:text-[#9ca3af] hover:text-primary text-sm font-medium transition-colors">{{ __('messages.details') }}</a>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <button type="button" onclick="deleteClinic('{{ $clinic['id'] }}', '{{ addslashes($clinic['name']) }}')" class="text-red-400 hover:text-red-600 text-sm font-medium transition-colors">{{ __('messages.delete') }}</button>
                </div>
            </div>
            @endforeach

             <!-- Add Grid Item -->
            <a href="{{ route('clinics.create') }}" class="border-2 border-dashed border-[#e5e7eb] dark:border-[#2d3748] rounded-xl p-6 flex flex-col items-center justify-center text-[#637388] dark:text-[#9ca3af] hover:border-primary hover:text-primary transition-colors h-full min-h-[250px] bg-gray-50 dark:bg-[#1a222e]/50">
                <span class="material-symbols-outlined text-4xl mb-3">add_circle</span>
                <span class="font-medium">{{ __('messages.add_new_clinic') }}</span>
            </a>
        </div>
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
