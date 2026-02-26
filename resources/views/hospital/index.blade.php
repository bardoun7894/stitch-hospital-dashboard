@extends('layouts.app')

@php
    use App\Http\Middleware\RoleMiddleware;
    $isSuperAdmin = RoleMiddleware::hasRole('super_admin');
@endphp

@section('content')
<div class="max-w-[1600px] mx-auto">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight mb-1">{{ __('messages.hospitals') }}</h1>
            <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.manage_hospitals') }}</p>
        </div>
        @if($isSuperAdmin)
        <a href="{{ route('hospital.create') }}" class="bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg flex items-center justify-center gap-2 transition-all shadow-md shadow-primary/20 hover:shadow-lg active:scale-95">
            <span class="material-symbols-outlined text-sm">add</span>
            {{ __('messages.add_hospital') }}
        </a>
        @endif
    </div>

    {{-- Alerts --}}
    @if(session('success'))
    <div class="mb-4 px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 rounded-2xl text-sm border border-emerald-100 dark:border-emerald-900/30 flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">check_circle</span> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-2xl text-sm border border-red-100 dark:border-red-900/30 flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">error</span> {{ session('error') }}
    </div>
    @endif

    {{-- Status Filter Tabs --}}
    @if($isSuperAdmin)
    <div class="mb-6 bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-1.5 inline-flex gap-1">
        @php
            $tabs = [
                'all' => ['label' => __('messages.all_statuses'), 'count' => $statusCounts['all']],
                'waiting' => ['label' => __('messages.filter_waiting'), 'count' => $statusCounts['waiting']],
                'pending' => ['label' => __('messages.filter_pending'), 'count' => $statusCounts['pending']],
                'active' => ['label' => __('messages.filter_active'), 'count' => $statusCounts['active']],
                'blocked' => ['label' => __('messages.filter_blocked'), 'count' => $statusCounts['blocked']],
                'rejected' => ['label' => __('messages.filter_rejected'), 'count' => $statusCounts['rejected']],
                'inactive' => ['label' => __('messages.filter_inactive'), 'count' => $statusCounts['inactive']],
            ];
        @endphp
        @foreach($tabs as $key => $tab)
        <a href="{{ route('hospital.index', ['status' => $key]) }}"
           class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all flex items-center gap-1.5 {{ $statusFilter === $key ? 'bg-primary text-white shadow-sm' : 'text-text-sub-light dark:text-text-sub-dark hover:bg-background-light dark:hover:bg-white/5' }}">
            {{ $tab['label'] }}
            @if($tab['count'] > 0 && $key !== 'all')
            <span class="min-w-[18px] h-[18px] flex items-center justify-center text-[10px] font-bold rounded-full {{ $statusFilter === $key ? 'bg-white/20 text-white' : ($key === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-background-light dark:bg-white/10 text-text-sub-light') }}">{{ $tab['count'] }}</span>
            @endif
        </a>
        @endforeach
    </div>
    @endif

    {{-- Hospitals Grid --}}
    @if(count($hospitals) === 0)
    <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-12 text-center">
        <span class="material-symbols-outlined text-5xl text-border-light dark:text-border-dark mb-3 block">domain</span>
        <p class="text-text-sub-light dark:text-text-sub-dark font-medium">{{ __('messages.no_hospitals') }}</p>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($hospitals as $hospital)
        @php
            $status = $hospital['status'] ?? 'active';
            $statusConfig = match($status) {
                'waiting' => ['bg' => 'bg-blue-100 dark:bg-blue-900/30', 'text' => 'text-blue-700 dark:text-blue-400', 'icon' => 'schedule'],
                'pending' => ['bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-700 dark:text-amber-400', 'icon' => 'hourglass_top'],
                'active' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-700 dark:text-emerald-400', 'icon' => 'check_circle'],
                'blocked' => ['bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-700 dark:text-red-400', 'icon' => 'block'],
                'rejected' => ['bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-700 dark:text-red-400', 'icon' => 'cancel'],
                'inactive' => ['bg' => 'bg-gray-100 dark:bg-gray-800', 'text' => 'text-gray-600 dark:text-gray-400', 'icon' => 'pause_circle'],
                default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'icon' => 'help'],
            };
        @endphp
        <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark overflow-hidden card-hover">
            {{-- Clickable Card Header --}}
            <a href="{{ route('hospital.show', $hospital['id']) }}" class="block p-5 border-b border-border-light dark:border-border-dark hover:bg-background-light/50 dark:hover:bg-white/5 transition-colors">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        @if(!empty($hospital['logo_url']))
                        <img src="{{ $hospital['logo_url'] }}" alt="{{ $hospital['name'] ?? '' }}" class="size-10 rounded-xl object-cover border border-border-light dark:border-border-dark">
                        @else
                        <div class="p-3 rounded-xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined">domain</span>
                        </div>
                        @endif
                        <div>
                            <h3 class="font-bold text-text-main-light dark:text-white">{{ $hospital['name'] ?? '' }}</h3>
                            <p class="text-xs text-text-sub-light dark:text-text-sub-dark">{{ $hospital['name_en'] ?? '' }}</p>
                        </div>
                    </div>
                    <span class="px-2 py-1 rounded-full text-[10px] font-bold {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} flex items-center gap-1">
                        <span class="material-symbols-outlined text-xs">{{ $statusConfig['icon'] }}</span>
                        {{ __('messages.' . strtolower($status)) }}
                    </span>
                </div>
            </a>

            {{-- Details --}}
            <div class="p-5 space-y-3 text-sm">
                @if(!empty($hospital['address']))
                <div class="flex items-center gap-2 text-text-sub-light dark:text-text-sub-dark">
                    <span class="material-symbols-outlined text-base">location_on</span>
                    <span>{{ $hospital['address'] }}</span>
                </div>
                @endif
                @if(!empty($hospital['phone']))
                <div class="flex items-center gap-2 text-text-sub-light dark:text-text-sub-dark">
                    <span class="material-symbols-outlined text-base">phone</span>
                    <span>{{ $hospital['phone'] }}</span>
                </div>
                @endif
                <div class="flex items-center gap-2 text-text-sub-light dark:text-text-sub-dark">
                    <span class="material-symbols-outlined text-base">emergency_home</span>
                    <span>{{ $hospital['clinic_count'] ?? 0 }} {{ __('messages.clinic_branches') }}</span>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="bg-background-light/30 dark:bg-white/5 p-3 flex items-center justify-between border-t border-border-light dark:border-border-dark">
                @if($isSuperAdmin && $status === 'pending')
                {{-- Approve/Reject for pending --}}
                <div class="flex items-center gap-2">
                    <form action="{{ route('hospital.approve', $hospital['id']) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_approve') }}')">
                        @csrf
                        <button type="submit" class="text-emerald-600 text-sm font-semibold hover:underline flex items-center gap-1">
                            <span class="material-symbols-outlined text-base">check_circle</span>
                            {{ __('messages.approve_hospital') }}
                        </button>
                    </form>
                    <form action="{{ route('hospital.reject', $hospital['id']) }}" method="POST" onsubmit="if(!this.rejection_reason.value){alert('{{ __('messages.rejection_reason') }}');return false;}return confirm('{{ __('messages.confirm_reject') }}')">
                        @csrf
                        <input type="hidden" name="rejection_reason" value="Rejected from listing">
                        <button type="submit" class="text-red-500 text-sm font-semibold hover:underline flex items-center gap-1">
                            <span class="material-symbols-outlined text-base">cancel</span>
                            {{ __('messages.reject_hospital') }}
                        </button>
                    </form>
                </div>
                @else
                <div class="flex items-center gap-3">
                    <a href="{{ route('hospital.show', $hospital['id']) }}" class="text-text-sub-light dark:text-text-sub-dark hover:text-primary text-sm font-semibold flex items-center gap-1 transition-colors">
                        <span class="material-symbols-outlined text-base">visibility</span>
                        {{ __('messages.view_hospital') }}
                    </a>
                </div>
                @endif
                <div class="flex items-center gap-3">
                    <a href="{{ route('hospital.edit', $hospital['id']) }}" class="text-primary text-sm font-semibold hover:underline flex items-center gap-1">
                        <span class="material-symbols-outlined text-base">edit</span>
                        {{ __('messages.edit') }}
                    </a>
                    @if($isSuperAdmin)
                    <button onclick="confirmDelete('{{ $hospital['id'] }}', '{{ addslashes($hospital['name'] ?? '') }}')" class="text-red-500 text-sm font-semibold hover:underline flex items-center gap-1">
                        <span class="material-symbols-outlined text-base">delete</span>
                        {{ __('messages.delete') }}
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @endforeach

        {{-- Add Hospital Card --}}
        @if($isSuperAdmin)
        <a href="{{ route('hospital.create') }}" class="border-2 border-dashed border-border-light dark:border-border-dark rounded-2xl p-8 flex flex-col items-center justify-center gap-3 text-text-sub-light dark:text-text-sub-dark hover:border-primary hover:text-primary transition-colors">
            <span class="material-symbols-outlined text-4xl">add_circle</span>
            <span class="font-semibold">{{ __('messages.add_hospital') }}</span>
        </a>
        @endif
    </div>
    @endif
</div>

{{-- Delete Modal --}}
<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-surface-dark rounded-2xl p-6 max-w-sm mx-4 w-full border border-border-light dark:border-border-dark shadow-2xl">
        <h3 class="text-lg font-bold text-text-main-light dark:text-white mb-2">{{ __('messages.confirm_delete') }}</h3>
        <p class="text-sm text-text-sub-light dark:text-text-sub-dark mb-6" id="deleteMessage"></p>
        <div class="flex gap-3 justify-end">
            <button onclick="closeDeleteModal()" class="px-4 py-2 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium hover:bg-background-light dark:hover:bg-white/5 transition-colors">{{ __('messages.cancel') }}</button>
            <button onclick="executeDelete()" class="px-4 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white text-sm font-bold transition-colors">{{ __('messages.delete') }}</button>
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
