@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto">

    {{-- Welcome Section --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-text-main-light dark:text-white tracking-tight mb-1">
            {{ __('messages.welcome_doctor') }} {{ $currentUser['name'] ?? '' }}
        </h1>
        @if($clinic)
        <p class="text-text-sub-light dark:text-text-sub-dark text-sm flex items-center gap-1.5">
            <span class="material-symbols-outlined text-primary text-base">emergency_home</span>
            {{ $clinic['name'] ?? '' }}
        </p>
        @endif
    </div>

    {{-- Flash Messages --}}
    @if(session('error'))
    <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 text-sm font-medium flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">error</span>
        {{ session('error') }}
    </div>
    @endif

    {{-- Doctor Summary --}}
    @if($doctor)
    <div class="mb-6 bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm p-5 flex items-center gap-4">
        @if(!empty($doctor['photo_url']))
        <img src="{{ $doctor['photo_url'] }}" class="size-14 rounded-full object-cover border-2 border-primary/20">
        @else
        <div class="size-14 rounded-full bg-primary/10 flex items-center justify-center">
            <span class="material-symbols-outlined text-primary text-2xl">person</span>
        </div>
        @endif
        <div>
            <h2 class="text-lg font-bold text-[#111418] dark:text-white">{{ $doctor['name'] ?? '' }}</h2>
            <p class="text-sm text-[#637388] dark:text-gray-400">{{ $doctor['specialty'] ?? '' }}</p>
        </div>
    </div>
    @endif

    {{-- Queue Card + Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        {{-- Now Serving --}}
        <div class="bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm p-6 flex flex-col items-center text-center">
            <p class="text-[#637388] dark:text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">{{ __('messages.now_serving') }}</p>
            <h1 class="text-primary text-5xl font-black tracking-tight mb-1">{{ $queueState['now_serving'] ?? '0' }}</h1>
            @if($queueState['is_paused'] ?? false)
            <span class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 text-xs px-2 py-0.5 rounded-full font-bold uppercase">{{ __('messages.paused_label') }}</span>
            @endif
        </div>

        {{-- Total Tokens --}}
        <div class="bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm p-6 flex flex-col items-center text-center">
            <p class="text-[#637388] dark:text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">{{ __('messages.total_bookings') }}</p>
            <h1 class="text-[#111418] dark:text-white text-5xl font-black tracking-tight mb-1">{{ $queueState['last_issued'] ?? '0' }}</h1>
            <p class="text-[#637388] dark:text-gray-400 text-xs">{{ __('messages.bookings_today') }}</p>
        </div>

        {{-- Remaining --}}
        <div class="bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm p-6 flex flex-col items-center text-center">
            <p class="text-[#637388] dark:text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">{{ __('messages.awaiting_label') }}</p>
            <h1 class="text-orange-500 text-5xl font-black tracking-tight mb-1">{{ $queueState['remaining'] ?? '0' }}</h1>
            <p class="text-[#637388] dark:text-gray-400 text-xs">{{ __('messages.no_patients_waiting') }}</p>
        </div>
    </div>

    {{-- Quick Links --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @if($currentUser['doctor_id'] ?? null)
        <a href="{{ route('doctors.schedule', $currentUser['doctor_id']) }}" class="bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm p-4 flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-primary">calendar_month</span>
            <span class="text-sm font-medium text-[#111418] dark:text-white">{{ __('messages.my_schedule') }}</span>
        </a>
        @endif
        <a href="{{ route('bookings.index') }}" class="bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm p-4 flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-blue-500">campaign</span>
            <span class="text-sm font-medium text-[#111418] dark:text-white">{{ __('messages.my_queue') }}</span>
        </a>
        <a href="{{ route('treatment-plans.index') }}" class="bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm p-4 flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-green-500">clinical_notes</span>
            <span class="text-sm font-medium text-[#111418] dark:text-white">{{ __('messages.treatment_plans_nav') }}</span>
        </a>
        <a href="{{ route('medications.index') }}" class="bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm p-4 flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-purple-500">medication</span>
            <span class="text-sm font-medium text-[#111418] dark:text-white">{{ __('messages.medications_nav') }}</span>
        </a>
    </div>

    {{-- Today's Patients Table --}}
    <div class="bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm">
        <div class="p-5 border-b border-[#e5e7eb] dark:border-[#2d3748]">
            <h3 class="text-[#111418] dark:text-white text-lg font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">groups</span>
                {{ __('messages.bookings_today') }}
                <span class="bg-primary/10 text-primary text-xs font-bold px-2 py-0.5 rounded-full">{{ count($bookings) }}</span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#f8fafc] dark:bg-[#1e2530] border-b border-[#e5e7eb] dark:border-[#2d3748]">
                        <th class="py-3 px-5 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.token_label') }}</th>
                        <th class="py-3 px-5 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.patient_label') }}</th>
                        <th class="py-3 px-5 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.status') }}</th>
                        <th class="py-3 px-5 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.patient_weight') }}</th>
                        <th class="py-3 px-5 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.patient_height') }}</th>
                        <th class="py-3 px-5 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.patient_blood_pressure') }}</th>
                        <th class="py-3 px-5 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.patient_allergies') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2d3748]">
                    @forelse($bookings as $booking)
                    @php
                        $status = $booking['status'] ?? 'pending';
                        $statusLabel = match($status) {
                            'confirmed' => __('messages.confirmed'),
                            'arrived' => __('messages.arrived'),
                            'completed' => __('messages.completed'),
                            'pending' => __('messages.pending'),
                            'acceptedAwaitingPayment' => __('messages.accepted'),
                            default => $status,
                        };
                        $statusColor = match($status) {
                            'confirmed' => 'blue',
                            'arrived' => 'green',
                            'completed' => 'gray',
                            'pending' => 'yellow',
                            default => 'gray',
                        };
                    @endphp
                    <tr class="hover:bg-[#f8fafc] dark:hover:bg-[#2d3748] transition-colors">
                        <td class="py-3 px-5">
                            <span class="inline-flex items-center justify-center px-2.5 py-1 rounded font-bold text-[#111418] dark:text-white bg-[#f0f2f4] dark:bg-[#2d3748] text-sm">{{ $booking['token_number'] ?? '-' }}</span>
                        </td>
                        <td class="py-3 px-5">
                            <p class="text-[#111418] dark:text-white text-sm font-medium">{{ $booking['patient_name'] ?? '-' }}</p>
                        </td>
                        <td class="py-3 px-5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                                @if($statusColor === 'blue') bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                @elseif($statusColor === 'green') bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                @elseif($statusColor === 'yellow') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                                @else bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-300
                                @endif
                            ">
                                {{ $statusLabel }}
                            </span>
                            @if($booking['is_followup'] ?? false)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300">
                                {{ __('messages.followup') }}
                            </span>
                            @endif
                        </td>
                        <td class="py-3 px-5 text-sm text-[#111418] dark:text-white">{{ $booking['patient_weight'] ?? '-' }}</td>
                        <td class="py-3 px-5 text-sm text-[#111418] dark:text-white">{{ $booking['patient_height'] ?? '-' }}</td>
                        <td class="py-3 px-5 text-sm text-[#111418] dark:text-white">{{ $booking['patient_blood_pressure'] ?? '-' }}</td>
                        <td class="py-3 px-5 text-sm text-[#111418] dark:text-white">
                            @if(!empty($booking['patient_allergies']))
                                @if(is_array($booking['patient_allergies']))
                                    {{ implode(', ', $booking['patient_allergies']) }}
                                @else
                                    {{ $booking['patient_allergies'] }}
                                @endif
                            @else
                                <span class="text-[#637388] dark:text-gray-500">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center">
                            <span class="material-symbols-outlined text-4xl text-[#d1d5db] dark:text-[#4a5568] mb-2 block">calendar_today</span>
                            <p class="text-[#637388] dark:text-gray-400 text-sm">{{ __('messages.no_bookings_found') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
