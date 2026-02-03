@extends('layouts.app')

@section('title', 'Schedule Configuration - ' . $doctor['name'])

@section('content')
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
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Weekly Schedule -->
        <div class="bg-white dark:bg-[#111821] p-6 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748]">
            <h2 class="text-lg font-bold text-[#111418] dark:text-white mb-4">{{ __('messages.working_hours') }}</h2>

            <form action="{{ route('doctors.schedule.update', $doctor['id']) }}" method="POST">
                @csrf

                <div class="mb-6">
                    <label class="block text-sm font-medium text-[#111418] dark:text-white mb-2">
                        {{ __('messages.slot_duration') }}
                    </label>
                    <select name="slot_duration" class="w-full sm:w-1/2 p-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white">
                        @foreach([10, 15, 20, 30, 45, 60] as $duration)
                            <option value="{{ $duration }}" {{ ($doctor['slot_duration'] ?? 15) == $duration ? 'selected' : '' }}>
                                {{ $duration }} minutes
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
                    <div class="flex items-center gap-4 p-3 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748]">
                        <div class="w-24 capitalize font-medium text-[#111418] dark:text-white">{{ $day }}</div>
                        
                        <input type="hidden" name="working_hours[{{ $day }}][active]" value="0">
                        <input type="checkbox" name="working_hours[{{ $day }}][active]" value="1" 
                            class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary"
                            {{ ($workingHours[$day]['active'] ?? false) ? 'checked' : '' }}>
                        
                        <input type="time" name="working_hours[{{ $day }}][start]" 
                            value="{{ $workingHours[$day]['start'] ?? '09:00' }}"
                            class="p-1 rounded border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white">


                        <span class="text-gray-500">{{ __('messages.time_separator') }}</span>
                        
                        <input type="time" name="working_hours[{{ $day }}][end]" 
                            value="{{ $workingHours[$day]['end'] ?? '17:00' }}"
                            class="p-1 rounded border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white">
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
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">End Date</label>
                        <input type="date" name="end_date" required
                            class="w-full p-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Reason</label>
                    <input type="text" name="reason" placeholder="Vacation, Conference, emergency..."
                        class="w-full p-2 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#1c2431] text-[#111418] dark:text-white">
                </div>
                <button type="submit" class="w-full bg-gray-200 dark:bg-gray-700 text-[#111418] dark:text-white px-4 py-2 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                    Add Block-out Period
                </button>
            </form>

            <div class="space-y-3">
                @forelse($unavailability as $period)
                <div class="flex items-center justify-between p-3 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748]">
                    <div>
                        @php
                             // Handle Firestore timestamp or string
                             $start = $period['start'] instanceof \Google\Cloud\Core\Timestamp ? $period['start']->get() : new \DateTime($period['start']);
                             $end = $period['end'] instanceof \Google\Cloud\Core\Timestamp ? $period['end']->get() : new \DateTime($period['end']);
                        @endphp
                        <div class="font-medium text-[#111418] dark:text-white">
                            {{ $start->format('M j') }} - {{ $end->format('M j, Y') }}
                        </div>
                        <div class="text-sm text-[#637388] dark:text-[#9ca3af]">
                            {{ $period['reason'] ?: 'No reason provided' }}
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
                    No block-out dates recorded.
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
