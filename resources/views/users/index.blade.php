@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto" x-data="usersPage()">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ __('messages.user_management') }}</h1>
            <p class="text-sm text-[#637388] dark:text-[#9ca3af] mt-1">{{ __('messages.manage_staff_users') }}</p>
        </div>
        <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium text-sm transition-colors">
            <span class="material-symbols-outlined text-lg">person_add</span>
            {{ __('messages.add_user') }}
        </a>
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

    {{-- Search --}}
    <div class="mb-4">
        <input type="text" x-model="searchQuery" placeholder="{{ __('messages.search') }}..." class="w-full sm:w-80 px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#1a222e] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary">
    </div>

    {{-- Users Table --}}
    <div class="bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-[#222b3a]">
                    <tr>
                        <th class="text-start px-4 py-3 font-medium text-[#637388] dark:text-[#9ca3af]">{{ __('messages.name') }}</th>
                        <th class="text-start px-4 py-3 font-medium text-[#637388] dark:text-[#9ca3af]">{{ __('messages.email') }}</th>
                        <th class="text-start px-4 py-3 font-medium text-[#637388] dark:text-[#9ca3af]">{{ __('messages.role') }}</th>
                        <th class="text-start px-4 py-3 font-medium text-[#637388] dark:text-[#9ca3af]">{{ __('messages.clinic') }}</th>
                        <th class="text-start px-4 py-3 font-medium text-[#637388] dark:text-[#9ca3af]">{{ __('messages.status') }}</th>
                        <th class="text-end px-4 py-3 font-medium text-[#637388] dark:text-[#9ca3af]">{{ __('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2d3748]">
                    @forelse($users as $user)
                    <tr x-show="matchesSearch('{{ addslashes($user['name'] ?? '') }}', '{{ addslashes($user['email'] ?? '') }}')" class="hover:bg-gray-50 dark:hover:bg-[#222b3a] transition-colors">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="size-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold text-xs">
                                    {{ strtoupper(substr($user['name'] ?? 'U', 0, 1)) }}
                                </div>
                                <span class="font-medium text-[#111418] dark:text-white">{{ $user['name'] ?? '-' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-[#637388] dark:text-[#9ca3af]">{{ $user['email'] ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $roleBadges = [
                                    'super_admin' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
                                    'hospital_manager' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    'clinic_admin' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
                                    'reception' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    'doctor' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
                                ];
                                $badge = $roleBadges[$user['role'] ?? ''] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $badge }}">
                                {{ __('messages.' . ($user['role'] ?? 'patient')) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-[#637388] dark:text-[#9ca3af]">{{ $user['clinic_id'] ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if($user['is_active'] ?? true)
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">{{ __('messages.active') }}</span>
                            @else
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('messages.inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('users.edit', $user['id']) }}" class="p-1.5 rounded-lg text-[#637388] hover:text-primary hover:bg-primary/10 transition-colors">
                                    <span class="material-symbols-outlined text-lg">edit</span>
                                </a>
                                <button @click="deleteUser('{{ $user['id'] }}')" class="p-1.5 rounded-lg text-[#637388] hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-[#637388] dark:text-[#9ca3af]">
                            {{ __('messages.no_users_found') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function usersPage() {
    return {
        searchQuery: '',
        matchesSearch(name, email) {
            if (!this.searchQuery) return true;
            const q = this.searchQuery.toLowerCase();
            return name.toLowerCase().includes(q) || email.toLowerCase().includes(q);
        },
        async deleteUser(id) {
            if (!confirm('{{ __("messages.confirm_delete_user") }}')) return;
            try {
                const res = await fetch('/users/' + id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Error');
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }
    };
}
</script>
@endsection
