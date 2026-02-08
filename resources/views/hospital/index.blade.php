@extends('layouts.app')

@section('content')
<div class="max-w-[1600px] mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ __('messages.hospitals') }}</h1>
            <p class="text-sm text-[#637388] dark:text-[#9ca3af] mt-1">{{ __('messages.manage_hospitals') }}</p>
        </div>
        @if(\App\Http\Middleware\RoleMiddleware::hasRole('super_admin'))
        <a href="{{ route('hospital.create') }}" class="bg-primary text-white px-4 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 hover:bg-primary/90 transition-colors">
            <span class="material-symbols-outlined text-sm">add</span>
            {{ __('messages.add_hospital') }}
        </a>
        @endif
    </div>

    {{-- Alerts --}}
    @if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm">
        {{ session('error') }}
    </div>
    @endif

    {{-- Hospitals Grid --}}
    @if(count($hospitals) === 0)
    <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] p-12 text-center">
        <span class="material-symbols-outlined text-5xl text-[#637388] dark:text-[#9ca3af] mb-3">domain</span>
        <p class="text-[#637388] dark:text-[#9ca3af]">{{ __('messages.no_hospitals') }}</p>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($hospitals as $hospital)
        <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] overflow-hidden hover:shadow-md transition-shadow">
            <div class="p-5 border-b border-[#e5e7eb] dark:border-[#2d3748]">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg text-blue-600 dark:text-blue-400">
                            <span class="material-symbols-outlined">domain</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-[#111418] dark:text-white">{{ $hospital['name'] ?? '' }}</h3>
                            <p class="text-xs text-[#637388] dark:text-[#9ca3af]">{{ $hospital['name_en'] ?? '' }}</p>
                        </div>
                    </div>
                    @php
                        $status = $hospital['status'] ?? 'active';
                        $statusClass = $status === 'active'
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
                    @endphp
                    <span class="px-2 py-1 rounded text-xs font-bold {{ $statusClass }}">{{ ucfirst($status) }}</span>
                </div>
            </div>
            <div class="p-5 space-y-3 text-sm">
                @if(!empty($hospital['address']))
                <div class="flex items-center gap-2 text-[#637388] dark:text-[#9ca3af]">
                    <span class="material-symbols-outlined text-base">location_on</span>
                    <span>{{ $hospital['address'] }}</span>
                </div>
                @endif
                @if(!empty($hospital['phone']))
                <div class="flex items-center gap-2 text-[#637388] dark:text-[#9ca3af]">
                    <span class="material-symbols-outlined text-base">phone</span>
                    <span>{{ $hospital['phone'] }}</span>
                </div>
                @endif
                <div class="flex items-center gap-2 text-[#637388] dark:text-[#9ca3af]">
                    <span class="material-symbols-outlined text-base">medical_services</span>
                    <span>{{ $hospital['clinic_count'] ?? 0 }} {{ __('messages.clinic_branches') }}</span>
                </div>
            </div>
            @if(\App\Http\Middleware\RoleMiddleware::hasRole('super_admin'))
            <div class="bg-gray-50 dark:bg-[#222b3a] p-3 flex items-center justify-between">
                <a href="{{ route('hospital.edit', $hospital['id']) }}" class="text-primary text-sm font-semibold hover:underline flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">edit</span>
                    {{ __('messages.edit') }}
                </a>
                <button onclick="confirmDelete('{{ $hospital['id'] }}', '{{ addslashes($hospital['name'] ?? '') }}')" class="text-red-500 text-sm font-semibold hover:underline flex items-center gap-1">
                    <span class="material-symbols-outlined text-base">delete</span>
                    {{ __('messages.delete') }}
                </button>
            </div>
            @endif
        </div>
        @endforeach

        {{-- Add Hospital Card (super_admin only) --}}
        @if(\App\Http\Middleware\RoleMiddleware::hasRole('super_admin'))
        <a href="{{ route('hospital.create') }}" class="border-2 border-dashed border-[#e5e7eb] dark:border-[#2d3748] rounded-xl p-8 flex flex-col items-center justify-center gap-3 text-[#637388] dark:text-[#9ca3af] hover:border-primary hover:text-primary transition-colors">
            <span class="material-symbols-outlined text-4xl">add_circle</span>
            <span class="font-semibold">{{ __('messages.add_hospital') }}</span>
        </a>
        @endif
    </div>
    @endif
</div>

{{-- Delete Modal --}}
<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-[#1a222e] rounded-xl p-6 max-w-sm mx-4 w-full">
        <h3 class="text-lg font-bold text-[#111418] dark:text-white mb-2">{{ __('messages.confirm_delete') }}</h3>
        <p class="text-sm text-[#637388] dark:text-[#9ca3af] mb-6" id="deleteMessage"></p>
        <div class="flex gap-3 justify-end">
            <button onclick="closeDeleteModal()" class="px-4 py-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-sm font-medium">{{ __('messages.cancel') }}</button>
            <button onclick="executeDelete()" class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-bold">{{ __('messages.delete') }}</button>
        </div>
    </div>
</div>

<script>
let deleteId = null;
function confirmDelete(id, name) {
    deleteId = id;
    document.getElementById('deleteMessage').textContent = '{{ __('messages.delete_hospital_confirm') }}'.replace(':name', name);
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    deleteId = null;
}
function executeDelete() {
    if (!deleteId) return;
    fetch(`/hospital/${deleteId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    }).then(r => r.json()).then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('{{ __('messages.hospital_delete_failed') }}');
            closeDeleteModal();
        }
    }).catch(() => {
        alert('{{ __('messages.hospital_delete_failed') }}');
        closeDeleteModal();
    });
}
</script>
@endsection
