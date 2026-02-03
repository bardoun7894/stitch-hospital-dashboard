@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[#111418] dark:text-white leading-tight">{{ __('messages.system_settings') }}</h1>
        <p class="text-sm text-[#637388] dark:text-[#9ca3af]">{{ __('messages.configure_preferences') }}</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Settings Sidebar -->
        <div class="w-full lg:w-64 flex-shrink-0">
            <nav class="space-y-1">
                <a href="#" class="bg-primary/10 text-primary font-medium px-4 py-3 rounded-lg flex items-center gap-3">
                    <span class="material-symbols-outlined">manage_accounts</span>
                    {{ __('messages.account_tab') }}
                </a>
                <a href="#" class="text-[#637388] dark:text-[#9ca3af] hover:bg-gray-50 dark:hover:bg-[#202a37] font-medium px-4 py-3 rounded-lg flex items-center gap-3 transition-colors">
                     <span class="material-symbols-outlined">notifications</span>
                    {{ __('messages.notifications_tab') }}
                </a>
                 <a href="#" class="text-[#637388] dark:text-[#9ca3af] hover:bg-gray-50 dark:hover:bg-[#202a37] font-medium px-4 py-3 rounded-lg flex items-center gap-3 transition-colors">
                     <span class="material-symbols-outlined">security</span>
                    {{ __('messages.security_tab') }}
                </a>
            </nav>
        </div>

        <!-- Settings Content -->
        <div class="flex-1 bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm p-6">
             <h3 class="text-lg font-bold text-[#111418] dark:text-white mb-6">{{ __('messages.general_profile') }}</h3>

             <div class="space-y-6">
                <div>
                     <label class="block text-sm font-medium text-[#111418] dark:text-gray-300 mb-2">{{ __('messages.hospital_name') }}</label>
                     <input type="text" value="{{ $settings['hospital_name'] }}" class="w-full bg-background-light dark:bg-[#111821] border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg px-4 py-2 text-[#111418] dark:text-white focus:ring-2 focus:ring-primary focus:outline-none">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                         <label class="block text-sm font-medium text-[#111418] dark:text-gray-300 mb-2">{{ __('messages.admin_email') }}</label>
                         <input type="email" value="{{ $settings['admin_email'] }}" class="w-full bg-background-light dark:bg-[#111821] border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg px-4 py-2 text-[#111418] dark:text-white focus:ring-2 focus:ring-primary focus:outline-none">
                    </div>
                     <div>
                         <label class="block text-sm font-medium text-[#111418] dark:text-gray-300 mb-2">{{ __('messages.phone_label') }}</label>
                         <input type="tel" value="{{ $settings['phone'] }}" class="w-full bg-background-light dark:bg-[#111821] border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg px-4 py-2 text-[#111418] dark:text-white focus:ring-2 focus:ring-primary focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[#111418] dark:text-gray-300 mb-2">{{ __('messages.theme_preference') }}</label>
                    <div class="flex space-x-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="theme" {{ $settings['theme'] == 'light' ? 'checked' : '' }} class="text-primary focus:ring-primary w-4 h-4">
                            <span class="ml-2 text-[#637388] dark:text-[#9ca3af]">{{ __('messages.light_theme') }}</span>
                        </label>
                         <label class="flex items-center cursor-pointer">
                            <input type="radio" name="theme" {{ $settings['theme'] == 'dark' ? 'checked' : '' }} class="text-primary focus:ring-primary w-4 h-4">
                            <span class="ml-2 text-[#637388] dark:text-[#9ca3af]">{{ __('messages.dark_theme') }}</span>
                        </label>
                    </div>
                </div>

                <div class="pt-6 border-t border-[#e5e7eb] dark:border-[#2d3748] flex justify-end gap-3">
                    <button class="bg-gray-100 dark:bg-[#202a37] text-[#637388] dark:text-white font-medium py-2 px-4 rounded-lg hover:bg-gray-200 dark:hover:bg-[#2d3748] transition-colors">{{ __('messages.cancel') }}</button>
                    <button class="bg-primary text-white font-medium py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors shadow-sm">{{ __('messages.save_changes') }}</button>
                </div>
             </div>
        </div>
        
        <!-- Clinic Configuration Section -->
        <div class="flex-1 bg-white dark:bg-[#1a222e] rounded-xl border border-[#e5e7eb] dark:border-[#2d3748] shadow-sm p-6 mt-6 lg:mt-0">
             <h3 class="text-lg font-bold text-[#111418] dark:text-white mb-6">{{ __('messages.clinic_configuration') }}</h3>

             @if(session('success'))
             <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
             </div>
             @endif
             
             @if(session('error'))
             <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
             </div>
             @endif
             
             <form action="{{ route('settings.updateClinic') }}" method="POST">
                @csrf
                <div class="space-y-6">
                    <div>
                         <label class="block text-sm font-medium text-[#111418] dark:text-gray-300 mb-2">{{ __('messages.clinic_name_label') }}</label>
                         <input type="text" value="{{ $clinic['name'] }}" disabled class="w-full bg-gray-100 dark:bg-[#111821] border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg px-4 py-2 text-[#637388] cursor-not-allowed">
                    </div>

                    <div>
                         <label class="block text-sm font-medium text-[#111418] dark:text-gray-300 mb-2">
                            {{ __('messages.geofence_radius') }}
                            <span class="text-xs text-[#637388] ml-2">{{ __('messages.auto_arrival_distance') }}</span>
                         </label>
                         <div class="flex items-center gap-4">
                             <input type="number" name="geofence_radius" value="{{ $clinic['geofence_radius'] ?? 100 }}" min="50" max="1000" class="w-32 bg-background-light dark:bg-[#111821] border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg px-4 py-2 text-[#111418] dark:text-white focus:ring-2 focus:ring-primary focus:outline-none">
                             <span class="text-sm text-[#637388]">{{ __('messages.distance_unit') }}</span>
                         </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                             <label class="block text-sm font-medium text-[#111418] dark:text-gray-300 mb-2">{{ __('messages.opening_time_label') }}</label>
                             <input type="time" name="open_time" value="{{ $clinic['working_hours']['start'] ?? '09:00' }}" class="w-full bg-background-light dark:bg-[#111821] border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg px-4 py-2 text-[#111418] dark:text-white focus:ring-2 focus:ring-primary focus:outline-none">
                        </div>
                         <div>
                             <label class="block text-sm font-medium text-[#111418] dark:text-gray-300 mb-2">{{ __('messages.closing_time_label') }}</label>
                             <input type="time" name="close_time" value="{{ $clinic['working_hours']['end'] ?? '17:00' }}" class="w-full bg-background-light dark:bg-[#111821] border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg px-4 py-2 text-[#111418] dark:text-white focus:ring-2 focus:ring-primary focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[#111418] dark:text-gray-300 mb-2">{{ __('messages.clinic_location_label') }}</label>
                        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                        <div id="map" class="h-64 bg-gray-100 dark:bg-[#111821] rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] relative overflow-hidden"></div>
                        <p class="text-xs text-[#637388] mt-2">{{ __('messages.map_instruction') }}</p>
                        <input type="hidden" name="latitude" id="latitude" value="{{ $clinic['location']['latitude'] ?? '25.2048' }}">
                        <input type="hidden" name="longitude" id="longitude" value="{{ $clinic['location']['longitude'] ?? '55.2708' }}">
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var lat = {{ $clinic['location']['latitude'] ?? 25.2048 }};
                            var lng = {{ $clinic['location']['longitude'] ?? 55.2708 }};
                            var map = L.map('map').setView([lat, lng], 13);

                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                            }).addTo(map);

                            var marker = L.marker([lat, lng], {draggable: true}).addTo(map);

                            function updateCoords(newLat, newLng) {
                                document.getElementById('latitude').value = newLat;
                                document.getElementById('longitude').value = newLng;
                            }

                            map.on('click', function(e) {
                                marker.setLatLng(e.latlng);
                                updateCoords(e.latlng.lat, e.latlng.lng);
                            });

                            marker.on('dragend', function(e) {
                                updateCoords(e.target.getLatLng().lat, e.target.getLatLng().lng);
                            });
                        });
                    </script>

                    <div class="pt-6 border-t border-[#e5e7eb] dark:border-[#2d3748] flex justify-end gap-3">
                        <button type="submit" class="bg-primary text-white font-medium py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors shadow-sm">Save Configuration</button>
                    </div>
                </div>
             </form>
        </div>
@endsection
