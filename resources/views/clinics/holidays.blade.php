@extends('layouts.app')

@php
    use App\Http\Middleware\RoleMiddleware;
    $isManager = RoleMiddleware::hasAnyRole(['hospital_manager', 'super_admin', 'clinic_admin']);
    $today = date('Y-m-d');

    $upcomingHolidays = [];
    $pastHolidays = [];

    foreach ($holidays as $holiday) {
        $holidayDate = $holiday['date'] ?? '';
        if ($holidayDate >= $today) {
            $upcomingHolidays[] = $holiday;
        } else {
            $pastHolidays[] = $holiday;
        }
    }

    // Check if next holiday is within 7 days
    $nextHolidaySoon = false;
    $sevenDaysFromNow = date('Y-m-d', strtotime('+7 days'));
    foreach ($upcomingHolidays as $holiday) {
        if (($holiday['date'] ?? '') <= $sevenDaysFromNow) {
            $nextHolidaySoon = true;
            break;
        }
    }
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
                <a href="{{ route('clinics.show', $clinic['id']) }}" class="p-2 rounded-lg hover:bg-background-light dark:hover:bg-white/5 transition-colors">
                    <span class="material-symbols-outlined text-text-sub-light dark:text-text-sub-dark">arrow_back</span>
                </a>
                <div class="p-3 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <span class="material-symbols-outlined text-2xl">event_busy</span>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-text-main-light dark:text-white">{{ __('messages.holiday_calendar') }}</h1>
                    <p class="text-sm text-text-sub-light dark:text-text-sub-dark">{{ $clinic['name'] ?? '' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Main Content (2/3) --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Add Holiday Form --}}
            @if($isManager)
            <div class="bg-white/80 dark:bg-surface-dark/80 backdrop-blur-xl rounded-2xl border border-border-light dark:border-border-dark p-5">
                <h2 class="text-lg font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-base">add_circle</span>
                    {{ __('messages.add_holiday') }}
                </h2>

                <form action="{{ route('clinics.holidays.store', $clinic['id']) }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Date --}}
                        <div>
                            <label for="date" class="block text-sm font-medium text-text-main-light dark:text-white mb-1">{{ __('messages.holiday_date') }} *</label>
                            <input type="date" id="date" name="date" required value="{{ old('date') }}"
                                class="w-full px-3 py-2 rounded-xl border border-border-light dark:border-border-dark bg-background-light dark:bg-white/5 text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all">
                            @error('date')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Holiday Name (AR) --}}
                        <div>
                            <label for="name" class="block text-sm font-medium text-text-main-light dark:text-white mb-1">{{ __('messages.holiday_name') }} *</label>
                            <input type="text" id="name" name="name" required value="{{ old('name') }}" placeholder="{{ __('messages.holiday_name') }}"
                                class="w-full px-3 py-2 rounded-xl border border-border-light dark:border-border-dark bg-background-light dark:bg-white/5 text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all">
                            @error('name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Holiday Name (EN) --}}
                        <div>
                            <label for="name_en" class="block text-sm font-medium text-text-main-light dark:text-white mb-1">{{ __('messages.holiday_name_en') }}</label>
                            <input type="text" id="name_en" name="name_en" value="{{ old('name_en') }}" placeholder="{{ __('messages.holiday_name_en') }}"
                                class="w-full px-3 py-2 rounded-xl border border-border-light dark:border-border-dark bg-background-light dark:bg-white/5 text-text-main-light dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all">
                        </div>

                        {{-- Recurring + Submit --}}
                        <div class="flex items-end gap-4">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="hidden" name="is_recurring" value="0">
                                <input type="checkbox" name="is_recurring" value="1" {{ old('is_recurring') ? 'checked' : '' }}
                                    class="w-4 h-4 rounded border-border-light dark:border-border-dark text-primary focus:ring-primary/20">
                                <span class="text-sm text-text-main-light dark:text-white">{{ __('messages.recurring_yearly') }}</span>
                            </label>

                            <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-medium py-2 px-5 rounded-xl flex items-center gap-2 transition-all shadow-md shadow-primary/20 text-sm ms-auto">
                                <span class="material-symbols-outlined text-sm">add</span>
                                {{ __('messages.add_holiday') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endif

            {{-- Upcoming Holidays --}}
            <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark overflow-hidden">
                <div class="p-5 border-b border-border-light dark:border-border-dark">
                    <h2 class="text-lg font-bold text-text-main-light dark:text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-500">upcoming</span>
                        {{ __('messages.upcoming_holidays') }}
                        <span class="text-sm font-normal text-text-sub-light dark:text-text-sub-dark">({{ count($upcomingHolidays) }})</span>
                    </h2>
                </div>

                @if(count($upcomingHolidays) === 0)
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined text-5xl text-border-light dark:text-border-dark mb-3 block">event_available</span>
                    <p class="text-text-sub-light dark:text-text-sub-dark font-medium">{{ __('messages.no_holidays') }}</p>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border-light dark:border-border-dark bg-background-light/50 dark:bg-white/5">
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.holiday_date') }}</th>
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.holiday_name') }}</th>
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.holiday_name_en') }}</th>
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.recurring_yearly') }}</th>
                                @if($isManager)
                                <th class="text-end p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light dark:divide-border-dark">
                            @foreach($upcomingHolidays as $holiday)
                            @php
                                $isWithin7Days = ($holiday['date'] ?? '') <= $sevenDaysFromNow && ($holiday['date'] ?? '') >= $today;
                            @endphp
                            <tr class="hover:bg-background-light/50 dark:hover:bg-white/5 transition-colors {{ $isWithin7Days ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }}">
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        @if($isWithin7Days)
                                        <span class="material-symbols-outlined text-amber-500 text-base">warning</span>
                                        @endif
                                        <span class="font-mono text-text-main-light dark:text-white">{{ $holiday['date'] ?? '' }}</span>
                                    </div>
                                </td>
                                <td class="p-3 text-text-main-light dark:text-white font-medium">{{ $holiday['name'] ?? '' }}</td>
                                <td class="p-3 text-text-sub-light dark:text-text-sub-dark">{{ $holiday['name_en'] ?? '-' }}</td>
                                <td class="p-3">
                                    @if(!empty($holiday['is_recurring']))
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">{{ __('messages.yes') }}</span>
                                    @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">{{ __('messages.no') }}</span>
                                    @endif
                                </td>
                                @if($isManager)
                                <td class="p-3 text-end">
                                    <form action="{{ route('clinics.holidays.delete', ['clinicId' => $clinic['id'], 'holidayId' => $holiday['id']]) }}" method="POST" class="inline"
                                        onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold flex items-center gap-1 ms-auto">
                                            <span class="material-symbols-outlined text-sm">delete</span>
                                            {{ __('messages.delete') }}
                                        </button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>

            {{-- Past Holidays --}}
            @if(count($pastHolidays) > 0)
            <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark overflow-hidden">
                <div class="p-5 border-b border-border-light dark:border-border-dark">
                    <h2 class="text-lg font-bold text-text-sub-light dark:text-text-sub-dark flex items-center gap-2">
                        <span class="material-symbols-outlined text-gray-400">history</span>
                        {{ __('messages.past_holidays') }}
                        <span class="text-sm font-normal">({{ count($pastHolidays) }})</span>
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border-light dark:border-border-dark bg-background-light/50 dark:bg-white/5">
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.holiday_date') }}</th>
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.holiday_name') }}</th>
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.holiday_name_en') }}</th>
                                <th class="text-start p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.recurring_yearly') }}</th>
                                @if($isManager)
                                <th class="text-end p-3 text-xs font-bold text-text-sub-light dark:text-text-sub-dark uppercase tracking-wider">{{ __('messages.actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-light dark:divide-border-dark opacity-60">
                            @foreach($pastHolidays as $holiday)
                            <tr class="hover:bg-background-light/50 dark:hover:bg-white/5 transition-colors">
                                <td class="p-3 font-mono text-text-sub-light dark:text-text-sub-dark">{{ $holiday['date'] ?? '' }}</td>
                                <td class="p-3 text-text-sub-light dark:text-text-sub-dark font-medium">{{ $holiday['name'] ?? '' }}</td>
                                <td class="p-3 text-text-sub-light dark:text-text-sub-dark">{{ $holiday['name_en'] ?? '-' }}</td>
                                <td class="p-3">
                                    @if(!empty($holiday['is_recurring']))
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">{{ __('messages.yes') }}</span>
                                    @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">{{ __('messages.no') }}</span>
                                    @endif
                                </td>
                                @if($isManager)
                                <td class="p-3 text-end">
                                    <form action="{{ route('clinics.holidays.delete', ['clinicId' => $clinic['id'], 'holidayId' => $holiday['id']]) }}" method="POST" class="inline"
                                        onsubmit="return confirm('{{ __('messages.confirm_delete') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold flex items-center gap-1 ms-auto">
                                            <span class="material-symbols-outlined text-sm">delete</span>
                                            {{ __('messages.delete') }}
                                        </button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        {{-- Sidebar (1/3) --}}
        <div class="space-y-6">

            {{-- Quick Add Common Holidays --}}
            @if($isManager)
            <div class="bg-white/80 dark:bg-surface-dark/80 backdrop-blur-xl rounded-2xl border border-border-light dark:border-border-dark p-5">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-base">flash_on</span>
                    {{ __('messages.common_holidays') }}
                </h3>
                <div class="space-y-2">
                    <button type="button" onclick="prefillHoliday('', '{{ __('messages.eid_al_fitr') }}', 'Eid Al-Fitr', false)"
                        class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-background-light dark:hover:bg-white/5 transition-colors text-sm font-medium text-text-main-light dark:text-white text-start">
                        <span class="material-symbols-outlined text-base text-amber-500">celebration</span>
                        {{ __('messages.eid_al_fitr') }}
                    </button>
                    <button type="button" onclick="prefillHoliday('', '{{ __('messages.eid_al_adha') }}', 'Eid Al-Adha', false)"
                        class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-background-light dark:hover:bg-white/5 transition-colors text-sm font-medium text-text-main-light dark:text-white text-start">
                        <span class="material-symbols-outlined text-base text-amber-500">celebration</span>
                        {{ __('messages.eid_al_adha') }}
                    </button>
                    <button type="button" onclick="prefillHoliday('{{ date('Y') }}-09-23', '{{ __('messages.saudi_national_day') }}', 'Saudi National Day', true)"
                        class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-background-light dark:hover:bg-white/5 transition-colors text-sm font-medium text-text-main-light dark:text-white text-start">
                        <span class="material-symbols-outlined text-base text-emerald-500">flag</span>
                        {{ __('messages.saudi_national_day') }}
                    </button>
                </div>
            </div>
            @endif

            {{-- Clinic Info --}}
            <div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark p-5">
                <h3 class="text-sm font-bold text-text-main-light dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-base">info</span>
                    {{ __('messages.clinic_info') }}
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center gap-2 text-text-sub-light dark:text-text-sub-dark">
                        <span class="material-symbols-outlined text-base">local_hospital</span>
                        <span>{{ $clinic['name'] ?? '' }}</span>
                    </div>
                    @if(!empty($clinic['specialty']))
                    <div class="flex items-center gap-2 text-text-sub-light dark:text-text-sub-dark">
                        <span class="material-symbols-outlined text-base">medical_services</span>
                        <span>{{ $clinic['specialty'] }}</span>
                    </div>
                    @endif
                    <div class="flex items-center justify-between p-3 bg-background-light dark:bg-white/5 rounded-xl">
                        <div class="flex items-center gap-2 text-sm text-text-sub-light dark:text-text-sub-dark">
                            <span class="material-symbols-outlined text-base text-amber-500">event_busy</span>
                            {{ __('messages.upcoming_holidays') }}
                        </div>
                        <span class="text-lg font-bold text-text-main-light dark:text-white">{{ count($upcomingHolidays) }}</span>
                    </div>
                </div>
            </div>

            {{-- Warning if next holiday is soon --}}
            @if($nextHolidaySoon)
            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-2xl border border-amber-200 dark:border-amber-900/30 p-5">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-xl">warning</span>
                    <div>
                        <p class="text-sm font-bold text-amber-700 dark:text-amber-400">{{ __('messages.next_holiday_warning') }}</p>
                        <p class="text-xs text-amber-600 dark:text-amber-500 mt-1">{{ $upcomingHolidays[0]['name'] ?? '' }} - {{ $upcomingHolidays[0]['date'] ?? '' }}</p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@if($isManager)
<script>
function prefillHoliday(date, name, nameEn, recurring) {
    const dateInput = document.getElementById('date');
    const nameInput = document.getElementById('name');
    const nameEnInput = document.getElementById('name_en');
    const recurringInput = document.querySelector('input[name="is_recurring"][type="checkbox"]');

    if (date) {
        dateInput.value = date;
    }
    nameInput.value = name;
    nameEnInput.value = nameEn;
    recurringInput.checked = recurring;

    // Scroll to form
    dateInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (!date) {
        dateInput.focus();
    }
}
</script>
@endif
@endsection
