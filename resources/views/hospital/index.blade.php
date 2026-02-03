@extends('layouts.app')

@section('content')
<main class="flex-1 flex flex-col h-full overflow-hidden bg-background-light dark:bg-background-dark relative">
    
    <!-- TopNavBar -->
    <div class="flex items-center justify-between whitespace-nowrap border-b border-solid border-b-[#f0f2f4] dark:border-[#2d3748] px-6 py-4 bg-white dark:bg-[#1a2027] shrink-0 mb-6 rounded-xl shadow-sm">
         <div class="flex items-center gap-4">
            <h2 class="text-[#111418] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">{{ __('messages.hospital_overview') }}</h2>
            <span class="hidden md:block text-[#637388] dark:text-gray-500 text-sm">|</span>
            <p class="hidden md:block text-[#637388] dark:text-gray-400 text-sm font-medium">{{ date('M d, Y') }}</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="bg-primary/10 text-primary px-3 py-1 rounded-full text-sm font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">domain</span>
                {{ __('messages.stitch_hospital') }}
            </div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="flex-1 overflow-y-auto pb-6 scroll-smooth px-6">
        <div class="max-w-[1600px] mx-auto">
            
            <!-- Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Patients -->
                <div class="bg-white dark:bg-[#1a2027] p-5 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm">
                    <div class="flex items-center gap-4 mb-2">
                         <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg text-blue-600 dark:text-blue-400">
                             <span class="material-symbols-outlined">group</span>
                         </div>
                         <div>
                             <p class="text-[#637388] dark:text-gray-400 text-sm font-medium">{{ __('messages.total_patients') }}</p>
                             <h3 class="text-[#111418] dark:text-white text-2xl font-bold">{{ $data['stats']['total_patients'] }}</h3>
                         </div>
                    </div>
                </div>

                <!-- Active Clinics -->
                <div class="bg-white dark:bg-[#1a2027] p-5 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm">
                     <div class="flex items-center gap-4 mb-2">
                         <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg text-green-600 dark:text-green-400">
                             <span class="material-symbols-outlined">medical_services</span>
                         </div>
                         <div>
                             <p class="text-[#637388] dark:text-gray-400 text-sm font-medium">{{ __('messages.active_clinics') }}</p>
                             <h3 class="text-[#111418] dark:text-white text-2xl font-bold">{{ $data['stats']['active_clinics'] }}</h3>
                         </div>
                    </div>
                </div>

                <!-- Doctors Active -->
                <div class="bg-white dark:bg-[#1a2027] p-5 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm">
                     <div class="flex items-center gap-4 mb-2">
                         <div class="bg-purple-50 dark:bg-purple-900/20 p-3 rounded-lg text-purple-600 dark:text-purple-400">
                             <span class="material-symbols-outlined">stethoscope</span>
                         </div>
                         <div>
                             <p class="text-[#637388] dark:text-gray-400 text-sm font-medium">{{ __('messages.doctors_on_duty_overview') }}</p>
                             <h3 class="text-[#111418] dark:text-white text-2xl font-bold">{{ $data['stats']['doctors_active'] }}</h3>
                         </div>
                    </div>
                </div>

                <!-- Avg Wait -->
                <div class="bg-white dark:bg-[#1a2027] p-5 rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm">
                     <div class="flex items-center gap-4 mb-2">
                         <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg text-orange-600 dark:text-orange-400">
                             <span class="material-symbols-outlined">timer</span>
                         </div>
                         <div>
                             <p class="text-[#637388] dark:text-gray-400 text-sm font-medium">{{ __('messages.avg_wait_time_overview') }}</p>
                             <h3 class="text-[#111418] dark:text-white text-2xl font-bold">{{ $data['stats']['avg_wait_hospital'] }}</h3>
                         </div>
                    </div>
                </div>
            </div>

            <!-- Clinics Grid -->
            <h3 class="text-[#111418] dark:text-white text-lg font-bold mb-4">{{ __('messages.clinic_status_heading') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($data['clinics'] as $clinic)
                <div class="bg-white dark:bg-[#1a2027] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                    <div class="p-5 border-b border-[#e5e7eb] dark:border-[#2d3748] flex items-center justify-between">
                         <div class="flex items-center gap-3">
                            <div class="bg-{{ $clinic['icon_color'] }}-50 dark:bg-{{ $clinic['icon_color'] }}-900/20 p-2 rounded-lg text-{{ $clinic['icon_color'] }}-600 dark:text-{{ $clinic['icon_color'] }}-400">
                                <span class="material-symbols-outlined">{{ $clinic['icon'] }}</span>
                            </div>
                            <h4 class="text-[#111418] dark:text-white font-bold">{{ $clinic['name'] }}</h4>
                         </div>
                         <span class="inline-flex items-center px-2 py-1 rounded text-xs font-bold {{ $clinic['status'] == 'Running' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                             {{ $clinic['status'] }}
                         </span>
                    </div>
                    <div class="p-5">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-[#637388] dark:text-gray-400 text-sm">{{ __('messages.patients_waiting_label') }}</span>
                            <span class="text-[#111418] dark:text-white font-semibold">{{ $clinic['patients_waiting'] }}</span>
                        </div>
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-[#637388] dark:text-gray-400 text-sm">{{ __('messages.doctors_on_duty_label') }}</span>
                            <span class="text-[#111418] dark:text-white font-semibold">{{ $clinic['doctors_on_duty'] }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[#637388] dark:text-gray-400 text-sm">{{ __('messages.average_wait_label') }}</span>
                            <span class="text-[#111418] dark:text-white font-semibold">{{ $clinic['avg_wait'] }}</span>
                        </div>
                    </div>
                    <div class="bg-[#f8fafc] dark:bg-[#1e2530] p-3 text-center">
                        <button class="text-primary text-sm font-semibold hover:underline">{{ __('messages.view_details') }}</button>
                    </div>
                </div>
                @endforeach
            </div>

        </div>
    </div>
</main>
@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Simple polling for real-time updates every 30 seconds
        setInterval(function() {
            // We could use fetch here to update only parts of the UI,
            // but for MVP, a simple partial reload or refresh works.
            console.log('Fetching live updates...');
            // window.location.reload(); // Simple but aggressive
        }, 30000);
    });
</script>
@endsection
@endsection
