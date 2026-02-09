@extends('layouts.app')

@section('content')
@php
    $counts = $data['status_counts'] ?? [];
    $totalBookings = count($data['bookings'] ?? []);
    $pendingCount = $counts['pending'] ?? 0;
    $arrivedCount = $counts['arrived'] ?? 0;
    $confirmedCount = $counts['confirmed'] ?? 0;
    $acceptedCount = $counts['accepted'] ?? 0;
    $completedCount = $counts['completed'] ?? 0;
    $cancelledCount = $counts['cancelled'] ?? 0;
    $noShowCount = $counts['noShow'] ?? 0;
    $checkedInCount = $arrivedCount + $confirmedCount;
    $isPaused = $data['queue_state']['is_paused'] ?? false;
@endphp

<main class="flex-1 flex flex-col h-full overflow-hidden bg-background-light dark:bg-background-dark relative" x-data="bookingsPage()">

    <!-- TopNavBar -->
    <div class="flex items-center justify-between whitespace-nowrap border-b border-solid border-b-[#f0f2f4] dark:border-[#2d3748] px-6 py-4 bg-white dark:bg-[#1a2027] shrink-0 mb-6 rounded-xl shadow-sm">
         <div class="flex items-center gap-4">
            <h2 class="text-[#111418] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">{{ __('messages.queue_manager_title') }}</h2>
            <span class="hidden md:block text-[#637388] dark:text-gray-500 text-sm">|</span>
            <p class="hidden md:block text-[#637388] dark:text-gray-400 text-sm font-medium">{{ date('M d, Y • h:i A') }}</p>

            @if(\App\Http\Middleware\RoleMiddleware::hasAnyRole(['super_admin', 'hospital_manager']))
            <span class="hidden md:block text-[#637388] dark:text-gray-500 text-sm">|</span>
            <select onchange="window.location='?clinic_id='+this.value" class="h-9 px-3 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] bg-white dark:bg-[#2d3748] text-sm text-[#111418] dark:text-white focus:ring-2 focus:ring-primary/50">
                <option value="">{{ __('messages.all_clinics') }}</option>
                @foreach($clinics ?? [] as $c)
                <option value="{{ $c['id'] }}" {{ ($selectedClinicId ?? '') == $c['id'] ? 'selected' : '' }}>{{ $c['name'] ?? $c['id'] }}</option>
                @endforeach
            </select>
            @endif
        </div>
        <div class="flex items-center gap-4">
            <div class="relative hidden md:flex items-center">
                <span class="material-symbols-outlined absolute left-3 text-[#637388]">search</span>
                <input x-model="searchQuery" class="h-10 w-64 rounded-full border-none bg-[#f0f2f4] dark:bg-[#2d3748] dark:text-white pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary/50" placeholder="{{ __('messages.search_patient_token') }}"/>
            </div>
             <a href="{{ route('tv.index') }}" target="_blank" class="hidden sm:flex h-10 px-4 bg-white dark:bg-[#2d3748] border border-[#dce0e5] dark:border-[#4a5568] hover:bg-gray-50 dark:hover:bg-[#384455] text-[#111418] dark:text-white text-sm font-bold rounded-lg items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-lg">tv</span>
                <span>{{ __('messages.open_tv_view') }}</span>
            </a>
            <a href="{{ route('bookings.create') }}" class="h-10 px-4 bg-primary hover:bg-blue-600 text-white text-sm font-bold rounded-lg flex items-center gap-2 shadow-sm transition-colors">
                <span class="material-symbols-outlined text-lg">add</span>
                <span class="hidden sm:inline">{{ __('messages.new_booking') }}</span>
            </a>
        </div>
    </div>

    <!-- Dashboard Content Scrollable Area -->
    <div class="flex-1 overflow-y-auto pb-6 scroll-smooth">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 max-w-[1600px] mx-auto">
            <!-- LEFT COLUMN: Stats & Queue Control -->
            <div class="xl:col-span-4 flex flex-col gap-6">
                <!-- Stats Row -->
                <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-1 gap-4">
                    <div class="bg-white dark:bg-[#1a2027] p-5 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-[#637388] dark:text-gray-400 text-sm font-medium mb-1">{{ __('messages.bookings_today') }}</p>
                            <h3 class="text-[#111418] dark:text-white text-2xl font-bold">{{ $totalBookings }}</h3>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 px-2 py-1 rounded text-xs font-medium flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">list_alt</span>
                            Total
                        </div>
                    </div>
                    <div class="bg-white dark:bg-[#1a2027] p-5 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-[#637388] dark:text-gray-400 text-sm font-medium mb-1">{{ __('messages.patients_arrived_stat') }}</p>
                            <h3 class="text-[#111418] dark:text-white text-2xl font-bold">{{ $arrivedCount }}</h3>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 px-2 py-1 rounded text-xs font-medium flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">location_on</span>
                            Arrived
                        </div>
                    </div>
                    <div class="col-span-2 lg:col-span-1 bg-white dark:bg-[#1a2027] p-5 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-[#637388] dark:text-gray-400 text-sm font-medium mb-1">Pending</p>
                            <h3 class="text-[#111418] dark:text-white text-2xl font-bold">{{ $pendingCount }}</h3>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 px-2 py-1 rounded text-xs font-medium flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">pending</span>
                            Awaiting
                        </div>
                    </div>
                </div>

                <!-- Queue Control Card -->
                <div class="bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-md flex flex-col overflow-hidden">
                    <div class="bg-primary/5 dark:bg-primary/10 border-b border-[#e5e7eb] dark:border-[#2d3748] p-4 flex items-center justify-between">
                        <h3 class="text-[#111418] dark:text-white font-bold text-lg flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">campaign</span>
                            {{ __('messages.queue_control') }}
                        </h3>
                        @if($isPaused)
                        <span class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 text-xs px-2 py-1 rounded-full font-bold uppercase tracking-wider">Paused</span>
                        @else
                        <span class="bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-xs px-2 py-1 rounded-full font-bold uppercase tracking-wider">{{ __('messages.live_badge') }}</span>
                        @endif
                    </div>
                    <div class="p-6 flex flex-col items-center text-center border-b border-[#e5e7eb] dark:border-[#2d3748]">
                        <p class="text-[#637388] dark:text-gray-400 text-sm font-medium uppercase tracking-wide mb-2">{{ __('messages.now_serving') }}</p>
                        <h1 class="text-primary text-6xl font-black tracking-tight mb-2">{{ $data['current_serving']['token'] ?? '00' }}</h1>
                        <p class="text-[#111418] dark:text-white text-xl font-semibold">{{ $data['current_serving']['patient'] ?? 'Waiting...' }}</p>
                        <p class="text-[#637388] dark:text-gray-400 text-sm">{{ $data['current_serving']['type'] ?? '-' }}</p>
                        @if(!empty($data['current_serving']['id']))
                        <button onclick="recallPatient('{{ $data['current_serving']['id'] }}')" class="mt-2 text-primary hover:text-blue-700 text-xs font-bold uppercase tracking-wider flex items-center gap-1 transition-colors">
                            <span class="material-symbols-outlined text-sm">notifications</span>
                            {{ __('messages.recall') }}
                        </button>
                        @endif
                    </div>
                    <div class="p-6 bg-[#f8fafc] dark:bg-[#1e2530]">
                        <div class="flex gap-3 mb-6">
                            <button onclick="callNext()" class="flex-1 bg-primary hover:bg-blue-600 text-white font-bold py-3 px-4 rounded-lg shadow-sm flex items-center justify-center gap-2 transition-all active:scale-[0.98]">
                                <span class="material-symbols-outlined">notifications_active</span>
                                {{ __('messages.call_next') }}
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <button onclick="skipPatient()" class="bg-white dark:bg-[#2d3748] border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 font-semibold py-2 px-3 rounded-lg text-sm flex items-center justify-center gap-2 transition-colors">
                                <span class="material-symbols-outlined text-lg">block</span>
                                {{ __('messages.skip_no_show') }}
                            </button>
                            <button onclick="togglePauseQueue()" id="pauseButton" class="{{ $isPaused ? 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 border-green-200 dark:border-green-900/50' : 'bg-white dark:bg-[#2d3748] text-[#111418] dark:text-white border-[#dce0e5] dark:border-[#4a5568]' }} border hover:bg-gray-50 dark:hover:bg-[#384455] font-semibold py-2 px-3 rounded-lg text-sm flex items-center justify-center gap-2 transition-colors">
                                <span class="material-symbols-outlined text-lg">{{ $isPaused ? 'play_arrow' : 'pause' }}</span>
                                {{ $isPaused ? 'Resume Queue' : __('messages.pause_queue') }}
                            </button>
                        </div>
                    </div>
                     <div class="p-5">
                        <p class="text-[#637388] dark:text-gray-400 text-xs font-bold uppercase tracking-wider mb-4">{{ __('messages.next_up_in_line') }}</p>
                        <div class="flex flex-col gap-3">
                            @forelse($data['next_up'] as $next)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-[#f8fafc] dark:bg-[#2d3748] border border-[#e5e7eb] dark:border-[#4a5568] {{ $loop->index > 0 ? 'opacity-75' : '' }}">
                                <div class="flex items-center gap-3">
                                    <span class="bg-white dark:bg-[#1a2027] text-[#111418] dark:text-white font-bold px-2 py-1 rounded border border-[#e5e7eb] dark:border-[#4a5568] text-sm">{{ $next['token'] }}</span>
                                    <div>
                                        <p class="text-[#111418] dark:text-white text-sm font-medium">{{ $next['patient'] }}</p>
                                        <p class="text-[#637388] dark:text-gray-400 text-xs">{{ $next['type'] }}</p>
                                    </div>
                                </div>
                                <span class="text-[#637388] dark:text-gray-400 text-xs">{{ $next['wait'] }}</span>
                            </div>
                            @empty
                            <p class="text-[#637388] dark:text-gray-500 text-sm text-center py-3">No patients in queue</p>
                            @endforelse
                        </div>
                    </div>

                    @if(isset($data['skipped']) && count($data['skipped']) > 0)
                    <div class="p-5 border-t border-[#e5e7eb] dark:border-[#2d3748] bg-red-50/50 dark:bg-red-900/10">
                        <p class="text-red-600 dark:text-red-400 text-xs font-bold uppercase tracking-wider mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">warning</span>
                            {{ __('messages.skipped_missed_section') }}
                        </p>
                        <div class="flex flex-col gap-3">
                            @foreach($data['skipped'] as $skipped)
                            <div class="flex items-center justify-between p-3 rounded-lg bg-white dark:bg-[#1a2027] border border-red-100 dark:border-red-900/30 shadow-sm">
                                <div class="flex items-center gap-3">
                                    <span class="text-red-600 dark:text-red-400 font-bold text-sm">{{ $skipped['token'] }}</span>
                                    <div>
                                        <p class="text-[#111418] dark:text-white text-sm font-medium">{{ $skipped['patient'] }}</p>
                                        <p class="text-red-500 text-xs">No Show</p>
                                    </div>
                                </div>
                                <button onclick="reinsertPatient('{{ $skipped['id'] }}')" class="text-primary hover:text-blue-700 p-1.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition-colors" title="Re-insert into Queue">
                                    <span class="material-symbols-outlined text-lg">undo</span>
                                </button>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- RIGHT COLUMN: Bookings Table -->
            <div class="xl:col-span-8 flex flex-col h-full min-h-[600px]">
                <div class="bg-white dark:bg-[#1a2027] border border-[#e5e7eb] dark:border-[#2d3748] rounded-xl shadow-sm flex flex-col h-full">
                    <!-- Header & Filters -->
                    <div class="p-5 border-b border-[#e5e7eb] dark:border-[#2d3748]">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                            <h3 class="text-[#111418] dark:text-white text-xl font-bold">Bookings &amp; Patients</h3>
                        </div>
                         <div class="flex gap-6 overflow-x-auto pb-1 no-scrollbar border-b border-[#f0f2f4] dark:border-[#2d3748]">
                            <button @click="activeTab = 'all'" :class="activeTab === 'all' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                All ({{ $totalBookings }})
                            </button>
                            <button @click="activeTab = 'pending'" :class="activeTab === 'pending' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                Pending ({{ $pendingCount }})
                            </button>
                            <button @click="activeTab = 'accepted'" :class="activeTab === 'accepted' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                Accepted ({{ $acceptedCount }})
                            </button>
                            <button @click="activeTab = 'confirmed'" :class="activeTab === 'confirmed' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                Confirmed ({{ $confirmedCount }})
                            </button>
                            <button @click="activeTab = 'arrived'" :class="activeTab === 'arrived' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                Arrived ({{ $arrivedCount }})
                            </button>
                            <button @click="activeTab = 'completed'" :class="activeTab === 'completed' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                Completed ({{ $completedCount }})
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#f8fafc] dark:bg-[#1e2530] border-b border-[#e5e7eb] dark:border-[#2d3748]">
                                    <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">Token</th>
                                    <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">Patient</th>
                                    <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">Status</th>
                                    <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400">Scheduled</th>
                                    <th class="py-4 px-6 text-xs font-semibold uppercase tracking-wider text-[#637388] dark:text-gray-400 text-right">Actions</th>
                                </tr>
                            </thead>
                             <tbody class="divide-y divide-[#e5e7eb] dark:divide-[#2d3748]">
                                @forelse($data['bookings'] as $booking)
                                @php
                                    $statusColor = match($booking['color'] ?? 'gray') {
                                        'blue' => 'blue',
                                        'green' => 'green',
                                        'yellow' => 'yellow',
                                        'red' => 'red',
                                        default => 'gray'
                                    };
                                    // Map display status to tab filter key
                                    $tabKey = match($booking['status']) {
                                        'Pending' => 'pending',
                                        'Accepted' => 'accepted',
                                        'Confirmed', 'Re-inserted' => 'confirmed',
                                        'Arrived' => 'arrived',
                                        'Completed' => 'completed',
                                        'Cancelled', 'No Show' => 'cancelled',
                                        default => 'other',
                                    };
                                @endphp
                                <tr x-show="matchesFilter('{{ $tabKey }}', '{{ addslashes($booking['patient']) }}')"
                                    class="hover:bg-[#f8fafc] dark:hover:bg-[#2d3748] transition-colors">
                                    <td class="py-4 px-6">
                                        <span class="inline-flex items-center justify-center px-2.5 py-1 rounded font-bold text-[#111418] dark:text-white bg-[#f0f2f4] dark:bg-[#2d3748] text-sm">{{ $booking['token'] }}</span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $booking['avatar'] }}" class="size-8 rounded-full object-cover" loading="lazy">
                                            <div>
                                                <p class="text-[#111418] dark:text-white text-sm font-medium">{{ $booking['patient'] }}</p>
                                                <p class="text-[#637388] dark:text-gray-400 text-xs">{{ $booking['type'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                         <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                                                @if($statusColor === 'blue') bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                                @elseif($statusColor === 'green') bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                                @elseif($statusColor === 'yellow') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                                                @elseif($statusColor === 'red') bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300
                                                @else bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-300
                                                @endif
                                            ">
                                                <span class="size-1.5 rounded-full
                                                    @if($statusColor === 'blue') bg-blue-500
                                                    @elseif($statusColor === 'green') bg-green-500
                                                    @elseif($statusColor === 'yellow') bg-yellow-500
                                                    @elseif($statusColor === 'red') bg-red-500
                                                    @else bg-gray-500
                                                    @endif
                                                "></span>
                                                {{ $booking['status'] }}
                                            </span>

                                            @if($booking['is_followup'] ?? false)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300" title="{{ __('messages.followup') }}">
                                                <span class="material-symbols-outlined text-xs">clinical_notes</span>
                                                {{ __('messages.followup') }}
                                            </span>
                                            @endif

                                            @if($booking['status'] == 'Accepted' && !empty($booking['payment_note']))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300" title="{{ $booking['payment_note'] }}">
                                                <span class="material-symbols-outlined text-xs">payments</span>
                                                {{ __('messages.payment_on_arrival', [], 'الدفع عند الوصول') }}
                                            </span>
                                            @endif

                                            @if($booking['is_arrived'] ?? false)
                                            <div class="inline-flex items-center justify-center p-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-400" title="Patient Arrived">
                                                <span class="material-symbols-outlined text-base">location_on</span>
                                            </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-sm text-[#111418] dark:text-white">{{ $booking['time'] }}</td>
                                     <td class="py-4 px-6 text-right flex justify-end gap-2">
                                        @if($booking['status'] == 'Pending')
                                            <button onclick="updateBooking('{{ $booking['id'] }}', 'accept')" class="bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/40 text-blue-600 dark:text-blue-400 p-1.5 rounded-md transition-colors" title="Accept Booking">
                                                <span class="material-symbols-outlined text-lg">check_circle</span>
                                            </button>
                                            <button onclick="updateBooking('{{ $booking['id'] }}', 'reject')" class="bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 p-1.5 rounded-md transition-colors" title="Reject Booking">
                                                <span class="material-symbols-outlined text-lg">cancel</span>
                                            </button>
                                        @elseif($booking['status'] == 'Accepted')
                                            <button onclick="updateBooking('{{ $booking['id'] }}', 'confirm-payment')" class="bg-green-50 hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/40 text-green-600 dark:text-green-400 p-1.5 rounded-md transition-colors" title="Confirm Payment">
                                                <span class="material-symbols-outlined text-lg">payments</span>
                                            </button>
                                        @elseif($booking['status'] == 'Confirmed' && !($booking['is_arrived'] ?? false))
                                            <button onclick="updateBooking('{{ $booking['id'] }}', 'mark-arrived')" class="bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/20 dark:hover:bg-purple-900/40 text-purple-600 dark:text-purple-400 p-1.5 rounded-md transition-colors" title="Mark Arrived">
                                                <span class="material-symbols-outlined text-lg">flight_land</span>
                                            </button>
                                            <a href="{{ route('bookings.reschedule', $booking['id']) }}" class="bg-orange-50 hover:bg-orange-100 dark:bg-orange-900/20 dark:hover:bg-orange-900/40 text-orange-600 dark:text-orange-400 p-1.5 rounded-md transition-colors" title="Reschedule">
                                                <span class="material-symbols-outlined text-lg">calendar_month</span>
                                            </a>
                                            <button onclick="cancelBooking('{{ $booking['id'] }}')" class="bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 p-1.5 rounded-md transition-colors" title="Cancel">
                                                <span class="material-symbols-outlined text-lg">block</span>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="py-16 text-center">
                                        <span class="material-symbols-outlined text-5xl text-[#d1d5db] dark:text-[#4a5568] mb-3 block">calendar_today</span>
                                        <p class="text-[#637388] dark:text-gray-400 text-lg font-medium">No bookings found</p>
                                        <p class="text-[#9ca3af] dark:text-gray-500 text-sm mt-1">Bookings will appear here when patients make appointments</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function bookingsPage() {
    return {
        activeTab: 'all',
        searchQuery: '',
        matchesFilter(tabKey, patientName) {
            // Tab filter
            if (this.activeTab !== 'all' && tabKey !== this.activeTab) return false;
            // Search filter
            if (this.searchQuery.trim()) {
                return patientName.toLowerCase().includes(this.searchQuery.toLowerCase());
            }
            return true;
        }
    };
}

// Queue state from backend
let queueState = {
    clinicId: '{{ $data['queue_state']['clinic_id'] ?? '' }}',
    doctorId: '{{ $data['queue_state']['doctor_id'] ?? '' }}',
    date: '{{ $data['queue_state']['date'] ?? now()->format('Y-m-d') }}',
    isPaused: {{ $isPaused ? 'true' : 'false' }}
};

async function updateBooking(bookingId, action) {
    if (!confirm('Are you sure you want to ' + action.replace('-', ' ') + ' this booking?')) return;

    try {
        const response = await fetch(`/bookings/${bookingId}/${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const data = await response.json();

        if (data.success) {
            showNotification(data.message || 'Success', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred', 'error');
    }
}

async function callNext() {
    if (!queueState.clinicId || !queueState.doctorId) {
        showNotification('No active queue found. Accept and confirm a booking first.', 'error');
        return;
    }

    try {
        const response = await fetch('/queue/next', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                clinic_id: queueState.clinicId,
                doctor_id: queueState.doctorId,
                date: queueState.date
            })
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Queue advanced to token #' + data.data.now_serving, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.error || data.message || 'No patients waiting', 'error');
        }
    } catch (error) {
        console.error('Error calling next:', error);
        showNotification('An error occurred while advancing the queue', 'error');
    }
}

async function skipPatient() {
    if (!queueState.clinicId || !queueState.doctorId) {
        showNotification('No active queue found.', 'error');
        return;
    }

    const reason = prompt('Reason for skipping (optional):');
    if (reason === null) return;

    try {
        const response = await fetch('/queue/skip', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                clinic_id: queueState.clinicId,
                doctor_id: queueState.doctorId,
                date: queueState.date,
                reason: reason || 'No show'
            })
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Patient skipped', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error skipping patient:', error);
        showNotification('An error occurred while skipping patient', 'error');
    }
}

async function togglePauseQueue() {
    if (!queueState.clinicId || !queueState.doctorId) {
        showNotification('No active queue found.', 'error');
        return;
    }

    const newPausedState = !queueState.isPaused;
    const action = newPausedState ? 'pause' : 'resume';

    if (!confirm(`Are you sure you want to ${action} the queue?`)) return;

    try {
        const response = await fetch('/queue/toggle-pause', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                clinic_id: queueState.clinicId,
                doctor_id: queueState.doctorId,
                date: queueState.date,
                paused: newPausedState
            })
        });

        const data = await response.json();

        if (data.success) {
            showNotification(`Queue ${action}d successfully`, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error toggling pause:', error);
        showNotification('An error occurred', 'error');
    }
}

function showNotification(message, type = 'info') {
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';

    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

async function cancelBooking(bookingId) {
    const reason = prompt('Reason for cancellation:');
    if (reason === null) return;

    try {
        const response = await fetch(`/bookings/${bookingId}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ reason: reason })
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred while cancelling', 'error');
    }
}

async function recallPatient(bookingId) {
    try {
        const response = await fetch('/queue/recall', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ booking_id: bookingId })
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Patient recalled successfully', 'success');
        } else {
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error recalling patient:', error);
        showNotification('An error occurred', 'error');
    }
}

async function reinsertPatient(bookingId) {
    if (!confirm('Re-insert this patient into the active queue?')) return;

    try {
        const response = await fetch('/queue/reinsert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ booking_id: bookingId })
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Patient re-inserted into queue', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error re-inserting patient:', error);
        showNotification('An error occurred', 'error');
    }
}

// Auto-refresh every 2 minutes
setInterval(() => {
    if (!document.querySelector('[role="dialog"]') && !document.querySelector('.fixed.top-4')) {
        window.location.reload();
    }
}, 120000);
</script>
@endsection
