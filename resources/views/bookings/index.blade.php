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
            <p id="dashboard-clock" class="hidden md:block text-[#637388] dark:text-gray-400 text-lg font-bold min-w-[220px]" dir="ltr">--:--</p>

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
            @if(\App\Http\Middleware\RoleMiddleware::hasAnyRole(['doctor', 'reception', 'clinic_admin', 'hospital_manager', 'super_admin']))
             <a href="{{ route('tv.index') }}" target="_blank" class="hidden sm:flex h-10 px-4 bg-white dark:bg-[#2d3748] border border-[#dce0e5] dark:border-[#4a5568] hover:bg-gray-50 dark:hover:bg-[#384455] text-[#111418] dark:text-white text-sm font-bold rounded-lg items-center gap-2 transition-colors">
                <span class="material-symbols-outlined text-lg">tv</span>
                <span>{{ __('messages.open_tv_view') }}</span>
            </a>
            @endif
            @if(\App\Http\Middleware\RoleMiddleware::hasAnyRole(['reception', 'clinic_admin', 'hospital_manager', 'super_admin']))
            <a href="{{ route('bookings.create') }}" class="h-10 px-4 bg-primary hover:bg-blue-600 text-white text-sm font-bold rounded-lg flex items-center gap-2 shadow-sm transition-colors">
                <span class="material-symbols-outlined text-lg">add</span>
                <span class="hidden sm:inline">{{ __('messages.new_booking') }}</span>
            </a>
            @endif
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
    <div class="mx-6 mb-4 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300 text-sm font-medium flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">check_circle</span>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mx-6 mb-4 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 text-sm font-medium flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">error</span>
        {{ session('error') }}
    </div>
    @endif

    <!-- Dashboard Content Scrollable Area -->
    <div class="flex-1 overflow-y-auto pb-6 scroll-smooth">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 max-w-[1600px] mx-auto">
            <!-- LEFT COLUMN: Stats & Queue Control -->
            <div class="xl:col-span-4 flex flex-col gap-6">
                <!-- Stats Row -->
                <!-- Stats Row -->
                <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-1 gap-4">
                    <!-- Total Bookings -->
                    <div class="bg-white dark:bg-[#1a2027] p-5 rounded-2xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-indigo-500"></div>
                        <div class="flex items-center justify-between relative z-10">
                            <div>
                                <p class="text-[#637388] dark:text-gray-400 text-sm font-bold uppercase tracking-wider mb-1">{{ __('messages.bookings_today') }}</p>
                                <h3 class="text-[#111418] dark:text-white text-3xl font-black">{{ $totalBookings }}</h3>
                            </div>
                            <div class="size-12 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-2xl">list_alt</span>
                            </div>
                        </div>
                    </div>

                    <!-- Arrived -->
                    <div class="bg-white dark:bg-[#1a2027] p-5 rounded-2xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                         <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-500 to-green-500"></div>
                        <div class="flex items-center justify-between relative z-10">
                            <div>
                                <p class="text-[#637388] dark:text-gray-400 text-sm font-bold uppercase tracking-wider mb-1">{{ __('messages.patients_arrived_stat') }}</p>
                                <h3 class="text-[#111418] dark:text-white text-3xl font-black">{{ $arrivedCount }}</h3>
                            </div>
                            <div class="size-12 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-2xl">location_on</span>
                            </div>
                        </div>
                    </div>

                    <!-- Pending -->
                    <div class="col-span-2 lg:col-span-1 bg-white dark:bg-[#1a2027] p-5 rounded-2xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
                        <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-amber-400 to-orange-500"></div>
                        <div class="flex items-center justify-between relative z-10">
                            <div>
                                <p class="text-[#637388] dark:text-gray-400 text-sm font-bold uppercase tracking-wider mb-1">{{ __('messages.pending') }}</p>
                                <h3 class="text-[#111418] dark:text-white text-3xl font-black">{{ $pendingCount }}</h3>
                            </div>
                            <div class="size-12 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-2xl">pending</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Doctors Today Panel (reception/clinic_admin/hospital_manager) -->
                @if(!empty($doctorsToday ?? []))
                <div class="bg-white dark:bg-[#1a2027] rounded-2xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm overflow-hidden flex flex-col">
                    <div class="bg-gradient-to-r from-indigo-50/80 to-purple-50/80 dark:from-indigo-900/20 dark:to-purple-900/20 border-b border-[#e5e7eb] dark:border-[#2d3748] p-4 backdrop-blur-sm">
                        <h3 class="text-[#111418] dark:text-white font-bold text-base flex items-center gap-2">
                            <span class="material-symbols-outlined text-indigo-600 dark:text-indigo-400">groups</span>
                            {{ __('messages.doctors_today') }}
                        </h3>
                    </div>
                    <div class="divide-y divide-[#e5e7eb] dark:divide-[#2d3748]">
                        @foreach($doctorsToday as $dt)
                        <div class="p-4 flex items-center justify-between hover:bg-indigo-50/30 dark:hover:bg-indigo-900/10 transition-colors group">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 flex items-center justify-center font-bold text-sm shrink-0 border-2 border-white dark:border-[#1a2027] shadow-sm">
                                    {{ mb_substr($dt['name'], 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[#111418] dark:text-white text-sm font-bold truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ $dt['name'] }}</p>
                                    <div class="flex items-center gap-3 text-xs text-[#637388] dark:text-gray-400 mt-0.5">
                                        <span class="flex items-center gap-1 font-medium">
                                            <span class="size-2 rounded-full {{ $dt['online'] === 'Online' ? 'bg-green-500 animate-pulse' : 'bg-gray-400' }}"></span>
                                            {{ $dt['online'] }}
                                        </span>
                                        <span class="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide">
                                            {{ $dt['walkin'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('bookings.create', ['doctor_id' => $dt['id']]) }}" class="shrink-0 size-9 flex items-center justify-center bg-indigo-50 hover:bg-indigo-600 dark:bg-indigo-900/20 dark:hover:bg-indigo-600 text-indigo-600 dark:text-indigo-400 hover:text-white dark:hover:text-white rounded-xl transition-all shadow-sm hover:shadow-md" title="{{ __('messages.take_token') }}">
                                <span class="material-symbols-outlined text-lg">confirmation_number</span>
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Queue Control Card -->
                <div class="bg-white dark:bg-[#1a2027] rounded-2xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-md flex flex-col overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-primary via-indigo-500 to-primary"></div>
                    
                    <div class="p-4 flex items-center justify-between border-b border-[#e5e7eb] dark:border-[#2d3748]">
                        <h3 class="text-[#111418] dark:text-white font-bold text-lg flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">campaign</span>
                            {{ __('messages.queue_control') }}
                        </h3>
                        @if($isPaused)
                        <span class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 text-xs px-3 py-1 rounded-full font-bold uppercase tracking-wider border border-yellow-200 dark:border-yellow-800">{{ __('messages.paused_label') }}</span>
                        @else
                        <span class="bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-xs px-3 py-1 rounded-full font-bold uppercase tracking-wider border border-red-200 dark:border-red-800 flex items-center gap-1">
                            <span class="relative flex h-2 w-2">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                            </span>
                            {{ __('messages.live_badge') }}
                        </span>
                        @endif
                    </div>

                    <div class="p-8 flex flex-col items-center text-center {{ \App\Http\Middleware\RoleMiddleware::hasAnyRole(['doctor', 'reception', 'clinic_admin', 'hospital_manager', 'super_admin']) ? 'border-b border-[#e5e7eb] dark:border-[#2d3748]' : '' }} relative">
                        <!-- Decorative background glow -->
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-48 h-48 bg-primary/5 dark:bg-primary/20 rounded-full blur-3xl pointer-events-none"></div>

                        <p class="text-[#637388] dark:text-gray-400 text-sm font-bold uppercase tracking-widest mb-4 z-10">{{ __('messages.now_serving') }}</p>
                        
                        <div class="relative z-10 mb-6">
                            <h1 class="text-8xl font-black tracking-tighter text-transparent bg-clip-text bg-gradient-to-b from-primary to-indigo-600 drop-shadow-sm">{{ $data['current_serving']['token'] ?? '00' }}</h1>
                        </div>
                        
                        <div class="bg-[#f8fafc] dark:bg-[#1e2530] px-6 py-3 rounded-2xl border border-[#e5e7eb] dark:border-[#2d3748] mb-2 w-full max-w-[280px] z-10">
                            <p class="text-[#111418] dark:text-white text-xl font-bold truncate">{{ $data['current_serving']['patient'] ?? __('messages.waiting_text') }}</p>
                            <p class="text-[#637388] dark:text-gray-400 text-sm font-medium">{{ $data['current_serving']['type'] ?? '-' }}</p>
                        </div>

                        @if(!empty($data['current_serving']['id']) && \App\Http\Middleware\RoleMiddleware::hasAnyRole(['doctor', 'reception', 'clinic_admin', 'hospital_manager', 'super_admin']))
                        <button onclick="recallPatient('{{ $data['current_serving']['id'] }}')" class="mt-4 text-primary hover:text-indigo-600 text-xs font-bold uppercase tracking-wider flex items-center gap-1.5 transition-colors bg-white dark:bg-[#2d3748] px-4 py-2 rounded-full border border-primary/20 dark:border-primary/40 shadow-sm hover:shadow-md z-10">
                            <span class="material-symbols-outlined text-sm">notifications</span>
                            {{ __('messages.recall') }}
                        </button>
                        @endif
                    </div>

                    @if(\App\Http\Middleware\RoleMiddleware::hasAnyRole(['doctor', 'reception', 'clinic_admin', 'hospital_manager', 'super_admin']))
                    <div class="p-6 bg-gradient-to-br from-[#f8fafc] to-white dark:from-[#1e2530] dark:to-[#1a2027]">
                        <button onclick="callNext()" class="w-full bg-gradient-to-r from-primary to-indigo-600 hover:from-primary/90 hover:to-indigo-600/90 text-white font-bold text-lg py-4 px-6 rounded-xl shadow-lg shadow-primary/20 flex items-center justify-center gap-3 transition-all transform active:scale-[0.98] mb-4 group">
                            <span class="material-symbols-outlined text-2xl group-hover:animate-bounce">notifications_active</span>
                            {{ __('messages.call_next') }}
                        </button>

                        <div class="grid grid-cols-2 gap-3">
                            <button onclick="skipPatient()" class="bg-white dark:bg-[#2d3748] border-2 border-transparent hover:border-red-100 dark:hover:border-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/10 font-bold py-2.5 px-3 rounded-xl text-sm flex items-center justify-center gap-2 transition-all shadow-sm">
                                <span class="material-symbols-outlined text-xl">block</span>
                                {{ __('messages.skip_no_show') }}
                            </button>
                            <button onclick="togglePauseQueue()" id="pauseButton" class="{{ $isPaused ? 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 border-green-200 dark:border-green-800' : 'bg-white dark:bg-[#2d3748] text-[#637388] dark:text-gray-300 border-transparent' }} border-2 hover:bg-gray-50 dark:hover:bg-[#384455] font-bold py-2.5 px-3 rounded-xl text-sm flex items-center justify-center gap-2 transition-all shadow-sm">
                                <span class="material-symbols-outlined text-xl">{{ $isPaused ? 'play_arrow' : 'pause' }}</span>
                                {{ $isPaused ? __('messages.resume_queue') : __('messages.pause_queue') }}
                            </button>
                        </div>
                    </div>
                    @endif
                    
                     <div class="p-5 bg-white dark:bg-[#1a2027]">
                        <p class="text-[#637388] dark:text-gray-400 text-xs font-bold uppercase tracking-wider mb-4 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                            {{ __('messages.next_up_in_line') }}
                        </p>
                        <div class="flex flex-col gap-3">
                            @forelse($data['next_up'] as $next)
                            <div class="flex items-center justify-between p-3.5 rounded-xl bg-[#f8fafc] dark:bg-[#1e2530] border border-[#e5e7eb] dark:border-[#2d3748] hover:border-primary/30 dark:hover:border-primary/30 transition-colors {{ $loop->index > 0 ? 'opacity-80' : '' }}">
                                <div class="flex items-center gap-3">
                                    <span class="bg-white dark:bg-[#1a2027] text-[#111418] dark:text-white font-black px-2.5 py-1.5 rounded-lg border border-[#e5e7eb] dark:border-[#4a5568] text-sm shadow-sm min-w-[3rem] text-center">{{ $next['token'] }}</span>
                                    <div>
                                        <p class="text-[#111418] dark:text-white text-sm font-bold">{{ $next['patient'] }}</p>
                                        <p class="text-[#637388] dark:text-gray-400 text-xs font-medium">{{ $next['type'] }}</p>
                                    </div>
                                </div>
                                <span class="text-primary dark:text-primary-light text-xs font-bold bg-primary/10 dark:bg-primary/20 px-2 py-1 rounded text-center min-w-[4rem]">
                                    {{ $next['wait'] }}
                                </span>
                            </div>
                            @empty
                            <div class="py-8 text-center flex flex-col items-center justify-center text-[#637388] dark:text-gray-500">
                                <span class="material-symbols-outlined text-3xl mb-2 opacity-50">hourglass_disabled</span>
                                <p class="text-sm font-medium">{{ __('messages.no_patients_in_queue') }}</p>
                            </div>
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
                                    <span class="text-red-600 dark:text-red-400 font-bold text-sm bg-red-50 dark:bg-red-900/20 px-2 py-1 rounded">{{ $skipped['token'] }}</span>
                                    <div>
                                        <p class="text-[#111418] dark:text-white text-sm font-medium">{{ $skipped['patient'] }}</p>
                                        <p class="text-red-500 text-xs">{{ __('messages.no_show') }}</p>
                                    </div>
                                </div>
                                @if(\App\Http\Middleware\RoleMiddleware::hasAnyRole(['doctor', 'reception', 'clinic_admin', 'hospital_manager', 'super_admin']))
                                <button onclick="reinsertPatient('{{ $skipped['id'] }}')" class="text-primary hover:text-white hover:bg-primary p-1.5 rounded-lg transition-all" title="Re-insert into Queue">
                                    <span class="material-symbols-outlined text-lg">undo</span>
                                </button>
                                @endif
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
                            <h3 class="text-[#111418] dark:text-white text-xl font-bold">{{ __('messages.bookings_patients_title') }}</h3>
                        </div>
                         <div class="flex gap-6 overflow-x-auto pb-1 no-scrollbar border-b border-[#f0f2f4] dark:border-[#2d3748]">
                             <button @click="activeTab = 'all'" :class="activeTab === 'all' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                {{ __('messages.all_filter') }} ({{ $totalBookings }})
                            </button>
                            <button @click="activeTab = 'pending'" :class="activeTab === 'pending' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                {{ __('messages.pending') }} ({{ $pendingCount }})
                            </button>
                            <button @click="activeTab = 'accepted'" :class="activeTab === 'accepted' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                {{ __('messages.accepted') }} ({{ $acceptedCount }})
                            </button>
                            <button @click="activeTab = 'confirmed'" :class="activeTab === 'confirmed' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                {{ __('messages.confirmed') }} ({{ $confirmedCount }})
                            </button>
                            <button @click="activeTab = 'arrived'" :class="activeTab === 'arrived' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                {{ __('messages.arrived') }} ({{ $arrivedCount }})
                            </button>
                            <button @click="activeTab = 'completed'" :class="activeTab === 'completed' ? 'border-primary text-primary font-semibold' : 'border-transparent text-[#637388] dark:text-gray-400 hover:text-[#111418] dark:hover:text-white font-medium'" class="pb-3 border-b-2 text-sm whitespace-nowrap transition-colors">
                                {{ __('messages.completed') }} ({{ $completedCount }})
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto flex-1 custom-scrollbar">
                        <table class="w-full text-left border-collapse relative">
                            <thead>
                                <tr class="bg-[#f8fafc]/90 dark:bg-[#1e2530]/90 backdrop-blur-sm sticky top-0 z-20 border-b border-[#e5e7eb] dark:border-[#2d3748]">
                                    <th class="py-4 px-6 text-xs font-bold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.token_label') }}</th>
                                    <th class="py-4 px-6 text-xs font-bold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.patient_label') }}</th>
                                    <th class="py-4 px-6 text-xs font-bold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.status') }}</th>
                                    <th class="py-4 px-6 text-xs font-bold uppercase tracking-wider text-[#637388] dark:text-gray-400">{{ __('messages.scheduled_label') }}</th>
                                    <th class="py-4 px-6 text-xs font-bold uppercase tracking-wider text-[#637388] dark:text-gray-400 text-right">{{ __('messages.actions') }}</th>
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
                                    class="hover:bg-[#f8fafc] dark:hover:bg-[#2d3748] transition-colors group">
                                    <td class="py-4 px-6">
                                        <span class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg font-black text-[#111418] dark:text-white bg-white dark:bg-[#1a2027] border border-[#e5e7eb] dark:border-[#4a5568] text-sm shadow-sm">{{ $booking['token'] }}</span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-3">
                                            <div class="relative">
                                                <img src="{{ $booking['avatar'] }}" class="size-10 rounded-full object-cover border-2 border-white dark:border-[#1a2027] shadow-sm" loading="lazy">
                                                @if($booking['is_arrived'] ?? false)
                                                <span class="absolute -bottom-1 -right-1 flex h-4 w-4">
                                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                  <span class="relative inline-flex rounded-full h-4 w-4 bg-green-500 border-2 border-white dark:border-[#1a2027]"></span>
                                                </span>
                                                @endif
                                            </div>
                                            <div>
                                                <p class="text-[#111418] dark:text-white text-sm font-bold group-hover:text-primary transition-colors">{{ $booking['patient'] }}</p>
                                                <p class="text-[#637388] dark:text-gray-400 text-xs font-medium">{{ $booking['type'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                         <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold backdrop-blur-md shadow-sm border
                                                @if($statusColor === 'blue') bg-blue-50/80 text-blue-700 border-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800
                                                @elseif($statusColor === 'green') bg-green-50/80 text-green-700 border-green-100 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800
                                                @elseif($statusColor === 'yellow') bg-yellow-50/80 text-yellow-700 border-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-800
                                                @elseif($statusColor === 'red') bg-red-50/80 text-red-700 border-red-100 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800
                                                @else bg-gray-50/80 text-gray-700 border-gray-200 dark:bg-gray-800/50 dark:text-gray-300 dark:border-gray-700
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
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-100 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800" title="{{ __('messages.followup') }}">
                                                <span class="material-symbols-outlined text-xs">clinical_notes</span>
                                                {{ __('messages.followup') }}
                                            </span>
                                            @endif

                                            @if($booking['status'] == 'Accepted' && !empty($booking['payment_note']))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-50 text-orange-700 border border-orange-100 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-800" title="{{ $booking['payment_note'] }}">
                                                <span class="material-symbols-outlined text-xs">payments</span>
                                                {{ __('messages.payment_on_arrival') }}
                                            </span>
                                            @endif

                                            @php
                                                $paymentStatus = $booking['payment_status'] ?? null;
                                            @endphp
                                            @if($paymentStatus === 'cash')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                <span class="material-symbols-outlined text-xs">payments</span>
                                                {{ __('messages.payment_cash') }}
                                            </span>
                                            @elseif($paymentStatus === 'stripe' || $paymentStatus === 'paid')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                                                <span class="material-symbols-outlined text-xs">credit_card</span>
                                                {{ __('messages.payment_online') }}
                                            </span>
                                            @elseif($paymentStatus === 'waived_followup')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">
                                                <span class="material-symbols-outlined text-xs">volunteer_activism</span>
                                                {{ __('messages.payment_followup_free') }}
                                            </span>
                                            @elseif($paymentStatus === 'unpaid' && $booking['status'] !== 'Pending')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300">
                                                <span class="material-symbols-outlined text-xs">money_off</span>
                                                {{ __('messages.payment_unpaid') }}
                                            </span>
                                            @elseif($paymentStatus === 'pay_on_arrival')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">
                                                <span class="material-symbols-outlined text-xs">store</span>
                                                {{ __('messages.payment_pay_on_arrival') }}
                                            </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-sm font-medium text-[#111418] dark:text-white tabular-nums">{{ $booking['time'] }}</td>
                                     <td class="py-4 px-6 text-right flex justify-end gap-2">
                                        @if(\App\Http\Middleware\RoleMiddleware::hasAnyRole(['doctor', 'reception', 'clinic_admin', 'hospital_manager', 'super_admin']))
                                        @if($booking['status'] == 'Pending')
                                            <button onclick="updateBooking('{{ $booking['id'] }}', 'accept')" class="bg-blue-50 hover:bg-blue-600 dark:bg-blue-900/20 dark:hover:bg-blue-600 text-blue-600 dark:text-blue-400 hover:text-white p-2 rounded-lg transition-all shadow-sm" title="Accept Booking">
                                                <span class="material-symbols-outlined text-lg">check_circle</span>
                                            </button>
                                            <button onclick="updateBooking('{{ $booking['id'] }}', 'reject')" class="bg-red-50 hover:bg-red-600 dark:bg-red-900/20 dark:hover:bg-red-600 text-red-600 dark:text-red-400 hover:text-white p-2 rounded-lg transition-all shadow-sm" title="Reject Booking">
                                                <span class="material-symbols-outlined text-lg">cancel</span>
                                            </button>
                                        @elseif($booking['status'] == 'Accepted')
                                            <button onclick="updateBooking('{{ $booking['id'] }}', 'confirm-payment')" class="bg-green-50 hover:bg-green-600 dark:bg-green-900/20 dark:hover:bg-green-600 text-green-600 dark:text-green-400 hover:text-white p-2 rounded-lg transition-all shadow-sm" title="Confirm Payment">
                                                <span class="material-symbols-outlined text-lg">payments</span>
                                            </button>
                                            @if(\App\Http\Middleware\RoleMiddleware::hasAnyRole(['doctor', 'reception', 'clinic_admin', 'hospital_manager', 'super_admin']))
                                            <form action="{{ route('bookings.cash-payment', $booking['id']) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" onclick="return confirm('{{ __('messages.confirm_cash_payment') }}')" class="bg-emerald-50 hover:bg-emerald-600 dark:bg-emerald-900/20 dark:hover:bg-emerald-600 text-emerald-600 dark:text-emerald-400 hover:text-white p-2 rounded-lg transition-all shadow-sm" title="{{ __('messages.record_cash_payment') }}">
                                                    <span class="material-symbols-outlined text-lg">local_atm</span>
                                                </button>
                                            </form>
                                            @endif
                                        @elseif($booking['status'] == 'Confirmed' && !($booking['is_arrived'] ?? false))
                                            <button onclick="updateBooking('{{ $booking['id'] }}', 'mark-arrived')" class="bg-purple-50 hover:bg-purple-600 dark:bg-purple-900/20 dark:hover:bg-purple-600 text-purple-600 dark:text-purple-400 hover:text-white p-2 rounded-lg transition-all shadow-sm" title="Mark Arrived">
                                                <span class="material-symbols-outlined text-lg">flight_land</span>
                                            </button>
                                            <a href="{{ route('bookings.reschedule', $booking['id']) }}" class="bg-orange-50 hover:bg-orange-600 dark:bg-orange-900/20 dark:hover:bg-orange-600 text-orange-600 dark:text-orange-400 hover:text-white p-2 rounded-lg transition-all shadow-sm" title="Reschedule">
                                                <span class="material-symbols-outlined text-lg">calendar_month</span>
                                            </a>
                                            <button onclick="cancelBooking('{{ $booking['id'] }}')" class="bg-red-50 hover:bg-red-600 dark:bg-red-900/20 dark:hover:bg-red-600 text-red-600 dark:text-red-400 hover:text-white p-2 rounded-lg transition-all shadow-sm" title="Cancel">
                                                <span class="material-symbols-outlined text-lg">block</span>
                                            </button>
                                        @endif
                                        @endif {{-- end role check for booking actions --}}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="py-20 text-center">
                                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-50 dark:bg-gray-800 mb-4">
                                            <span class="material-symbols-outlined text-4xl text-gray-300 dark:text-gray-600">calendar_today</span>
                                        </div>
                                        <p class="text-[#111418] dark:text-white text-lg font-bold">{{ __('messages.no_bookings_found') }}</p>
                                        <p class="text-[#637388] dark:text-gray-400 text-sm mt-1">{{ __('messages.bookings_will_appear') }}</p>
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

// ---- Live Date/Time for Dashboard Header ----
function updateDashboardClock() {
    const clockElement = document.getElementById('dashboard-clock');
    if (!clockElement) return;

    const now = new Date();
    // Use the document language (or fallback to 'en') for localization
    const locale = document.documentElement.lang === 'ar' ? 'ar-EG' : 'en-US';
    
    // Format: "Feb 19, 2026 • 11:15 PM" (or Arabic equivalent)
    const options = { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric', 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    };

    // We construct the string manually to match the specific "Date • Time" format more easily across locales
    const datePart = new Intl.DateTimeFormat(locale, { month: 'short', day: 'numeric', year: 'numeric' }).format(now);
    const timePart = new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: '2-digit', hour12: true }).format(now);
    
    clockElement.textContent = `${datePart} • ${timePart}`;
}

// Update clock immediately and then every second
updateDashboardClock();
setInterval(updateDashboardClock, 1000);

async function updateBooking(bookingId, action) {
    if (!confirm('{{ __("messages.confirm_booking_action") }}'.replace(':action', action.replace('-', ' ')))) return;

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
            showNotification('{{ __("messages.queue_advanced_msg") }}'.replace(':token', data.data.now_serving), 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.error || data.message || '{{ __("messages.no_patients_waiting") }}', 'error');
        }
    } catch (error) {
        console.error('Error calling next:', error);
        showNotification('{{ __("messages.error_advancing_queue") }}', 'error');
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
            showNotification('{{ __("messages.patient_skipped") }}', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error skipping patient:', error);
        showNotification('{{ __("messages.error_skipping_patient") }}', 'error');
    }
}

async function togglePauseQueue() {
    if (!queueState.clinicId || !queueState.doctorId) {
        showNotification('No active queue found.', 'error');
        return;
    }

    const newPausedState = !queueState.isPaused;
    const action = newPausedState ? 'pause' : 'resume';

    const confirmMsg = action === 'pause' ? '{{ __("messages.confirm_queue_pause") }}' : '{{ __("messages.confirm_queue_resume") }}';
    if (!confirm(confirmMsg)) return;

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
            const actionDisplay = action === 'pause' ? '{{ __("messages.pause_queue") }}' : '{{ __("messages.resume_queue") }}';
            showNotification('{{ __("messages.queue_action_success") }}'.replace(':action', actionDisplay), 'success');
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
    const reason = prompt('{{ __("messages.reason_for_cancellation") }}');
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
        showNotification('{{ __("messages.cancel_error") }}', 'error');
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
            showNotification('{{ __("messages.patient_recalled") }}', 'success');
        } else {
            showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error recalling patient:', error);
        showNotification('An error occurred', 'error');
    }
}

async function reinsertPatient(bookingId) {
    if (!confirm('{{ __("messages.confirm_reinsert") }}')) return;

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
            showNotification('{{ __("messages.patient_reinserted") }}', 'success');
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
