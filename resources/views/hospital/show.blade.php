@extends('layouts.app')

@php
    use App\Http\Middleware\RoleMiddleware;
    $isSuperAdmin = (RoleMiddleware::getCurrentUser()['role'] ?? '') === 'super_admin';
    $status = $hospital['status'] ?? 'active';
    $statusConfig = match($status) {
        'waiting' => ['label' => __('messages.waiting'), 'bg' => 'bg-blue-100 dark:bg-blue-900/30', 'text' => 'text-blue-700 dark:text-blue-400', 'icon' => 'schedule'],
        'pending' => ['label' => __('messages.hospital_pending'), 'bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-700 dark:text-amber-400', 'icon' => 'hourglass_top'],
        'active' => ['label' => __('messages.active'), 'bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-700 dark:text-emerald-400', 'icon' => 'check_circle'],
        'blocked' => ['label' => __('messages.blocked'), 'bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-700 dark:text-red-400', 'icon' => 'block'],
        'rejected' => ['label' => __('messages.hospital_rejected'), 'bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-700 dark:text-red-400', 'icon' => 'cancel'],
        'inactive' => ['label' => __('messages.inactive'), 'bg' => 'bg-gray-100 dark:bg-gray-800', 'text' => 'text-gray-600 dark:text-gray-400', 'icon' => 'pause_circle'],
        default => ['label' => $status, 'bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'icon' => 'help'],
    };
@endphp

@section('content')
<div class="flex flex-col gap-6" x-data="{ showClinicModal: false, showDoctorModal: false, selectedClinicId: '', selectedClinicName: '' }">
    {{-- Alerts --}}
    @if(session('success'))
    <div class="px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 rounded-2xl text-sm border border-emerald-100 dark:border-emerald-900/30 flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">check_circle</span> {{ session('success') }}
    </div>
    @endif

    {{-- Approval Banner (super_admin only, pending hospitals) --}}
    @if($isSuperAdmin && $status === 'pending')
    <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-900/30 rounded-2xl p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/30 text-amber-600">
                    <span class="material-symbols-outlined text-2xl">pending_actions</span>
                </div>
                <div>
                    <h3 class="font-bold text-text-main-light dark:text-white">{{ __('messages.hospital_pending') }}</h3>
                    <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.hospital_submit_note') }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <form action="{{ route('hospital.approve', $hospital['id']) }}" method="POST" onsubmit="return confirm('{{ __('messages.confirm_approve') }}')">
                    @csrf
                    <button type="submit" class="h-10 px-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm transition-colors">
                        <span class="material-symbols-outlined text-lg">check_circle</span>
                        {{ __('messages.approve_hospital') }}
                    </button>
                </form>
                <button onclick="document.getElementById('reject-modal').classList.remove('hidden')" class="h-10 px-4 inline-flex items-center gap-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-bold text-sm transition-colors">
                    <span class="material-symbols-outlined text-lg">cancel</span>
                    {{ __('messages.reject_hospital') }}
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Rejection Reason Banner --}}
    @if($status === 'rejected' && ($hospital['rejection_reason'] ?? null))
    <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-900/30 rounded-2xl p-5">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-red-500 text-xl mt-0.5">error</span>
            <div class="flex-1">
                <h3 class="font-bold text-red-800 dark:text-red-300">{{ __('messages.hospital_rejected') }}</h3>
                <p class="text-sm text-red-700 dark:text-red-400 mt-1">{{ __('messages.rejection_reason') }}: {{ $hospital['rejection_reason'] }}</p>
            </div>
            @if(!$isSuperAdmin)
            <form action="{{ route('hospital.resubmit', $hospital['id']) }}" method="POST">
                @csrf
                <button type="submit" class="h-10 px-4 inline-flex items-center gap-2 rounded-lg bg-primary hover:bg-primary-hover text-white font-bold text-sm transition-colors">
                    <span class="material-symbols-outlined text-lg">refresh</span>
                    {{ __('messages.resubmit_hospital') }}
                </button>
            </form>
            @endif
        </div>
    </div>
    @endif

    {{-- Header Card --}}
    <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm p-6 border border-border-light dark:border-border-dark">
        <div class="flex flex-col md:flex-row gap-5 justify-between items-start md:items-center">
            <div class="flex items-center gap-4">
                @if(!empty($hospital['logo_url']))
                <img src="{{ $hospital['logo_url'] }}" alt="{{ $hospital['name'] ?? '' }}" class="size-16 rounded-xl object-cover border-2 border-border-light dark:border-border-dark">
                @else
                <div class="p-3 rounded-xl bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-3xl">domain</span>
                </div>
                @endif
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-bold text-text-main-light dark:text-white">{{ $hospital['name'] ?? '' }}</h1>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">{{ $statusConfig['icon'] }}</span>
                            {{ $statusConfig['label'] }}
                        </span>
                    </div>
                    @if($hospital['name_en'] ?? null)
                    <p class="text-sm text-text-sub-light dark:text-text-sub-dark mt-0.5">{{ $hospital['name_en'] }}</p>
                    @endif
                    <div class="flex items-center gap-4 text-sm text-text-sub-light dark:text-text-sub-dark mt-1 flex-wrap">
                        @if($hospital['address'] ?? null)
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">location_on</span> {{ $hospital['address'] }}</span>
                        @endif
                        @if($hospital['phone'] ?? null)
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">phone</span> {{ $hospital['phone'] }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex gap-3 items-center flex-wrap">
                {{-- Status Change Dropdown (super_admin only) --}}
                @if($isSuperAdmin)
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="h-10 px-4 inline-flex items-center justify-center gap-2 rounded-lg bg-background-light dark:bg-white/5 text-text-main-light dark:text-white hover:bg-border-light dark:hover:bg-white/10 font-bold text-sm transition-colors border border-border-light dark:border-border-dark">
                        <span class="material-symbols-outlined text-[18px]">swap_horiz</span> {{ __('messages.change_status') }}
                        <span class="material-symbols-outlined text-[16px]" :class="open ? 'rotate-180' : ''">expand_more</span>
                    </button>
                    <div x-show="open" @click.away="open = false" style="display:none" x-transition class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-48 bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-xl shadow-lg z-50 overflow-hidden">
                        @php
                            $statusOptions = [
                                'active' => ['label' => __('messages.active'), 'icon' => 'check_circle', 'color' => 'text-emerald-600'],
                                'waiting' => ['label' => __('messages.waiting'), 'icon' => 'schedule', 'color' => 'text-blue-600'],
                                'blocked' => ['label' => __('messages.blocked'), 'icon' => 'block', 'color' => 'text-red-600'],
                                'inactive' => ['label' => __('messages.inactive'), 'icon' => 'pause_circle', 'color' => 'text-gray-500'],
                                'pending' => ['label' => __('messages.filter_pending'), 'icon' => 'hourglass_top', 'color' => 'text-amber-600'],
                            ];
                        @endphp
                        @foreach($statusOptions as $sKey => $sOpt)
                            @if($sKey !== $status)
                            <form action="{{ route('hospital.change-status', $hospital['id']) }}" method="POST">
                                @csrf
                                <input type="hidden" name="status" value="{{ $sKey }}">
                                <button type="submit" class="w-full flex items-center gap-2 px-4 py-2.5 text-sm font-medium hover:bg-background-light dark:hover:bg-white/5 transition-colors {{ $sOpt['color'] }}">
                                    <span class="material-symbols-outlined text-lg">{{ $sOpt['icon'] }}</span>
                                    {{ $sOpt['label'] }}
                                </button>
                            </form>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif

                <a href="{{ route('hospital.edit', $hospital['id']) }}" class="h-10 px-4 inline-flex items-center justify-center gap-2 rounded-lg bg-background-light dark:bg-white/5 text-text-main-light dark:text-white hover:bg-border-light dark:hover:bg-white/10 font-bold text-sm transition-colors border border-border-light dark:border-border-dark">
                    <span class="material-symbols-outlined text-[18px]">edit</span> {{ __('messages.edit') }}
                </a>
                <a href="{{ route('hospital.index') }}" class="h-10 px-4 inline-flex items-center justify-center gap-2 rounded-lg bg-primary hover:bg-primary-hover text-white font-bold text-sm transition-colors shadow-md shadow-primary/20">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span> {{ __('messages.back') }}
                </a>
            </div>
        </div>
    </div>

    {{-- Setup Wizard (show when in setup mode or hospital is pending with incomplete setup) --}}
    @if($isSetupMode || ($status === 'pending' && (!$setupProgress['has_clinics'] || !$setupProgress['has_doctors'])))
    <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark p-6">
        <h3 class="font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">rocket_launch</span> {{ __('messages.setup_progress') }}
        </h3>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-0">
            @php
                $steps = [
                    ['key' => 'hospital_created', 'done' => true, 'label' => __('messages.setup_step_hospital'), 'icon' => 'domain'],
                    ['key' => 'has_clinics', 'done' => $setupProgress['has_clinics'], 'label' => __('messages.setup_step_clinics'), 'icon' => 'emergency_home'],
                    ['key' => 'has_doctors', 'done' => $setupProgress['has_doctors'], 'label' => __('messages.setup_step_doctors'), 'icon' => 'stethoscope'],
                    ['key' => 'review', 'done' => $status === 'active', 'label' => __('messages.setup_step_review'), 'icon' => 'verified'],
                ];
            @endphp
            @foreach($steps as $i => $step)
            <div class="flex items-center gap-3 flex-1 {{ $i > 0 ? 'sm:pl-4' : '' }}">
                @if($i > 0)
                <div class="hidden sm:block w-8 h-0.5 {{ $step['done'] ? 'bg-success-green' : 'bg-border-light dark:bg-border-dark' }}"></div>
                @endif
                <div class="flex items-center gap-2 min-w-0">
                    <div class="size-8 rounded-full flex items-center justify-center shrink-0 {{ $step['done'] ? 'bg-emerald-100 dark:bg-emerald-900/30 text-success-green' : 'bg-background-light dark:bg-white/5 text-text-sub-light' }}">
                        <span class="material-symbols-outlined text-lg">{{ $step['done'] ? 'check_circle' : $step['icon'] }}</span>
                    </div>
                    <span class="text-sm font-medium {{ $step['done'] ? 'text-success-green' : 'text-text-sub-light dark:text-text-sub-dark' }} truncate">{{ $step['label'] }}</span>
                </div>
            </div>
            @endforeach
        </div>
        {{-- Hint for next step --}}
        @if(!$setupProgress['has_clinics'])
        <div class="mt-4 flex items-center gap-2 text-sm text-primary">
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
            <span>{{ __('messages.setup_add_clinic_hint') }}</span>
            <button type="button" @click="showClinicModal = true" class="font-bold hover:underline">{{ __('messages.add_clinic') }}</button>
        </div>
        @elseif(!$setupProgress['has_doctors'])
        <div class="mt-4 flex items-center gap-2 text-sm text-primary">
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
            <span>{{ __('messages.setup_add_doctor_hint') }}</span>
        </div>
        @elseif($status === 'pending')
        <div class="mt-4 flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
            <span class="material-symbols-outlined text-lg">schedule</span>
            <span>{{ __('messages.setup_awaiting_review') }}</span>
        </div>
        @endif
    </div>
    @endif

    {{-- Main Content --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Clinics --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center bg-background-light/30 dark:bg-white/5">
                    <h3 class="font-bold text-lg text-text-main-light dark:text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">emergency_home</span> {{ __('messages.hospital_clinics') }}
                    </h3>
                    <button type="button" @click="showClinicModal = true" class="text-sm text-primary hover:text-primary-hover font-bold hover:underline flex items-center gap-1 transition-colors">
                        <span class="material-symbols-outlined text-lg">add</span> {{ __('messages.add_clinic') }}
                    </button>
                </div>

                @if(count($clinics) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
                    @foreach($clinics as $clinic)
                    <div class="bg-white dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark overflow-hidden" x-data="{ expanded: false }">
                        {{-- Clinic Header --}}
                        <div class="p-4">
                            <div class="flex items-start gap-3">
                                <div class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                    <span class="material-symbols-outlined">emergency_home</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <a href="{{ route('clinics.show', $clinic['id']) }}" class="font-bold text-text-main-light dark:text-white truncate block hover:text-primary transition-colors">{{ $clinic['name'] ?? $clinic['id'] }}</a>
                                    @if($clinic['specialty'] ?? null)
                                    <p class="text-xs text-text-sub-light dark:text-text-sub-dark mt-0.5">{{ $clinic['specialty'] }}</p>
                                    @endif
                                    <div class="flex items-center gap-3 mt-2">
                                        <span class="text-xs text-text-sub-light dark:text-text-sub-dark flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">stethoscope</span>
                                            {{ __('messages.doctors_count', ['count' => $clinic['doctor_count']]) }}
                                        </span>
                                        @php
                                            $cStatus = $clinic['status'] ?? 'active';
                                            $cStatusClass = match($cStatus) {
                                                'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                                'paused' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                                default => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                            };
                                        @endphp
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $cStatusClass }}">{{ __('messages.' . $cStatus) }}</span>
                                    </div>
                                </div>
                                <button type="button" @click="expanded = !expanded" class="p-1 rounded-lg hover:bg-background-light dark:hover:bg-white/5 text-text-sub-light transition-colors">
                                    <span class="material-symbols-outlined text-lg transition-transform" :class="expanded ? 'rotate-180' : ''">expand_more</span>
                                </button>
                            </div>
                        </div>

                        {{-- Expandable Doctors Section --}}
                        <div x-show="expanded" x-collapse style="display:none">
                            <div class="border-t border-border-light dark:border-border-dark px-4 py-3 bg-background-light/30 dark:bg-white/[0.02]">
                                @if(count($clinic['doctors'] ?? []) > 0)
                                <div class="space-y-2">
                                    @foreach($clinic['doctors'] as $doctor)
                                    <div class="flex items-center gap-3 p-2 rounded-lg bg-white dark:bg-surface-dark border border-border-light/50 dark:border-border-dark/50">
                                        <div class="size-8 rounded-full bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-sm">person</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-text-main-light dark:text-white truncate">{{ $doctor['name'] ?? '' }}</p>
                                            <p class="text-xs text-text-sub-light dark:text-text-sub-dark truncate">{{ $doctor['specialty'] ?? '' }}</p>
                                        </div>
                                        @php
                                            $dStatus = $doctor['status'] ?? 'off';
                                            $dStatusClass = match($dStatus) {
                                                'available' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                                'busy' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                                default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                                            };
                                        @endphp
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $dStatusClass }} shrink-0">{{ __('messages.' . $dStatus) }}</span>
                                    </div>
                                    @endforeach
                                </div>
                                @else
                                <p class="text-xs text-text-sub-light dark:text-text-sub-dark text-center py-2">{{ __('messages.no_doctors_yet') }}</p>
                                @endif

                                {{-- Add Doctor Button --}}
                                <button type="button"
                                    @click="selectedClinicId = '{{ $clinic['id'] }}'; selectedClinicName = '{{ addslashes($clinic['name'] ?? $clinic['id']) }}'; showDoctorModal = true"
                                    class="mt-2 w-full flex items-center justify-center gap-1.5 py-2 rounded-lg border border-dashed border-border-light dark:border-border-dark text-xs font-medium text-text-sub-light dark:text-text-sub-dark hover:text-primary hover:border-primary/50 transition-colors">
                                    <span class="material-symbols-outlined text-sm">add</span> {{ __('messages.add_doctor') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    {{-- Add Clinic dashed card --}}
                    <button type="button" @click="showClinicModal = true" class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border-light dark:border-border-dark p-6 text-text-sub-light dark:text-text-sub-dark hover:text-primary hover:border-primary/50 transition-colors">
                        <span class="material-symbols-outlined text-2xl">add_circle</span>
                        <span class="text-sm font-medium">{{ __('messages.add_clinic') }}</span>
                    </button>
                </div>
                @else
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined text-4xl text-border-light dark:text-border-dark mb-3 block">emergency_home</span>
                    <p class="text-text-sub-light dark:text-text-sub-dark font-medium">{{ __('messages.no_clinics_yet') }}</p>
                    <p class="text-sm text-text-sub-light/70 dark:text-text-sub-dark/70 mt-1">{{ __('messages.add_first_clinic') }}</p>
                    <button type="button" @click="showClinicModal = true" class="mt-4 inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white font-bold text-sm px-4 py-2.5 rounded-lg transition-colors shadow-md shadow-primary/20">
                        <span class="material-symbols-outlined text-lg">add</span> {{ __('messages.add_clinic') }}
                    </button>
                </div>
                @endif
            </div>
        </div>

        {{-- Right Column: Sidebar --}}
        <div class="flex flex-col gap-6">
            {{-- Hospital Info --}}
            <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark">
                <div class="px-5 py-4 border-b border-border-light dark:border-border-dark">
                    <h3 class="font-bold text-lg text-text-main-light dark:text-white">{{ __('messages.hospital_info') }}</h3>
                </div>
                <div class="p-4 space-y-3">
                    @if($hospital['address'] ?? null)
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-text-sub-light text-lg mt-0.5">location_on</span>
                        <div>
                            <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.address') }}</p>
                            <p class="text-sm text-text-main-light dark:text-white">{{ $hospital['address'] }}</p>
                        </div>
                    </div>
                    @endif
                    @if($hospital['phone'] ?? null)
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-text-sub-light text-lg mt-0.5">phone</span>
                        <div>
                            <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.phone') }}</p>
                            <p class="text-sm text-text-main-light dark:text-white" dir="ltr">{{ $hospital['phone'] }}</p>
                        </div>
                    </div>
                    @endif
                    @if($hospital['contact_person'] ?? null)
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-text-sub-light text-lg mt-0.5">person</span>
                        <div>
                            <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.contact_person') }}</p>
                            <p class="text-sm text-text-main-light dark:text-white">{{ $hospital['contact_person'] }}</p>
                        </div>
                    </div>
                    @endif
                    @if($hospital['created_at'] ?? null)
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-text-sub-light text-lg mt-0.5">calendar_today</span>
                        <div>
                            <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.submitted_at') }}</p>
                            <p class="text-sm text-text-main-light dark:text-white">{{ $hospital['created_at'] }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Quick Stats --}}
            <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark">
                <div class="px-5 py-4 border-b border-border-light dark:border-border-dark">
                    <h3 class="font-bold text-lg text-text-main-light dark:text-white">{{ __('messages.quick_stats') }}</h3>
                </div>
                <div class="divide-y divide-border-light dark:divide-border-dark">
                    <div class="p-4 flex items-center justify-between">
                        <span class="text-sm text-text-sub-light dark:text-text-sub-dark flex items-center gap-2">
                            <span class="material-symbols-outlined text-indigo-500 text-lg">emergency_home</span> {{ __('messages.total_clinics') }}
                        </span>
                        <span class="text-lg font-bold text-text-main-light dark:text-white">{{ count($clinics) }}</span>
                    </div>
                    <div class="p-4 flex items-center justify-between">
                        <span class="text-sm text-text-sub-light dark:text-text-sub-dark flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-500 text-lg">stethoscope</span> {{ __('messages.total_doctors') }}
                        </span>
                        <span class="text-lg font-bold text-text-main-light dark:text-white">{{ $totalDoctors }}</span>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark">
                <div class="px-5 py-4 border-b border-border-light dark:border-border-dark">
                    <h3 class="font-bold text-lg text-text-main-light dark:text-white">{{ __('messages.quick_actions') }}</h3>
                </div>
                <div class="p-2 space-y-1">
                    <button type="button" @click="showClinicModal = true" class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-background-light dark:hover:bg-white/5 transition-colors group">
                        <div class="size-8 flex items-center justify-center bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined text-[20px]">add</span>
                        </div>
                        <span class="text-sm font-medium text-text-main-light dark:text-white">{{ __('messages.add_clinic') }}</span>
                    </button>
                    @if(count($clinics) > 0)
                    <button type="button" @click="selectedClinicId = '{{ $clinics[0]['id'] ?? '' }}'; selectedClinicName = '{{ addslashes($clinics[0]['name'] ?? '') }}'; showDoctorModal = true" class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-background-light dark:hover:bg-white/5 transition-colors group">
                        <div class="size-8 flex items-center justify-center bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 rounded group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined text-[20px]">stethoscope</span>
                        </div>
                        <span class="text-sm font-medium text-text-main-light dark:text-white">{{ __('messages.add_doctor') }}</span>
                    </button>
                    @endif
                    <a href="{{ route('users.create', ['hospital_id' => $hospital['id']]) }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-background-light dark:hover:bg-white/5 transition-colors group">
                        <div class="size-8 flex items-center justify-center bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined text-[20px]">person_add</span>
                        </div>
                        <span class="text-sm font-medium text-text-main-light dark:text-white">{{ __('messages.add_staff') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
@if($isSuperAdmin && $status === 'pending')
<div id="reject-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-2xl border border-border-light dark:border-border-dark w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-border-light dark:border-border-dark">
            <h3 class="font-bold text-text-main-light dark:text-white">{{ __('messages.reject_hospital') }}</h3>
        </div>
        <form action="{{ route('hospital.reject', $hospital['id']) }}" method="POST" class="p-6">
            @csrf
            <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.rejection_reason') }} *</label>
            <textarea name="rejection_reason" required rows="3" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.rejection_reason_placeholder') }}"></textarea>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="flex-1 h-10 inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-bold text-sm transition-colors">
                    {{ __('messages.reject_hospital') }}
                </button>
                <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')" class="flex-1 h-10 inline-flex items-center justify-center rounded-lg bg-background-light dark:bg-white/5 text-text-main-light dark:text-white font-bold text-sm border border-border-light dark:border-border-dark transition-colors hover:bg-border-light dark:hover:bg-white/10">
                    {{ __('messages.cancel') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Create Clinic Modal --}}
<div x-show="showClinicModal" x-cloak style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
     @click.self="showClinicModal = false"
     @keydown.escape.window="showClinicModal = false">
    <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-2xl border border-border-light dark:border-border-dark w-full max-w-lg overflow-hidden" @click.stop>
        <div class="px-6 py-4 border-b border-border-light dark:border-border-dark flex items-center justify-between">
            <h3 class="font-bold text-text-main-light dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">emergency_home</span> {{ __('messages.add_clinic') }}
            </h3>
            <button type="button" @click="showClinicModal = false" class="p-1 rounded-lg hover:bg-background-light dark:hover:bg-white/5 text-text-sub-light transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form action="{{ route('clinics.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="hospital_id" value="{{ $hospital['id'] }}">
            @if($isSetupMode)
            <input type="hidden" name="setup" value="1">
            @endif

            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.clinic_name') }} *</label>
                <input type="text" name="name" required class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.clinic_name') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.clinic_name_en') }}</label>
                <input type="text" name="name_en" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.clinic_name_en') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.specialty') }}</label>
                <input type="text" name="specialty" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.specialty') }}">
            </div>

            <input type="hidden" name="status" value="active">

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 h-10 inline-flex items-center justify-center gap-2 rounded-lg bg-primary hover:bg-primary-hover text-white font-bold text-sm transition-colors shadow-md shadow-primary/20">
                    <span class="material-symbols-outlined text-lg">add</span> {{ __('messages.save') }}
                </button>
                <button type="button" @click="showClinicModal = false" class="flex-1 h-10 inline-flex items-center justify-center rounded-lg bg-background-light dark:bg-white/5 text-text-main-light dark:text-white font-bold text-sm border border-border-light dark:border-border-dark transition-colors hover:bg-border-light dark:hover:bg-white/10">
                    {{ __('messages.cancel') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Create Doctor Modal --}}
