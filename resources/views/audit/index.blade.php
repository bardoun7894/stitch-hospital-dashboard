@extends('layouts.app')

@section('content')
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="bg-gradient-to-br from-primary/10 to-indigo-500/10 rounded-xl p-2.5 flex items-center justify-center border border-primary/10 shadow-sm">
                <span class="material-symbols-outlined text-primary text-2xl">history</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight">{{ __('messages.audit_logs') }}</h1>
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.audit_logs_subtitle') }}</p>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-4 shadow-sm">
        <form method="GET" action="{{ route('audit.index') }}" class="flex flex-wrap items-end gap-4">
            {{-- Action Filter --}}
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-1.5">{{ __('messages.filter_by_action') }}</label>
                <select name="action" class="w-full px-3 py-2 rounded-xl border border-border-light dark:border-border-dark bg-background-light dark:bg-white/5 text-sm text-text-main-light dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                    <option value="">{{ __('messages.all_actions') }}</option>
                    <option value="login" {{ ($filters['action'] ?? '') === 'login' ? 'selected' : '' }}>{{ __('messages.action_login') }}</option>
                    <option value="logout" {{ ($filters['action'] ?? '') === 'logout' ? 'selected' : '' }}>{{ __('messages.action_logout') }}</option>
                    <option value="hospital.created" {{ ($filters['action'] ?? '') === 'hospital.created' ? 'selected' : '' }}>{{ __('messages.action_hospital_created') }}</option>
                    <option value="hospital.updated" {{ ($filters['action'] ?? '') === 'hospital.updated' ? 'selected' : '' }}>{{ __('messages.action_hospital_updated') }}</option>
                    <option value="hospital.deleted" {{ ($filters['action'] ?? '') === 'hospital.deleted' ? 'selected' : '' }}>{{ __('messages.action_hospital_deleted') }}</option>
                    <option value="hospital.approved" {{ ($filters['action'] ?? '') === 'hospital.approved' ? 'selected' : '' }}>{{ __('messages.action_hospital_approved') }}</option>
                    <option value="hospital.rejected" {{ ($filters['action'] ?? '') === 'hospital.rejected' ? 'selected' : '' }}>{{ __('messages.action_hospital_rejected') }}</option>
                    <option value="booking.accepted" {{ ($filters['action'] ?? '') === 'booking.accepted' ? 'selected' : '' }}>{{ __('messages.action_booking_accepted') }}</option>
                    <option value="booking.rejected" {{ ($filters['action'] ?? '') === 'booking.rejected' ? 'selected' : '' }}>{{ __('messages.action_booking_rejected') }}</option>
                    <option value="booking.confirmed" {{ ($filters['action'] ?? '') === 'booking.confirmed' ? 'selected' : '' }}>{{ __('messages.action_booking_confirmed') }}</option>
                    <option value="booking.cancelled" {{ ($filters['action'] ?? '') === 'booking.cancelled' ? 'selected' : '' }}>{{ __('messages.action_booking_cancelled') }}</option>
                    <option value="user.created" {{ ($filters['action'] ?? '') === 'user.created' ? 'selected' : '' }}>{{ __('messages.action_user_created') }}</option>
                    <option value="user.updated" {{ ($filters['action'] ?? '') === 'user.updated' ? 'selected' : '' }}>{{ __('messages.action_user_updated') }}</option>
                    <option value="user.deleted" {{ ($filters['action'] ?? '') === 'user.deleted' ? 'selected' : '' }}>{{ __('messages.action_user_deleted') }}</option>
                    <option value="clinic.created" {{ ($filters['action'] ?? '') === 'clinic.created' ? 'selected' : '' }}>{{ __('messages.action_clinic_created') }}</option>
                    <option value="clinic.updated" {{ ($filters['action'] ?? '') === 'clinic.updated' ? 'selected' : '' }}>{{ __('messages.action_clinic_updated') }}</option>
                    <option value="clinic.deleted" {{ ($filters['action'] ?? '') === 'clinic.deleted' ? 'selected' : '' }}>{{ __('messages.action_clinic_deleted') }}</option>
                    <option value="doctor.created" {{ ($filters['action'] ?? '') === 'doctor.created' ? 'selected' : '' }}>{{ __('messages.action_doctor_created') }}</option>
                    <option value="doctor.updated" {{ ($filters['action'] ?? '') === 'doctor.updated' ? 'selected' : '' }}>{{ __('messages.action_doctor_updated') }}</option>
                    <option value="doctor.deleted" {{ ($filters['action'] ?? '') === 'doctor.deleted' ? 'selected' : '' }}>{{ __('messages.action_doctor_deleted') }}</option>
                </select>
            </div>

            {{-- Date From --}}
            <div class="min-w-[160px]">
                <label class="block text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-1.5">{{ __('messages.filter_by_date') }}</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full px-3 py-2 rounded-xl border border-border-light dark:border-border-dark bg-background-light dark:bg-white/5 text-sm text-text-main-light dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
            </div>

            {{-- Date To --}}
            <div class="min-w-[160px]">
                <label class="block text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-1.5">{{ __('messages.end_date') }}</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full px-3 py-2 rounded-xl border border-border-light dark:border-border-dark bg-background-light dark:bg-white/5 text-sm text-text-main-light dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
            </div>

            {{-- Search Button --}}
            <div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary-hover shadow-sm flex items-center gap-2 transition-all hover:shadow-md active:scale-95">
                    <span class="material-symbols-outlined text-sm">search</span>
                    {{ __('messages.search') }}
                </button>
            </div>

            {{-- Clear Filters --}}
            @if(!empty($filters['action']) || !empty($filters['date_from']) || !empty($filters['date_to']))
            <div>
                <a href="{{ route('audit.index') }}" class="px-4 py-2 rounded-xl border border-border-light dark:border-border-dark bg-white dark:bg-surface-dark text-sm font-medium text-text-main-light dark:text-white hover:bg-background-light dark:hover:bg-white/5 transition-colors shadow-sm inline-flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">clear</span>
                    {{ __('messages.all') }}
                </a>
            </div>
            @endif
        </form>
    </div>

    {{-- Logs Table --}}
    <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-background-light dark:bg-white/5 border-b border-border-light dark:border-border-dark">
                        <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.date_label') }}</th>
                        <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.name') }}</th>
                        <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.role') }}</th>
                        <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.action') }}</th>
                        <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.details') }}</th>
                        <th class="px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.ip_address') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-light dark:divide-border-dark">
                    @forelse($logs as $log)
                        @php
                            $action = $log['action'] ?? '';
                            $actionParts = explode('.', $action);
                            $actionType = $actionParts[1] ?? $actionParts[0] ?? '';

                            // Determine badge color based on action type
                            $badgeColor = match(true) {
                                in_array($actionType, ['login']) => 'blue',
                                in_array($actionType, ['logout']) => 'slate',
                                in_array($actionType, ['created']) => 'green',
                                in_array($actionType, ['updated']) => 'amber',
                                in_array($actionType, ['deleted']) => 'red',
                                in_array($actionType, ['approved', 'accepted', 'confirmed']) => 'emerald',
                                in_array($actionType, ['rejected', 'cancelled']) => 'rose',
                                default => 'slate',
                            };

                            // Also handle single-word actions like "login", "logout"
                            if ($action === 'login') $badgeColor = 'blue';
                            if ($action === 'logout') $badgeColor = 'slate';

                            // Role label
                            $roleKey = $log['user_role'] ?? '';
                            $roleLabel = __('messages.' . $roleKey);
                            if ($roleLabel === 'messages.' . $roleKey) {
                                $roleLabel = $roleKey;
                            }

                            // Action label
                            $actionLabelKey = 'messages.action_' . str_replace('.', '_', $action);
                            $actionLabel = __($actionLabelKey);
                            if ($actionLabel === $actionLabelKey) {
                                $actionLabel = $action;
                            }

                            // Timestamp formatting
                            $timestamp = $log['timestamp'] ?? '';
                            $formattedTime = $timestamp;
                            if ($timestamp) {
                                try {
                                    $dt = new \DateTime($timestamp);
                                    $formattedTime = $dt->format('Y-m-d H:i:s');
                                } catch (\Exception $e) {
                                    $formattedTime = $timestamp;
                                }
                            }

                            // Details
                            $details = $log['details'] ?? [];
                        @endphp
                        <tr class="hover:bg-background-light dark:hover:bg-white/5 transition-colors group">
                            {{-- Date/Time --}}
                            <td class="px-6 py-4">
                                <span class="text-sm font-medium text-text-main-light dark:text-white whitespace-nowrap">{{ $formattedTime }}</span>
                            </td>

                            {{-- User --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="size-8 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xs font-bold">
                                        {{ mb_substr($log['user_name'] ?? '?', 0, 1) }}
                                    </div>
                                    <span class="text-sm font-semibold text-text-main-light dark:text-white">{{ $log['user_name'] ?? __('messages.unknown_user') }}</span>
                                </div>
                            </td>

                            {{-- Role --}}
                            <td class="px-6 py-4">
                                <span class="text-xs font-medium text-text-sub-light dark:text-text-sub-dark bg-background-light dark:bg-white/5 px-2 py-1 rounded-md">{{ $roleLabel }}</span>
                            </td>

                            {{-- Action Badge --}}
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-{{ $badgeColor }}-50 dark:bg-{{ $badgeColor }}-500/10 text-{{ $badgeColor }}-700 dark:text-{{ $badgeColor }}-400 border border-{{ $badgeColor }}-100 dark:border-{{ $badgeColor }}-500/20">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-{{ $badgeColor }}-500"></span>
                                    </span>
                                    {{ $actionLabel }}
                                </span>
                            </td>

                            {{-- Details --}}
                            <td class="px-6 py-4">
                                @if(!empty($details) && is_array($details))
                                    <div x-data="{ expanded: false }" class="max-w-xs">
                                        <button @click="expanded = !expanded" class="text-xs text-primary hover:text-primary-hover font-medium flex items-center gap-1 transition-colors">
                                            <span class="material-symbols-outlined text-sm" x-text="expanded ? 'expand_less' : 'expand_more'">expand_more</span>
                                            <span x-text="expanded ? '{{ __('messages.close') }}' : '{{ __('messages.view_details') }}'">{{ __('messages.view_details') }}</span>
                                        </button>
                                        <div x-show="expanded" x-collapse style="display:none" class="mt-2 space-y-1">
                                            @foreach($details as $key => $value)
                                                <div class="flex items-start gap-2 text-xs">
                                                    <span class="font-semibold text-text-sub-light dark:text-text-sub-dark whitespace-nowrap">{{ $key }}:</span>
                                                    <span class="text-text-main-light dark:text-white break-all">{{ is_array($value) ? json_encode($value) : $value }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-text-sub-light/50">-</span>
                                @endif
                            </td>

                            {{-- IP Address --}}
                            <td class="px-6 py-4">
                                <span class="text-xs text-text-sub-light dark:text-text-sub-dark font-mono">{{ $log['ip_address'] ?? '-' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="material-symbols-outlined text-4xl text-border-light dark:text-border-dark mb-2">history</span>
                                    <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.no_audit_logs') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-border-light dark:border-border-dark flex items-center justify-between bg-background-light/30 dark:bg-white/5">
            <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.showing_logs', ['count' => count($logs)]) }}</p>
        </div>
    </div>

</div>
@endsection
