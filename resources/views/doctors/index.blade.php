@extends('layouts.app')

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight mb-1">{{ __('messages.medical_staff') }}</h1>
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.doctors_directory') }}</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                 <div class="relative">
                     <span class="absolute left-3 top-2.5 material-symbols-outlined text-text-sub-light dark:text-text-sub-dark text-xl">search</span>
                     <input type="text" placeholder="{{ __('messages.search_doctors') }}" class="pl-10 pr-4 py-2 w-full sm:w-64 bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-text-main-light dark:text-white placeholder-text-sub-light/50 transition-all shadow-sm">
                </div>
                <a href="{{ route('doctors.create') }}" class="bg-primary hover:bg-primary-hover text-white font-bold py-2 px-4 rounded-xl flex items-center justify-center gap-2 transition-all shadow-md shadow-primary/20 hover:shadow-lg active:scale-95">
                    <span class="material-symbols-outlined text-sm">add</span>
                    {{ __('messages.add_doctor') }}
                </a>
            </div>
        </div>

        {{-- Success/Error Alerts --}}
        @if(session('success'))
        <div class="px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 rounded-xl text-sm border border-emerald-100 dark:border-emerald-900/30 flex items-center gap-2 shadow-sm">
            <span class="material-symbols-outlined text-sm">check_circle</span>
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="px-4 py-3 bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-400 rounded-xl text-sm border border-rose-100 dark:border-rose-900/30 flex items-center gap-2 shadow-sm">
            <span class="material-symbols-outlined text-sm">error</span>
            {{ session('error') }}
        </div>
        @endif

        <div class="bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-2xl shadow-sm overflow-hidden flex flex-col">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse relative">
                    <thead>
                        <tr class="bg-background-light/90 dark:bg-white/5 backdrop-blur-sm sticky top-0 z-10 border-b border-border-light dark:border-border-dark">
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.name') }}</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.specialty') }}</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.clinic') }}</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.status') }}</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.contact') }}</th>
                            <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-border-dark">
                        @foreach($doctors as $index => $doctor)
                        @php
                            $statusKey = strtolower(str_replace(' ', '_', $doctor['status']));
                            $displayStatus = __('messages.' . $statusKey);
                            // Fallback if key doesn't exist
                            if ($displayStatus === 'messages.' . $statusKey) {
                                $displayStatus = $doctor['status'];
                            }

                            $statusColor = match($doctor['status']) {
                                'available', 'Available' => 'emerald',
                                'On Duty' => 'emerald',
                                'busy', 'Busy' => 'amber',
                                'On Call' => 'amber',
                                'off', 'Off', 'Off Duty' => 'slate',
                                default => 'slate'
                            };
                        @endphp
                        <tr class="hover:bg-background-light dark:hover:bg-white/5 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <img class="size-10 rounded-full border-2 border-white dark:border-[#1a2027] shadow-sm object-cover" src="https://ui-avatars.com/api/?name={{ urlencode($doctor['name']) }}&background=random" alt="">
                                        <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white dark:border-[#1a2027] bg-{{ $statusColor }}-500"></span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-text-main-light dark:text-white group-hover:text-primary transition-colors">{{ $doctor['name'] }}</div>
                                        <div class="text-[10px] text-text-sub-light dark:text-text-sub-dark font-mono bg-background-light dark:bg-white/5 px-1.5 py-0.5 rounded">{{ __('messages.id_prefix') }}DOC-{{ str_pad($index + 1, 3, '0', STR_PAD_LEFT) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-main-light dark:text-white font-medium">
                                {{ $doctor['specialty'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 inline-flex text-xs font-bold rounded-lg bg-primary/5 text-primary border border-primary/10">
                                    {{ $doctor['clinic'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold backdrop-blur-sm shadow-sm bg-{{ $statusColor }}-50/80 dark:bg-{{ $statusColor }}-500/10 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-400 border border-{{ $statusColor }}-100 dark:border-{{ $statusColor }}-500/20">
                                    <span class="relative flex h-1.5 w-1.5">
                                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-{{ $statusColor }}-400 opacity-75"></span>
                                      <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-{{ $statusColor }}-500"></span>
                                    </span>
                                    {{ $displayStatus }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-text-sub-light dark:text-text-sub-dark">
                                <div class="flex items-center gap-2 font-medium">
                                     <span class="material-symbols-outlined text-sm opacity-70">call</span>
                                     {{ $doctor['phone'] ?? ('+1 (555) 123-456' . $index) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('doctors.schedule', $doctor['id']) }}" class="p-2 text-text-sub-light dark:text-text-sub-dark hover:text-primary hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all rounded-lg" title="{{ __('messages.manage_schedule') }}">
                                        <span class="material-symbols-outlined text-xl">calendar_month</span>
                                    </a>
                                    <a href="{{ route('doctors.edit', $doctor['id']) }}" class="p-2 text-text-sub-light dark:text-text-sub-dark hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-all rounded-lg" title="{{ __('messages.edit_profile') }}">
                                        <span class="material-symbols-outlined text-xl">edit</span>
                                    </a>
                                    <button onclick="deleteDoctor('{{ $doctor['id'] }}', '{{ addslashes($doctor['name']) }}')" class="p-2 text-text-sub-light dark:text-text-sub-dark hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-all rounded-lg" title="{{ __('messages.delete') }}">
                                        <span class="material-symbols-outlined text-xl">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
             <div class="px-6 py-4 border-t border-border-light dark:border-border-dark flex items-center justify-between bg-background-light/30 dark:bg-white/5">
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark font-medium">{{ __('messages.showing_doctors', ['count' => count($doctors)]) }}</p>
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
    </div>

    {{-- Delete Confirmation Modal --}}
    <div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-6 max-w-md mx-4 shadow-2xl transform scale-100 transition-transform duration-300">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-3 bg-rose-50 dark:bg-rose-900/20 rounded-full">
                    <span class="material-symbols-outlined text-rose-600 dark:text-rose-400">warning</span>
                </div>
                <h3 class="text-lg font-bold text-text-main-light dark:text-white tracking-tight">{{ __('messages.confirm_delete') }}</h3>
            </div>
            <p class="text-sm text-text-sub-light dark:text-text-sub-dark mb-6 leading-relaxed" id="deleteMessage"></p>
            <div class="flex items-center justify-end gap-3">
                <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-text-sub-light hover:text-text-main-light dark:hover:text-white transition-colors">
                    {{ __('messages.cancel') }}
                </button>
                <button onclick="confirmDelete()" class="px-4 py-2 text-sm font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-md shadow-rose-600/20 transition-all hover:shadow-lg active:scale-95">
                    {{ __('messages.delete') }}
                </button>
            </div>
        </div>
    </div>

    <script>
        // ... script stays mostly the same, logic is fine ...
        let deleteDoctorId = null;

        function deleteDoctor(id, name) {
            deleteDoctorId = id;
            document.getElementById('deleteMessage').textContent = '{{ __("messages.delete_doctor_confirm") }}'.replace(':name', name) || 'Are you sure you want to delete ' + name + '?';
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            deleteDoctorId = null;
        }

        function confirmDelete() {
            if (!deleteDoctorId) return;

            fetch('/doctors/' + deleteDoctorId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                closeDeleteModal();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || '{{ __("messages.error") }}');
                }
            })
            .catch(error => {
                closeDeleteModal();
                alert('{{ __("messages.error_occurred") }}');
                console.error(error);
            });
        }

        // Close modal on backdrop click
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
@endsection
