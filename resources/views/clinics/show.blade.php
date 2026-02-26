@extends('layouts.app')

@php
    use App\Http\Middleware\RoleMiddleware;
    $isSuperAdmin = RoleMiddleware::hasRole('super_admin');
    $isManager = RoleMiddleware::hasAnyRole(['hospital_manager', 'super_admin', 'clinic_admin']);
    $status = $clinic['status'] ?? 'active';
    $statusConfig = match($status) {
        'active' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-700 dark:text-emerald-400', 'icon' => 'check_circle'],
        'paused' => ['bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-700 dark:text-amber-400', 'icon' => 'pause_circle'],
        'closed' => ['bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-700 dark:text-red-400', 'icon' => 'cancel'],
        default => ['bg' => 'bg-gray-100 dark:bg-gray-800', 'text' => 'text-gray-600 dark:text-gray-400', 'icon' => 'help'],
    };
@endphp

@section('content')
<div class="max-w-[1600px] mx-auto">

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

    {{-- Header --}}
    <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                @if($hospital)
                <a href="{{ route('hospital.show', $hospital['id']) }}" class="p-2 rounded-lg hover:bg-background-light dark:hover:bg-white/5 transition-colors">
                    <span class="material-symbols-outlined text-text-sub-light dark:text-text-sub-dark">arrow_back</span>
                </a>
                @else
                <a href="{{ route('clinics.index') }}" class="p-2 rounded-lg hover:bg-background-light dark:hover:bg-white/5 transition-colors">
                    <span class="material-symbols-outlined text-text-sub-light dark:text-text-sub-dark">arrow_back</span>
                </a>
                @endif
                <div class="p-3 rounded-xl bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-2xl">{{ $clinic['icon'] ?? 'local_hospital' }}</span>
                </div>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-text-main-light dark:text-white">{{ $clinic['name'] ?? '' }}</h1>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">{{ $statusConfig['icon'] }}</span>
                            {{ __(('messages.' . strtolower($status))) }}
                        </span>
                    </div>
                    @if(!empty($clinic['name_en']))
                    <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ $clinic['name_en'] }}</p>
                    @endif
                    @if(!empty($clinic['specialty']))
                    <p class="text-xs text-text-sub-light dark:text-text-sub-dark mt-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">medical_services</span>
                        {{ $clinic['specialty'] }}
                    </p>
                    @endif
                </div>
            </div>
            @if($isManager)
            <div class="flex items-center gap-2">
                <a href="{{ route('clinics.edit', $clinic['id']) }}" class="px-4 py-2 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium hover:bg-background-light dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">edit</span>
                    {{ __('messages.edit') }}
                </a>
                <a href="{{ route('doctors.create', ['clinic_id' => $clinic['id']]) }}" class="bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition-all shadow-md shadow-primary/20 text-sm">
                    <span class="material-symbols-outlined text-sm">person_add</span>
                    {{ __('messages.add_doctor') }}
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Doctors Table (2/3) --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark overflow-hidden">
                <div class="p-5 border-b border-border-light dark:border-border-dark flex items-center justify-between">
                    <h2 class="text-lg font-bold text-text-main-light dark:text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">stethoscope</span>
                        {{ __('messages.doctors') }}
                        <span class="text-sm font-normal text-text-sub-light dark:text-text-sub-dark">({{ count($doctors) }})</span>
                    </h2>
                </div>

                @if(count($doctors) === 0)
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined text-5xl text-border-light dark:text-border-dark mb-3 block">person_off</span>
                    <p class="text-text-sub-light dark:text-text-sub-dark font-medium mb-4">{{ __('messages.no_doctors_yet') }}</p>
                    @if($isManager)
                    <a href="{{ route('doctors.create', ['clinic_id' => $clinic['id']]) }}" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-lg transition-all text-sm">
                        <span class="material-symbols-outlined text-sm">person_add</span>
                        {{ __('messages.add_first_doctor') }}
                    </a>
                    @endif
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border-light dark:border-border-dark bg-background-light/50 dark:bg-white/5">
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.doctor_name') }}</th>
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.specialty') }}</th>
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.status') }}</th>
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.phone') }}</th>
                                @if($isManager)
                                <th class="text-end p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light dark:divide-border-dark">
                            @foreach($doctors as $doctor)
                            @php
                                $docStatus = $doctor['status'] ?? 'available';
                                $docStatusConfig = match($docStatus) {
                                    'available' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-700 dark:text-emerald-400'],
                                    'busy' => ['bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-700 dark:text-amber-400'],
                                    'off' => ['bg' => 'bg-gray-100 dark:bg-gray-800', 'text' => 'text-gray-600 dark:text-gray-400'],
                                    default => ['bg' => 'bg-gray-100 dark:bg-gray-800', 'text' => 'text-gray-600 dark:text-gray-400'],
                                };
                            @endphp
                            <tr class="hover:bg-background-light/50 dark:hover:bg-white/5 transition-colors">
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        @if(!empty($doctor['photo_url']))
                                        <img src="{{ $doctor['photo_url'] }}" alt="{{ $doctor['name'] ?? '' }}" class="size-9 rounded-full object-cover border border-border-light dark:border-border-dark">
                                        @else
                                        <div class="size-9 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                                            <span class="material-symbols-outlined text-base">person</span>
                                        </div>
                                        @endif
                                        <div>
                                            <a href="{{ route('doctors.edit', $doctor['id']) }}" class="font-semibold text-text-main-light dark:text-white hover:text-primary transition-colors">{{ $doctor['name'] ?? '' }}</a>
                                            @if(!empty($doctor['name_en']))
                                            <p class="text-xs text-text-sub-light dark:text-text-sub-dark">{{ $doctor['name_en'] }}</p>
                                            @endif
                                            @if(!empty($doctor['avg_rating']))
                                            <div class="flex items-center gap-1 mt-0.5">
                                                @for($starIdx = 1; $starIdx <= 5; $starIdx++)
                                                    @if($starIdx <= floor($doctor['avg_rating']))
                                                        <span class="material-symbols-outlined text-amber-400 text-[11px] filled">star</span>
                                                    @elseif($starIdx - $doctor['avg_rating'] < 1 && $starIdx - $doctor['avg_rating'] > 0)
                                                        <span class="material-symbols-outlined text-amber-400 text-[11px]">star_half</span>
                                                    @else
                                                        <span class="material-symbols-outlined text-gray-300 dark:text-gray-600 text-[11px]">star</span>
                                                    @endif
                                                @endfor
                                                <span class="text-[10px] font-bold text-text-sub-light dark:text-text-sub-dark ltr:ml-0.5 rtl:mr-0.5">{{ $doctor['avg_rating'] }} ({{ $doctor['total_reviews'] ?? 0 }} {{ __('messages.reviews') }})</span>
                                            </div>
                                            @endif
                                            @if(!empty($doctor['years_experience']))
                                            <p class="text-[10px] text-text-sub-light dark:text-text-sub-dark flex items-center gap-0.5">
                                                <span class="material-symbols-outlined text-[10px]">work</span>
                                                {{ $doctor['years_experience'] }} {{ __('messages.years_experience') }}
                                            </p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3 text-text-sub-light dark:text-text-sub-dark">{{ $doctor['specialty'] ?? '-' }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $docStatusConfig['bg'] }} {{ $docStatusConfig['text'] }}">
                                        {{ __('messages.' . $docStatus) }}
                                    </span>
                                </td>
                                <td class="p-3 text-text-sub-light dark:text-text-sub-dark" dir="ltr">{{ $doctor['phone'] ?? '-' }}</td>
                                @if($isManager)
                                <td class="p-3 text-end">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('doctors.edit', $doctor['id']) }}" class="text-primary text-xs font-semibold hover:underline flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">edit</span>
                                            {{ __('messages.edit') }}
                                        </a>
                                        <a href="{{ route('doctors.schedule', $doctor['id']) }}" class="text-text-sub-light dark:text-text-sub-dark text-xs font-semibold hover:text-primary flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">calendar_month</span>
                                            {{ __('messages.schedule') }}
                                        </a>
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        {{-- Sidebar (1/3) --}}
        <div class="space-y-6">
            {{-- Clinic Info --}}
            <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-5">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-base">info</span>
                    {{ __('messages.clinic_info') }}
                </h3>
                <div class="space-y-3 text-sm">
                    @if($hospital)
                    <div class="flex items-center gap-2 text-text-sub-light dark:text-text-sub-dark">
                        <span class="material-symbols-outlined text-base">domain</span>
                        <a href="{{ route('hospital.show', $hospital['id']) }}" class="hover:text-primary transition-colors">{{ $hospital['name'] ?? '' }}</a>
                    </div>
                    @endif
                    @if(!empty($clinic['specialty']))
                    <div class="flex items-center gap-2 text-text-sub-light dark:text-text-sub-dark">
                        <span class="material-symbols-outlined text-base">medical_services</span>
                        <span>{{ $clinic['specialty'] }}</span>
                    </div>
                    @endif
                    @if(!empty($clinic['address']))
                    <div class="flex items-center gap-2 text-text-sub-light dark:text-text-sub-dark">
                        <span class="material-symbols-outlined text-base">location_on</span>
                        <span>{{ $clinic['address'] }}</span>
                    </div>
                    @endif
                    @if(!empty($clinic['working_hours']))
                    <div class="flex items-center gap-2 text-text-sub-light dark:text-text-sub-dark">
                        <span class="material-symbols-outlined text-base">schedule</span>
                        <span>{{ $clinic['working_hours']['start'] ?? '' }} - {{ $clinic['working_hours']['end'] ?? '' }}</span>
                    </div>
                    @endif
                    @if(!empty($clinic['daily_capacity']))
                    <div class="flex items-center gap-2 text-text-sub-light dark:text-text-sub-dark">
                        <span class="material-symbols-outlined text-base">groups</span>
                        <span>{{ __('messages.daily_capacity') }}: {{ $clinic['daily_capacity'] }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Accepted Insurance --}}
            <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-5">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-base">health_and_safety</span>
                    {{ __('messages.insurance_providers') }}
                </h3>
                @if(!empty($clinic['accepted_insurance']) && is_array($clinic['accepted_insurance']) && count($clinic['accepted_insurance']) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach($clinic['accepted_insurance'] as $insurer)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary/10 text-primary rounded-full text-xs font-medium">
                        <span class="material-symbols-outlined text-xs">health_and_safety</span>
                        {{ $insurer }}
                    </span>
                    @endforeach
                </div>
                @else
                <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ __('messages.no_insurance_info') }}</p>
                @endif
            </div>

            {{-- Quick Stats --}}
            <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-5">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-base">analytics</span>
                    {{ __('messages.quick_stats') }}
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-background-light dark:bg-white/5 rounded-xl">
                        <div class="flex items-center gap-2 text-sm text-text-sub-light dark:text-text-sub-dark">
                            <span class="material-symbols-outlined text-base text-primary">stethoscope</span>
                            {{ __('messages.doctors') }}
                        </div>
                        <span class="text-lg font-bold text-text-main-light dark:text-white">{{ count($doctors) }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-background-light dark:bg-white/5 rounded-xl">
                        <div class="flex items-center gap-2 text-sm text-text-sub-light dark:text-text-sub-dark">
                            <span class="material-symbols-outlined text-base text-amber-500">hourglass_top</span>
                            {{ __('messages.patients_waiting_label') }}
                        </div>
                        <span class="text-lg font-bold text-text-main-light dark:text-white">{{ $clinic['patients_waiting'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            {{-- Holidays --}}
            @if($isManager)
            @php
                $holidays = app(\App\Services\FirebaseService::class)->getClinicHolidays($clinic['id']);
                $todayDate = date('Y-m-d');
                $upcomingCount = 0;
                $nextHolidaySoon = false;
                $nextHolidayName = '';
                $nextHolidayDate = '';
                $sevenDays = date('Y-m-d', strtotime('+7 days'));
                foreach ($holidays as $h) {
                    if (($h['date'] ?? '') >= $todayDate) {
                        $upcomingCount++;
                        if (!$nextHolidaySoon && ($h['date'] ?? '') <= $sevenDays) {
                            $nextHolidaySoon = true;
                            $nextHolidayName = $h['name'] ?? '';
                            $nextHolidayDate = $h['date'] ?? '';
                        }
                    }
                }
            @endphp
            <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-5">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500 text-base">event_busy</span>
                    {{ __('messages.holidays') }}
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-background-light dark:bg-white/5 rounded-xl">
                        <div class="flex items-center gap-2 text-sm text-text-sub-light dark:text-text-sub-dark">
                            <span class="material-symbols-outlined text-base text-amber-500">upcoming</span>
                            {{ __('messages.upcoming_holidays') }}
                        </div>
                        <span class="text-lg font-bold text-text-main-light dark:text-white">{{ $upcomingCount }}</span>
                    </div>

                    @if($nextHolidaySoon)
                    <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-900/30">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-base">warning</span>
                            <span class="text-xs font-bold text-amber-700 dark:text-amber-400">{{ __('messages.next_holiday_warning') }}</span>
                        </div>
                        <p class="text-xs text-amber-600 dark:text-amber-500 mt-1 ms-6">{{ $nextHolidayName }} - {{ $nextHolidayDate }}</p>
                    </div>
                    @endif

                    <a href="{{ route('clinics.holidays', $clinic['id']) }}" class="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 transition-colors text-sm font-medium text-amber-700 dark:text-amber-400">
                        <span class="material-symbols-outlined text-base">calendar_month</span>
                        {{ __('messages.manage_holidays') }}
                    </a>
                </div>
            </div>
            @endif

            {{-- Quick Actions --}}
            @if($isManager)
            <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-5">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-base">bolt</span>
                    {{ __('messages.quick_actions') }}
                </h3>
                <div class="space-y-2">
                    <a href="{{ route('doctors.create', ['clinic_id' => $clinic['id']]) }}" class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-background-light dark:hover:bg-white/5 transition-colors text-sm font-medium text-text-main-light dark:text-white">
                        <span class="material-symbols-outlined text-base text-primary">person_add</span>
                        {{ __('messages.add_doctor') }}
                    </a>
                    <a href="{{ route('bookings.index', ['clinic_id' => $clinic['id']]) }}" class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-background-light dark:hover:bg-white/5 transition-colors text-sm font-medium text-text-main-light dark:text-white">
                        <span class="material-symbols-outlined text-base text-primary">event_note</span>
                        {{ __('messages.view_queue') }}
                    </a>
                    <a href="{{ route('clinics.holidays', $clinic['id']) }}" class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-background-light dark:hover:bg-white/5 transition-colors text-sm font-medium text-text-main-light dark:text-white">
                        <span class="material-symbols-outlined text-base text-amber-500">event_busy</span>
                        {{ __('messages.manage_holidays') }}
                    </a>
                    <a href="{{ route('clinics.edit', $clinic['id']) }}" class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-background-light dark:hover:bg-white/5 transition-colors text-sm font-medium text-text-main-light dark:text-white">
                        <span class="material-symbols-outlined text-base text-primary">settings</span>
                        {{ __('messages.clinic_settings') }}
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
