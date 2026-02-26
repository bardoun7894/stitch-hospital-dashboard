@extends('layouts.app')

@section('content')
<div class="flex flex-col gap-6" x-data="{ activeTab: 'visits' }">

    {{-- Success / Error Messages --}}
    @if(session('success'))
    <div class="px-4 py-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 rounded-xl text-sm font-medium border border-emerald-100 dark:border-emerald-900/30 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">check_circle</span>
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-xl text-sm font-medium border border-red-100 dark:border-red-900/30 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">error</span>
        {{ session('error') }}
    </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('patients.index') }}" class="p-2 rounded-lg hover:bg-background-light dark:hover:bg-white/5 transition-colors border border-border-light dark:border-border-dark">
                <span class="material-symbols-outlined text-text-sub-light dark:text-text-sub-dark">arrow_back</span>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-text-main-light dark:text-white">{{ $patient['name'] }}</h1>
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.patient_profile') }}</p>
            </div>
        </div>
        <a href="{{ route('patients.edit', $patient['id']) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-background-light dark:bg-white/5 text-text-main-light dark:text-white hover:bg-border-light dark:hover:bg-white/10 font-bold text-sm transition-colors border border-border-light dark:border-border-dark">
            <span class="material-symbols-outlined text-[18px]">edit</span>
            {{ __('messages.edit_patient') }}
        </a>
    </div>

    {{-- Patient Info Card --}}
    <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Personal Info --}}
            <div>
                <h3 class="text-sm font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">person</span>
                    {{ __('messages.patient_profile') }}
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="size-12 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-lg uppercase">
                            {{ substr($patient['name'], 0, 2) }}
                        </div>
                        <div>
                            <p class="font-bold text-text-main-light dark:text-white text-lg">{{ $patient['name'] }}</p>
                            <span class="bg-primary/10 text-primary text-xs font-bold px-2 py-0.5 rounded-md border border-primary/20">{{ __('messages.mrn_label') }} {{ $patient['id'] }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-[16px] text-text-sub-light">phone</span>
                            <span class="text-text-sub-light dark:text-text-sub-dark">{{ __('messages.phone') }}:</span>
                            <span class="font-medium text-text-main-light dark:text-white">{{ $patient['phone'] ?? '-' }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-[16px] text-text-sub-light">mail</span>
                            <span class="text-text-sub-light dark:text-text-sub-dark">{{ __('messages.email') }}:</span>
                            <span class="font-medium text-text-main-light dark:text-white">{{ $patient['email'] ?? '-' }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-[16px] text-text-sub-light">badge</span>
                            <span class="text-text-sub-light dark:text-text-sub-dark">{{ __('messages.national_id_label') }}:</span>
                            <span class="font-mono bg-background-light dark:bg-white/5 px-2 py-0.5 rounded text-xs font-medium text-text-main-light dark:text-white">{{ $patient['national_id'] ?? '-' }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-[16px] text-text-sub-light">{{ ($patient['gender'] ?? '') === 'Female' ? 'female' : 'male' }}</span>
                            <span class="text-text-sub-light dark:text-text-sub-dark">{{ __('messages.gender') }}:</span>
                            <span class="font-medium text-text-main-light dark:text-white">{{ $patient['gender'] ?? '-' }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-[16px] text-text-sub-light">cake</span>
                            <span class="text-text-sub-light dark:text-text-sub-dark">{{ __('messages.date_of_birth') }}:</span>
                            <span class="font-medium text-text-main-light dark:text-white">{{ $patient['dob'] ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Medical Info --}}
            <div>
                <h3 class="text-sm font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">medical_information</span>
                    {{ __('messages.medical_info') }}
                </h3>
                <div class="space-y-4">
                    {{-- Blood Type Badge --}}
                    @if(!empty($patient['blood_type']) && $patient['blood_type'] !== '-')
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-100 dark:border-red-900/30">
                        <span class="material-symbols-outlined text-[16px] text-alert-red">water_drop</span>
                        <span class="text-sm font-bold text-red-800 dark:text-red-300">{{ __('messages.blood_type') }}: {{ $patient['blood_type'] }}</span>
                    </div>
                    @endif

                    {{-- Allergies Tags --}}
                    <div>
                        <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-2">{{ __('messages.allergies') }}</p>
                        @php
                            $allergies = $patient['allergies'] ?? [];
                            if (!is_array($allergies)) $allergies = [];
                            // Support both simple string arrays and object arrays
                            $allergyNames = [];
                            foreach ($allergies as $a) {
                                $allergyNames[] = is_array($a) ? ($a['name'] ?? '') : (string)$a;
                            }
                            $allergyNames = array_filter($allergyNames);
                        @endphp
                        @if(count($allergyNames) > 0)
                        <div class="flex flex-wrap gap-2">
                            @foreach($allergyNames as $allergy)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-xs font-medium rounded-full border border-red-100 dark:border-red-900/30">
                                <span class="material-symbols-outlined text-[12px]">warning</span>
                                {{ $allergy }}
                            </span>
                            @endforeach
                        </div>
                        @else
                        <p class="text-sm text-text-sub-light dark:text-text-sub-dark italic">{{ __('messages.no_allergies') }}</p>
                        @endif
                    </div>

                    {{-- Chronic Conditions Tags --}}
                    <div>
                        <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-2">{{ __('messages.chronic_conditions') }}</p>
                        @php
                            $conditions = $patient['chronic_conditions'] ?? [];
                            if (!is_array($conditions)) $conditions = [];
                            $conditionNames = [];
                            foreach ($conditions as $c) {
                                $conditionNames[] = is_array($c) ? ($c['name'] ?? '') : (string)$c;
                            }
                            $conditionNames = array_filter($conditionNames);
                        @endphp
                        @if(count($conditionNames) > 0)
                        <div class="flex flex-wrap gap-2">
                            @foreach($conditionNames as $condition)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 text-xs font-medium rounded-full border border-amber-100 dark:border-amber-900/30">
                                <span class="material-symbols-outlined text-[12px]">healing</span>
                                {{ $condition }}
                            </span>
                            @endforeach
                        </div>
                        @else
                        <p class="text-sm text-text-sub-light dark:text-text-sub-dark italic">{{ __('messages.no_conditions') }}</p>
                        @endif
                    </div>
                </div>

                {{-- Emergency Contact --}}
                @php
                    $ec = $patient['emergency_contact'] ?? [];
                    $ecName = is_array($ec) ? ($ec['name'] ?? '-') : '-';
                    $ecPhone = is_array($ec) ? ($ec['phone'] ?? '-') : '-';
                    $ecRelation = is_array($ec) ? ($ec['relation'] ?? '-') : '-';
                @endphp
                @if($ecName !== '-' || $ecPhone !== '-')
                <div class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-100 dark:border-amber-900/50">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-alert-amber text-[18px]">emergency</span>
                        <span class="text-xs font-bold text-amber-800 dark:text-amber-200 uppercase tracking-wider">{{ __('messages.emergency_contact') }}</span>
                    </div>
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        {{ $ecName }} ({{ $ecRelation }}) &bull; {{ $ecPhone }}
                    </p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <div class="border-b border-border-light dark:border-border-dark bg-transparent">
        <nav aria-label="Tabs" class="-mb-px flex space-x-6 rtl:space-x-reverse overflow-x-auto no-scrollbar">
            <button @click="activeTab = 'visits'"
                :class="activeTab === 'visits' ? 'border-primary text-primary font-bold' : 'border-transparent text-text-sub-light dark:text-text-sub-dark hover:text-text-main-light dark:hover:text-white hover:border-border-light font-medium'"
                class="whitespace-nowrap py-4 px-1 border-b-2 text-sm flex items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-[20px]">history</span> {{ __('messages.visit_history') }}
                @if(count($visitHistory ?? []) > 0)
                <span class="bg-primary/10 text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ count($visitHistory) }}</span>
                @endif
            </button>
            <button @click="activeTab = 'treatments'"
                :class="activeTab === 'treatments' ? 'border-primary text-primary font-bold' : 'border-transparent text-text-sub-light dark:text-text-sub-dark hover:text-text-main-light dark:hover:text-white hover:border-border-light font-medium'"
                class="whitespace-nowrap py-4 px-1 border-b-2 text-sm flex items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-[20px]">healing</span> {{ __('messages.treatment_history') }}
                @if(count($treatmentPlans ?? []) > 0)
                <span class="bg-primary/10 text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ count($treatmentPlans) }}</span>
                @endif
            </button>
            <button @click="activeTab = 'prescriptions'"
                :class="activeTab === 'prescriptions' ? 'border-primary text-primary font-bold' : 'border-transparent text-text-sub-light dark:text-text-sub-dark hover:text-text-main-light dark:hover:text-white hover:border-border-light font-medium'"
                class="whitespace-nowrap py-4 px-1 border-b-2 text-sm flex items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-[20px]">pill</span> {{ __('messages.prescriptions_history') }}
                @if(count($prescriptions ?? []) > 0)
                <span class="bg-primary/10 text-primary text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ count($prescriptions) }}</span>
                @endif
            </button>
            <button @click="activeTab = 'medical'"
                :class="activeTab === 'medical' ? 'border-primary text-primary font-bold' : 'border-transparent text-text-sub-light dark:text-text-sub-dark hover:text-text-main-light dark:hover:text-white hover:border-border-light font-medium'"
                class="whitespace-nowrap py-4 px-1 border-b-2 text-sm flex items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-[20px]">medical_information</span> {{ __('messages.medical_info') }}
            </button>
        </nav>
    </div>

    {{-- Tab 1: Visit History --}}
    <div x-show="activeTab === 'visits'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark overflow-hidden">
            <div class="px-6 py-4 border-b border-border-light dark:border-border-dark bg-background-light/30 dark:bg-white/5">
                <h3 class="font-bold text-text-main-light dark:text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">history</span> {{ __('messages.visit_history') }}
                </h3>
            </div>
            @if(count($visitHistory ?? []) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-background-light dark:bg-white/5 border-b border-border-light dark:border-border-dark">
                            <th class="px-6 py-3 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.date_label') }}</th>
                            <th class="px-6 py-3 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.doctor_name') }}</th>
                            <th class="px-6 py-3 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.clinic_name') }}</th>
                            <th class="px-6 py-3 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.status') }}</th>
                            <th class="px-6 py-3 text-[11px] font-bold uppercase tracking-wider text-text-sub-light dark:text-text-sub-dark">{{ __('messages.notes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-light dark:divide-border-dark text-sm">
                        @foreach($visitHistory as $visit)
                        <tr class="hover:bg-background-light dark:hover:bg-white/5 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-text-main-light dark:text-white font-medium">{{ $visit['date'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-text-sub-light dark:text-text-sub-dark">{{ $visit['doctor_name'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-text-sub-light dark:text-text-sub-dark">{{ $visit['clinic_name'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20">
                                    {{ __('messages.completed') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-text-sub-light dark:text-text-sub-dark max-w-xs truncate">{{ $visit['notes'] ?: '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="p-12 text-center">
                <span class="material-symbols-outlined text-4xl text-border-light dark:text-border-dark mb-3 block">event_busy</span>
                <p class="text-text-sub-light dark:text-text-sub-dark font-medium">{{ __('messages.no_visits') }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Tab 2: Treatment Plans --}}
    <div x-show="activeTab === 'treatments'" x-cloak style="display:none" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        @if(count($treatmentPlans ?? []) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($treatmentPlans as $plan)
            @php
                $isActive = ($plan['status'] ?? '') === 'active';
                $statusColor = $isActive ? 'emerald' : 'blue';
            @endphp
            <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark p-5 card-hover">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="p-2 rounded-xl bg-{{ $statusColor }}-50 dark:bg-{{ $statusColor }}-500/10 text-{{ $statusColor }}-600 dark:text-{{ $statusColor }}-400">
                            <span class="material-symbols-outlined">healing</span>
                        </div>
                        <div>
                            <p class="font-bold text-text-main-light dark:text-white">{{ $plan['doctor_name'] }}</p>
                            <p class="text-xs text-text-sub-light dark:text-text-sub-dark">{{ $plan['created_at'] ?? '-' }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-{{ $statusColor }}-50 dark:bg-{{ $statusColor }}-500/10 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-400 border border-{{ $statusColor }}-100 dark:border-{{ $statusColor }}-500/20">
                        {{ $isActive ? __('messages.active') : __('messages.completed') }}
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-1">{{ __('messages.diagnosis') }}</p>
                    <p class="text-sm text-text-main-light dark:text-white">{{ $plan['diagnosis'] ?? '-' }}</p>
                </div>
                @if(!empty($plan['notes']))
                <div class="mt-2">
                    <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider mb-1">{{ __('messages.notes') }}</p>
                    <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ $plan['notes'] }}</p>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @else
        <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark p-12 text-center">
            <span class="material-symbols-outlined text-4xl text-border-light dark:text-border-dark mb-3 block">healing</span>
            <p class="text-text-sub-light dark:text-text-sub-dark font-medium">{{ __('messages.no_treatment_plans') }}</p>
        </div>
        @endif
    </div>

    {{-- Tab 3: Prescriptions --}}
    <div x-show="activeTab === 'prescriptions'" x-cloak style="display:none" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        @if(count($prescriptions ?? []) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($prescriptions as $rx)
            <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark p-5 card-hover">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="p-2 rounded-xl bg-violet-50 dark:bg-violet-500/10 text-violet-600 dark:text-violet-400">
                            <span class="material-symbols-outlined">pill</span>
                        </div>
                        <div>
                            <p class="font-bold text-text-main-light dark:text-white">{{ $rx['doctor_name'] }}</p>
                            <p class="text-xs text-text-sub-light dark:text-text-sub-dark">{{ $rx['created_at'] ?? '-' }}</p>
                        </div>
                    </div>
                    @php $rxStatus = $rx['status'] ?? 'active'; @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                        {{ $rxStatus === 'active' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20' : 'bg-gray-50 dark:bg-gray-500/10 text-gray-600 dark:text-gray-400 border border-gray-100 dark:border-gray-500/20' }}">
                        {{ $rxStatus === 'active' ? __('messages.active') : __('messages.completed') }}
                    </span>
                </div>
                {{-- Medications list --}}
                <div class="mt-3 space-y-2">
                    <p class="text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.medications_label') }} ({{ $rx['medications_count'] ?? 0 }})</p>
                    @foreach(($rx['medications'] ?? []) as $med)
                    <div class="flex items-center gap-3 p-2.5 bg-background-light dark:bg-white/5 rounded-lg border border-border-light dark:border-border-dark">
                        <span class="material-symbols-outlined text-[16px] text-violet-500">medication</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-text-main-light dark:text-white truncate">{{ $med['name'] ?? '-' }}</p>
                            <p class="text-xs text-text-sub-light dark:text-text-sub-dark">
                                {{ $med['dose_amount'] ?? '' }} {{ $med['dose_unit'] ?? '' }}
                                @if(!empty($med['duration_days']))
                                &bull; {{ $med['duration_days'] }} {{ __('messages.days') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark p-12 text-center">
            <span class="material-symbols-outlined text-4xl text-border-light dark:text-border-dark mb-3 block">pill</span>
            <p class="text-text-sub-light dark:text-text-sub-dark font-medium">{{ __('messages.no_prescriptions') }}</p>
        </div>
        @endif
    </div>

    {{-- Tab 4: Medical Info (Editable Form) --}}
    <div x-show="activeTab === 'medical'" x-cloak style="display:none" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-border-light dark:border-border-dark p-6">
            <div class="flex items-center gap-2 mb-6">
                <span class="material-symbols-outlined text-primary">medical_information</span>
                <h3 class="font-bold text-lg text-text-main-light dark:text-white">{{ __('messages.medical_info') }}</h3>
            </div>

            @if($errors->any())
            <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm border border-red-100 dark:border-red-900/30">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form action="{{ route('patients.update-medical', $patient['id']) }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Allergies --}}
                    <div>
                        <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.allergies') }}</label>
                        <p class="text-xs text-text-sub-light dark:text-text-sub-dark mb-2">{{ __('messages.add_allergy') }} (comma-separated)</p>
                        @php
                            $allergyStr = '';
                            $allergiesRaw = $patient['allergies'] ?? [];
                            if (is_array($allergiesRaw)) {
                                $names = [];
                                foreach ($allergiesRaw as $a) {
                                    $names[] = is_array($a) ? ($a['name'] ?? '') : (string)$a;
                                }
                                $allergyStr = implode(', ', array_filter($names));
                            }
                        @endphp
                        <input type="text" name="allergies" value="{{ old('allergies', $allergyStr) }}"
                            class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-white dark:bg-surface-dark text-sm text-text-main-light dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder-text-sub-light/50"
                            placeholder="e.g. Penicillin, Peanuts, Latex">
                    </div>

                    {{-- Chronic Conditions --}}
                    <div>
                        <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.chronic_conditions') }}</label>
                        <p class="text-xs text-text-sub-light dark:text-text-sub-dark mb-2">{{ __('messages.add_condition') }} (comma-separated)</p>
                        @php
                            $condStr = '';
                            $condsRaw = $patient['chronic_conditions'] ?? [];
                            if (is_array($condsRaw)) {
                                $names = [];
                                foreach ($condsRaw as $c) {
                                    $names[] = is_array($c) ? ($c['name'] ?? '') : (string)$c;
                                }
                                $condStr = implode(', ', array_filter($names));
                            }
                        @endphp
                        <input type="text" name="chronic_conditions" value="{{ old('chronic_conditions', $condStr) }}"
                            class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-white dark:bg-surface-dark text-sm text-text-main-light dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder-text-sub-light/50"
                            placeholder="e.g. Diabetes, Hypertension">
                    </div>

                    {{-- Blood Type --}}
                    <div>
                        <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.blood_type') }}</label>
                        <select name="blood_type"
                            class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-white dark:bg-surface-dark text-sm text-text-main-light dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <option value="">{{ __('messages.select') }}</option>
                            @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type)
                            <option value="{{ $type }}" {{ old('blood_type', $patient['blood_type'] ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Emergency Contact Fields --}}
                <div class="border-t border-border-light dark:border-border-dark pt-6">
                    <h4 class="font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-alert-amber">emergency</span>
                        {{ __('messages.emergency_contact') }}
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.emergency_contact_name') }}</label>
                            <input type="text" name="emergency_contact_name"
                                value="{{ old('emergency_contact_name', $patient['emergency_contact']['name'] ?? '') }}"
                                class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-white dark:bg-surface-dark text-sm text-text-main-light dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder-text-sub-light/50"
                                placeholder="{{ __('messages.full_name_placeholder') }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.emergency_contact_phone') }}</label>
                            <input type="text" name="emergency_contact_phone"
                                value="{{ old('emergency_contact_phone', $patient['emergency_contact']['phone'] ?? '') }}"
                                class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-white dark:bg-surface-dark text-sm text-text-main-light dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder-text-sub-light/50"
                                placeholder="{{ __('messages.phone_placeholder') }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.emergency_contact_relation') }}</label>
                            <select name="emergency_contact_relation"
                                class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-white dark:bg-surface-dark text-sm text-text-main-light dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                <option value="">{{ __('messages.select') }}</option>
                                @php $currentRelation = old('emergency_contact_relation', $patient['emergency_contact']['relation'] ?? ''); @endphp
                                <option value="spouse" {{ $currentRelation === 'spouse' ? 'selected' : '' }}>{{ __('messages.relation_spouse') }}</option>
                                <option value="parent" {{ $currentRelation === 'parent' ? 'selected' : '' }}>{{ __('messages.relation_parent') }}</option>
                                <option value="child" {{ $currentRelation === 'child' ? 'selected' : '' }}>{{ __('messages.relation_child') }}</option>
                                <option value="sibling" {{ $currentRelation === 'sibling' ? 'selected' : '' }}>{{ __('messages.relation_sibling') }}</option>
                                <option value="other" {{ $currentRelation === 'other' ? 'selected' : '' }}>{{ __('messages.relation_other') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Medical Notes --}}
                <div class="border-t border-border-light dark:border-border-dark pt-6">
                    <label class="block text-sm font-medium text-text-main-light dark:text-white mb-1.5">{{ __('messages.medical_notes') }}</label>
                    <textarea name="medical_notes" rows="4"
                        class="w-full px-4 py-2.5 border border-border-light dark:border-border-dark rounded-lg bg-white dark:bg-surface-dark text-sm text-text-main-light dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all placeholder-text-sub-light/50 resize-none"
                        placeholder="{{ __('messages.medical_notes') }}...">{{ old('medical_notes', $patient['medical_notes'] ?? '') }}</textarea>
                </div>

                {{-- Save Button --}}
                <div class="flex items-center gap-3 pt-4 border-t border-border-light dark:border-border-dark">
                    <button type="submit" class="px-6 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-lg font-medium text-sm transition-colors shadow-md shadow-primary/20 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        {{ __('messages.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
