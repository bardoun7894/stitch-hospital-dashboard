@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Header -->
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('bookings.index') }}" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
            <span class="material-symbols-outlined text-gray-500">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-[#111418] dark:text-white">{{ __('messages.reschedule_booking_title') }}</h1>
            <p class="text-[#637388] dark:text-[#9ca3af]">{{ __('messages.change_appointment_time') }} {{ $booking['patient_name'] ?? __('messages.patient_label') }}</p>
        </div>
    </div>

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">{{ __('messages.error_msg') }}</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
    </div>
    @endif

    <div class="bg-white dark:bg-[#111821] p-6 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm">
        <form action="{{ route('bookings.process-reschedule', $booking['id']) }}" method="POST" id="bookingForm">
            @csrf
            
            <input type="hidden" id="doctorId" value="{{ $booking['doctor_id'] }}">
            
            <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/10 rounded-lg border border-blue-100 dark:border-blue-900/30">
                <div class="flex gap-4">
                     <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-500 uppercase">{{ __('messages.current_appointment') }}</label>
                        <p class="font-bold text-[#111418] dark:text-white">{{ $booking['doctor_name'] ?? __('messages.doctor_label') }}</p>
                        <p class="text-sm text-blue-600 dark:text-blue-400">{{ $booking['date'] }} {{ __('messages.at') ?? 'at' }} {{ $booking['time'] }}</p>
                    </div>
                </div>
            </div>

            <!-- Date & Time -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-2">{{ __('messages.new_date') }}</label>
                    <input type="date" name="scheduled_date" id="dateInput" required 
                        min="{{ date('Y-m-d') }}" value="{{ $booking['date'] }}"
                        class="w-full p-2.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50 outline-none">
                </div>


                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-2">{{ __('messages.new_time_slot') }}</label>
                    <select name="time_slot" id="timeSlotSelect" required
                        class="w-full p-2.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50 outline-none disabled:opacity-50 disabled:cursor-not-allowed">
                        <option value="">{{ __('messages.select_date_first') }}</option>
                    </select>
                    <p id="slotLoading" class="hidden text-xs text-gray-500 mt-1">{{ __('messages.checking_availability') }}</p>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-[#e5e7eb] dark:border-[#2d3748]">
                <button type="button" onclick="history.back()" class="mr-4 px-6 py-2.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-[#111418] dark:text-white font-medium hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
                    {{ __('messages.cancel') }}
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-lg bg-primary hover:bg-blue-600 text-white font-medium shadow-sm transition-colors">
                    {{ __('messages.save_changes') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const doctorId = document.getElementById('doctorId').value;
    const dateInput = document.getElementById('dateInput');
    const timeSlotSelect = document.getElementById('timeSlotSelect');
    const slotLoading = document.getElementById('slotLoading');

    function fetchSlots() {
        const date = dateInput.value;

        if (!doctorId || !date) {
            timeSlotSelect.innerHTML = '<option value="">{{ __('messages.select_date_first') }}</option>';
            timeSlotSelect.disabled = true;
            return;
        }

        timeSlotSelect.disabled = true;
        slotLoading.classList.remove('hidden');
        timeSlotSelect.innerHTML = '<option value="">{{ __('messages.checking_availability') }}</option>';

        fetch(`{{ route('api.slots') }}?doctor_id=${doctorId}&date=${date}`)
            .then(response => response.json())
            .then(data => {
                timeSlotSelect.innerHTML = '<option value="">{{ __('messages.select_time') }}</option>';
                
                if (data.slots && data.slots.length > 0) {
                    data.slots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot;
                        option.textContent = formatTime(slot);
                        timeSlotSelect.appendChild(option);
                    });
                    timeSlotSelect.disabled = false;
                } else {
                    timeSlotSelect.innerHTML = '<option value="">{{ __('messages.no_slots_available') }}</option>';
                }
            })
            .catch(error => {
                console.error('Error fetching slots:', error);
                timeSlotSelect.innerHTML = '<option value="">{{ __('messages.error_loading_slots') }}</option>';
            })
            .finally(() => {
                slotLoading.classList.add('hidden');
            });
    }

    function formatTime(timeString) {
        const [hours, minutes] = timeString.split(':');
        const h = parseInt(hours, 10);
        const ampm = h >= 12 ? '{{ __('messages.pm') }}' : '{{ __('messages.am') }}';
        const h12 = h % 12 || 12;
        return `${h12}:${minutes} ${ampm}`;
    }

    dateInput.addEventListener('change', fetchSlots);
    
    // Initial fetch if date is set
    if (dateInput.value) {
        fetchSlots();
    }
</script>
@endsection
