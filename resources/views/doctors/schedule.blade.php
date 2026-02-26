@extends('layouts.app')

@section('title', 'Schedule Configuration - ' . $doctor['name'])

@section('content')
@php
    $isArabic = app()->getLocale() === 'ar';
    $currentMonth = date('Y-m');
    $dutyDays = $doctor['duty_days'] ?? [];
    $currentMonthDutyDays = $dutyDays[$currentMonth] ?? [];
@endphp

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('doctors.index') }}" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                <span class="material-symbols-outlined text-gray-500">arrow_back</span>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ $doctor['name'] }}</h1>
                <p class="text-[#637388] dark:text-[#9ca3af]">{{ __('messages.schedule_configuration') }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">{{ __('messages.success') }}!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">{{ __('messages.error') }}!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Weekly Schedule (Working Hours) -->
        <div class="bg-white dark:bg-[#111821] p-6 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748]">
            <h2 class="text-lg font-bold text-[#111418] dark:text-white mb-4">{{ __('messages.working_hours') }}</h2>

            <form id="schedule-form" action="{{ route('doctors.schedule.update', $doctor['id']) }}" method="POST">
                @csrf

                <div class="mb-6">
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-2">
                        {{ __('messages.slot_duration') }}
                    </label>
                    <select name="slot_duration" class="w-full sm:w-1/2 p-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white">
                        @foreach([10, 15, 20, 30, 45, 60] as $duration)
                            <option value="{{ $duration }}" {{ ($doctor['slot_duration'] ?? 15) == $duration ? 'selected' : '' }}>
                                @if($isArabic){{ \App\Helpers\ArabicHelper::toArabicDigits($duration) }}@else{{ $duration }}@endif {{ __('messages.minutes') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-4">
                    @php
                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                        $workingHours = $doctor['working_hours'] ?? [];
                    @endphp

                    @foreach($days as $day)
                    <div class="p-3 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748]">
                        <div class="capitalize font-medium text-[#111418] dark:text-white mb-2">{{ __('messages.' . $day) }}</div>

                        {{-- Morning Session --}}
                        <div class="flex items-center gap-3 mb-2">
                            <span class="text-xs font-medium text-[#637388] dark:text-[#9ca3af] w-16">{{ __('messages.morning') }}</span>
                            <input type="hidden" name="working_hours[{{ $day }}][am_active]" value="0">
                            <input type="checkbox" name="working_hours[{{ $day }}][am_active]" value="1"
                                class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary"
                                {{ ($workingHours[$day]['am_active'] ?? false) ? 'checked' : '' }}>

                            <input type="time" name="working_hours[{{ $day }}][am_start]"
                                value="{{ $workingHours[$day]['am_start'] ?? '08:00' }}"
                                class="p-1 rounded border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white text-sm">

                            <span class="text-gray-500">{{ __('messages.time_separator') }}</span>

                            <input type="time" name="working_hours[{{ $day }}][am_end]"
                                value="{{ $workingHours[$day]['am_end'] ?? '12:00' }}"
                                class="p-1 rounded border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white text-sm">
                        </div>

                        {{-- Evening Session --}}
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium text-[#637388] dark:text-[#9ca3af] w-16">{{ __('messages.evening') }}</span>
                            <input type="hidden" name="working_hours[{{ $day }}][pm_active]" value="0">
                            <input type="checkbox" name="working_hours[{{ $day }}][pm_active]" value="1"
                                class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary"
                                {{ ($workingHours[$day]['pm_active'] ?? false) ? 'checked' : '' }}>

                            <input type="time" name="working_hours[{{ $day }}][pm_start]"
                                value="{{ $workingHours[$day]['pm_start'] ?? '16:00' }}"
                                class="p-1 rounded border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white text-sm">

                            <span class="text-gray-500">{{ __('messages.time_separator') }}</span>

                            <input type="time" name="working_hours[{{ $day }}][pm_end]"
                                value="{{ $workingHours[$day]['pm_end'] ?? '21:00' }}"
                                class="p-1 rounded border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white text-sm">
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="bg-[#1980e6] text-white px-6 py-2 rounded-full font-medium hover:bg-[#156bbf] transition-colors">
                        {{ __('messages.save_schedule') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: Calendar + Block-out Dates -->
        <div class="space-y-6">
            <!-- Monthly Duty Days Calendar -->
            <div class="bg-white dark:bg-[#111821] p-6 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748]"
                 x-data="dutyCalendar()" x-init="init()">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-[#111418] dark:text-white">{{ __('messages.monthly_duty_days') }}</h2>
                    <div class="flex items-center gap-3">
                        <span class="flex items-center gap-1 text-xs text-[#637388] dark:text-[#9ca3af]">
                            <span class="w-3 h-3 rounded-sm bg-green-500 inline-block"></span>
                            {{ __('messages.on_duty_day') }}
                        </span>
                        <span class="flex items-center gap-1 text-xs text-[#637388] dark:text-[#9ca3af]">
                            <span class="w-3 h-3 rounded-sm bg-gray-300 dark:bg-gray-600 inline-block"></span>
                            {{ __('messages.off_duty_day') }}
                        </span>
                    </div>
                </div>

                <!-- Month Navigation -->
                <div class="flex items-center justify-center gap-6 mb-4">
                    <button type="button" @click="prevMonth()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-gray-500">{{ $isArabic ? 'chevron_right' : 'chevron_left' }}</span>
                    </button>
                    <h3 class="text-base font-semibold text-[#111418] dark:text-white min-w-[180px] text-center" x-text="monthLabel"></h3>
                    <button type="button" @click="nextMonth()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-gray-500">{{ $isArabic ? 'chevron_left' : 'chevron_right' }}</span>
                    </button>
                </div>

                <!-- Quick Actions -->
                <div class="flex items-center justify-between mb-3">
                    <div class="flex gap-2">
                        <button type="button" @click="selectAll()" class="text-xs px-3 py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors">
                            {{ __('messages.select_all_days') }}
                        </button>
                        <button type="button" @click="deselectAll()" class="text-xs px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                            {{ __('messages.deselect_all_days') }}
                        </button>
                    </div>
                    <span class="text-sm text-[#637388] dark:text-[#9ca3af]">
                        <span x-text="toArabicNum(selectedDays.length)"></span> {{ __('messages.days_selected') }}
                    </span>
                </div>

                <!-- Weekday Headers -->
                <div class="grid grid-cols-7 gap-1.5 mb-1">
                    @php
                        $weekDayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                    @endphp
                    @foreach($weekDayKeys as $wd)
                    <div class="text-center text-xs font-semibold text-[#637388] dark:text-[#9ca3af] py-1">
                        {{ __('messages.' . $wd) }}
                    </div>
                    @endforeach
                </div>

                <!-- Calendar Grid -->
                <div class="grid grid-cols-7 gap-1.5">
                    <!-- Empty cells before first day -->
                    <template x-for="i in startOffset" :key="'empty-'+i">
                        <div class="aspect-square"></div>
                    </template>

                    <!-- Day cells -->
                    <template x-for="day in daysInMonth" :key="day">
                        <button type="button"
                            @click="toggleDay(day)"
                            :class="isDuty(day)
                                ? 'bg-green-500 hover:bg-green-600 text-white shadow-sm shadow-green-500/30'
                                : 'bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-[#637388] dark:text-[#9ca3af]'"
                            class="aspect-square rounded-lg flex items-center justify-center text-sm font-medium transition-all duration-200 cursor-pointer">
                            <span x-text="toArabicNum(day)"></span>
                        </button>
                    </template>
                </div>

                <!-- Saving indicator -->
                <div x-show="saving" x-transition style="display:none" class="mt-3 flex items-center justify-center gap-2 text-xs text-[#637388] dark:text-[#9ca3af]">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    {{ __('messages.saving') }}...
                </div>
            </div>

            <!-- Block-out Dates -->
            <div class="bg-white dark:bg-[#111821] p-6 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748]">
            <h2 class="text-lg font-bold text-[#111418] dark:text-white mb-4">{{ __('messages.unavailability') }}</h2>

            <form action="{{ route('doctors.blockout.add', $doctor['id']) }}" method="POST" class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                @csrf
                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('messages.start_date') }}</label>
                        <input type="date" name="start_date" required
                            class="w-full p-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('messages.end_date') }}</label>
                        <input type="date" name="end_date" required
                            class="w-full p-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('messages.reason') }}</label>
                    <input type="text" name="reason" placeholder="{{ __('messages.reason_placeholder') }}"
                        class="w-full p-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white">
                </div>
                <button type="submit" class="w-full bg-gray-200 dark:bg-gray-700 text-[#111418] dark:text-white px-4 py-2 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                    {{ __('messages.add_blockout') }}
                </button>
            </form>

            <div class="space-y-3">
                @forelse($unavailability as $period)
                <div class="flex items-center justify-between p-3 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748]">
                    <div>
                        @php
                             $start = $period['start'] instanceof \Google\Cloud\Core\Timestamp ? $period['start']->get() : new \DateTime($period['start']);
                             $end = $period['end'] instanceof \Google\Cloud\Core\Timestamp ? $period['end']->get() : new \DateTime($period['end']);
                        @endphp
                        <div class="font-medium text-[#111418] dark:text-white">
                            @if($isArabic)
                                {{ \App\Helpers\ArabicHelper::toArabicDigits($start->format('M j')) }} - {{ \App\Helpers\ArabicHelper::toArabicDigits($end->format('M j, Y')) }}
                            @else
                                {{ $start->format('M j') }} - {{ $end->format('M j, Y') }}
                            @endif
                        </div>
                        <div class="text-sm text-[#637388] dark:text-[#9ca3af]">
                            {{ $period['reason'] ?: __('messages.no_reason') }}
                        </div>
                    </div>
                    <form action="{{ route('doctors.blockout.remove', ['id' => $doctor['id'], 'blockId' => $period['id']]) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-500 hover:text-red-700 p-2">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </form>
                </div>
                @empty
                <div class="text-center py-8 text-gray-500">
                    {{ __('messages.no_blockouts') }}
                </div>
                @endforelse
            </div>
        </div>
        </div> {{-- /Right Column --}}
    </div>
</div>

<script>
    // Arabic numeral mapping
    const arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    const isArabicLocale = @json($isArabic);

    const arabicMonths = [
        'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
    ];
    const englishMonths = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    function toArabicDigits(num) {
        if (!isArabicLocale) return String(num);
        return String(num).replace(/[0-9]/g, function(d) {
            return arabicDigits[parseInt(d)];
        });
    }

    function dutyCalendar() {
        return {
            currentYear: {{ (int)date('Y') }},
            currentMonthIndex: {{ (int)date('n') - 1 }},
            selectedDays: @json(array_map('intval', $currentMonthDutyDays)),
            allDutyDays: @json($dutyDays),
            daysInMonth: 0,
            startOffset: 0,
            monthLabel: '',
            currentMonthKey: '{{ $currentMonth }}',
            saving: false,
            saveTimeout: null,

            init() {
                this.buildCalendar();
            },

            buildCalendar() {
                const date = new Date(this.currentYear, this.currentMonthIndex + 1, 0);
                this.daysInMonth = date.getDate();

                // First day of month (0=Sunday) - adjust for Monday-start week
                const firstDay = new Date(this.currentYear, this.currentMonthIndex, 1).getDay();
                // Mon=0, Tue=1, Wed=2, Thu=3, Fri=4, Sat=5, Sun=6
                this.startOffset = (firstDay + 6) % 7;

                const m = String(this.currentMonthIndex + 1).padStart(2, '0');
                this.currentMonthKey = this.currentYear + '-' + m;

                if (isArabicLocale) {
                    this.monthLabel = arabicMonths[this.currentMonthIndex] + ' ' + toArabicDigits(this.currentYear);
                } else {
                    this.monthLabel = englishMonths[this.currentMonthIndex] + ' ' + this.currentYear;
                }
            },

            toggleDay(day) {
                const idx = this.selectedDays.indexOf(day);
                if (idx > -1) {
                    this.selectedDays.splice(idx, 1);
                } else {
                    this.selectedDays.push(day);
                }
                this.saveDutyDays();
            },

            isDuty(day) {
                return this.selectedDays.includes(day);
            },

            selectAll() {
                this.selectedDays = [];
                for (let i = 1; i <= this.daysInMonth; i++) {
                    this.selectedDays.push(i);
                }
                this.saveDutyDays();
            },

            deselectAll() {
                this.selectedDays = [];
                this.saveDutyDays();
            },

            prevMonth() {
                this.currentMonthIndex--;
                if (this.currentMonthIndex < 0) {
                    this.currentMonthIndex = 11;
                    this.currentYear--;
                }
                this.selectedDays = [];
                this.loadMonthDutyDays();
                this.buildCalendar();
            },

            nextMonth() {
                this.currentMonthIndex++;
                if (this.currentMonthIndex > 11) {
                    this.currentMonthIndex = 0;
                    this.currentYear++;
                }
                this.selectedDays = [];
                this.loadMonthDutyDays();
                this.buildCalendar();
            },

            loadMonthDutyDays() {
                const m = String(this.currentMonthIndex + 1).padStart(2, '0');
                const key = this.currentYear + '-' + m;
                if (this.allDutyDays[key]) {
                    this.selectedDays = this.allDutyDays[key].map(Number);
                }
            },

            toArabicNum(num) {
                return toArabicDigits(num);
            },

            saveDutyDays() {
                // Update local cache
                this.allDutyDays[this.currentMonthKey] = [...this.selectedDays];

                // Debounce: wait 300ms after last click before saving
                if (this.saveTimeout) clearTimeout(this.saveTimeout);
                this.saving = true;

                this.saveTimeout = setTimeout(() => {
                    const url = '/doctors/{{ $doctor["id"] }}/duty-days';
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            month: this.currentMonthKey,
                            days: this.selectedDays,
                        }),
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.saving = false;
                        if (!data.success) {
                            console.error('Failed to save duty days:', data.error);
                        }
                    })
                    .catch(err => {
                        this.saving = false;
                        console.error('Error saving duty days:', err);
                    });
                }, 300);
            }
        }
    }
</script>
@endsection