<div x-show="showDoctorModal" x-cloak style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
     @click.self="showDoctorModal = false"
     @keydown.escape.window="showDoctorModal = false">
    <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-2xl border border-border-light dark:border-border-dark w-full max-w-lg overflow-hidden max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="px-6 py-4 border-b border-border-light dark:border-border-dark flex items-center justify-between sticky top-0 bg-white dark:bg-surface-dark z-10">
            <h3 class="font-bold text-text-main-light dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-teal-600">stethoscope</span>
                {{ __('messages.add_doctor') }}
                <span class="text-sm font-normal text-text-sub-light dark:text-text-sub-dark" x-text="selectedClinicName ? '- ' + selectedClinicName : ''"></span>
            </h3>
            <button type="button" @click="showDoctorModal = false" class="p-1 rounded-lg hover:bg-background-light dark:hover:bg-white/5 text-text-sub-light transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form action="{{ route('doctors.store') }}" method="POST" class="p-6 space-y-4" x-data="{ showCredentials: false }">
            @csrf
            <input type="hidden" name="clinic_id" :value="selectedClinicId">
            <input type="hidden" name="setup" value="1">

            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.doctor_name') }} *</label>
                <input type="text" name="name" required class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.doctor_name') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.doctor_name_en') }}</label>
                <input type="text" name="name_en" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.doctor_name_en') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.specialty') }} *</label>
                <input type="text" name="specialty" required class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="{{ __('messages.specialty') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.phone') }}</label>
                <input type="text" name="phone" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" dir="ltr" placeholder="{{ __('messages.phone') }}">
            </div>

            <input type="hidden" name="status" value="available">

            {{-- Optional: Login Credentials --}}
            <div class="border-t border-border-light dark:border-border-dark pt-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="generate_credentials" value="1" x-model="showCredentials" class="rounded border-border-light dark:border-border-dark text-primary focus:ring-primary/20">
                    <span class="text-sm font-medium text-text-main-light dark:text-white">{{ __('messages.generate_login_credentials') }}</span>
                </label>

                <div x-show="showCredentials" x-collapse style="display:none" class="mt-3 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.email') }} *</label>
                        <input type="email" name="login_email" :required="showCredentials" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" dir="ltr" placeholder="{{ __('messages.email') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.password') }} *</label>
                        <input type="password" name="login_password" :required="showCredentials" minlength="6" class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-background-light dark:bg-background-dark text-text-main-light dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" dir="ltr" placeholder="{{ __('messages.password') }}">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 h-10 inline-flex items-center justify-center gap-2 rounded-lg bg-primary hover:bg-primary-hover text-white font-bold text-sm transition-colors shadow-md shadow-primary/20">
                    <span class="material-symbols-outlined text-lg">add</span> {{ __('messages.save') }}
                </button>
                <button type="button" @click="showDoctorModal = false" class="flex-1 h-10 inline-flex items-center justify-center rounded-lg bg-background-light dark:bg-white/5 text-text-main-light dark:text-white font-bold text-sm border border-border-light dark:border-border-dark transition-colors hover:bg-border-light dark:hover:bg-white/10">
                    {{ __('messages.cancel') }}
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
