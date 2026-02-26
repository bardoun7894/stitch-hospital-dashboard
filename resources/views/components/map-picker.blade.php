@props(['latitude' => '', 'longitude' => ''])

@php
    $uid = 'map_' . Str::random(8);
    $lat = $latitude !== '' ? $latitude : null;
    $lng = $longitude !== '' ? $longitude : null;
    $apiKey = config('services.google_maps.api_key');
@endphp

<div id="{{ $uid }}_wrapper">
    {{-- Map container --}}
    <div id="{{ $uid }}" class="w-full h-64 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] mb-3"></div>

    {{-- Controls --}}
    <div class="flex items-center justify-between mb-3">
        <p class="text-xs text-[#637388] dark:text-[#9ca3af]">{{ __('messages.map_click_instruction') }}</p>
        <button type="button" id="{{ $uid }}_locate_btn"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#e5e7eb] dark:border-[#2d3748] text-xs font-medium text-[#111418] dark:text-white hover:bg-gray-50 dark:hover:bg-[#2d3748] transition-colors">
            <span class="material-symbols-outlined text-sm">my_location</span>
            <span id="{{ $uid }}_locate_text">{{ __('messages.use_current_location') }}</span>
        </button>
    </div>

    {{-- Lat/Lng inputs --}}
    <div class="grid grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-[#637388] dark:text-[#9ca3af] mb-1.5">{{ __('messages.latitude') }}</label>
            <input type="number" step="any" name="latitude" id="{{ $uid }}_lat" value="{{ $lat }}"
                class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary"
                placeholder="24.7136" dir="ltr">
        </div>
        <div>
            <label class="block text-sm font-medium text-[#637388] dark:text-[#9ca3af] mb-1.5">{{ __('messages.longitude') }}</label>
            <input type="number" step="any" name="longitude" id="{{ $uid }}_lng" value="{{ $lng }}"
                class="w-full px-4 py-2.5 border border-[#e5e7eb] dark:border-[#2d3748] rounded-lg bg-white dark:bg-[#222b3a] text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary"
                placeholder="46.6753" dir="ltr">
        </div>
    </div>
</div>

<script>
(function() {
    var uid = @json($uid);
    var initialLat = @json($lat ? (float) $lat : null);
    var initialLng = @json($lng ? (float) $lng : null);
    var defaultLat = 24.7136;
    var defaultLng = 46.6753;

    function initMap() {
        var centerLat = initialLat !== null ? initialLat : defaultLat;
        var centerLng = initialLng !== null ? initialLng : defaultLng;
        var center = { lat: centerLat, lng: centerLng };

        var map = new google.maps.Map(document.getElementById(uid), {
            center: center,
            zoom: initialLat !== null ? 15 : 10,
            streetViewControl: false,
            mapTypeControl: false,
            fullscreenControl: false,
        });

        var marker = new google.maps.Marker({
            position: center,
            map: map,
            draggable: true,
            visible: initialLat !== null,
        });

        var latInput = document.getElementById(uid + '_lat');
        var lngInput = document.getElementById(uid + '_lng');

        function updateInputs(lat, lng) {
            latInput.value = parseFloat(lat).toFixed(6);
            lngInput.value = parseFloat(lng).toFixed(6);
        }

        // Click on map to place/move marker
        map.addListener('click', function(e) {
            var pos = e.latLng;
            marker.setPosition(pos);
            marker.setVisible(true);
            updateInputs(pos.lat(), pos.lng());
        });

        // Drag marker
        marker.addListener('dragend', function(e) {
            var pos = e.latLng;
            updateInputs(pos.lat(), pos.lng());
        });

        // Manual input changes update marker
        function onInputChange() {
            var lat = parseFloat(latInput.value);
            var lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                var pos = { lat: lat, lng: lng };
                marker.setPosition(pos);
                marker.setVisible(true);
                map.panTo(pos);
            }
        }
        latInput.addEventListener('change', onInputChange);
        lngInput.addEventListener('change', onInputChange);

        // "Use my current location" button
        var locateBtn = document.getElementById(uid + '_locate_btn');
        var locateText = document.getElementById(uid + '_locate_text');

        locateBtn.addEventListener('click', function() {
            if (!navigator.geolocation) {
                alert(@json(__('messages.location_error')));
                return;
            }

            locateBtn.disabled = true;
            locateText.textContent = @json(__('messages.locating'));

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    var pos = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    };
                    map.setCenter(pos);
                    map.setZoom(15);
                    marker.setPosition(pos);
                    marker.setVisible(true);
                    updateInputs(pos.lat, pos.lng);
                    locateBtn.disabled = false;
                    locateText.textContent = @json(__('messages.use_current_location'));
                },
                function() {
                    alert(@json(__('messages.location_error')));
                    locateBtn.disabled = false;
                    locateText.textContent = @json(__('messages.use_current_location'));
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }

    // Load Google Maps API if not already loaded
    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
        initMap();
    } else {
        var apiKey = @json($apiKey);
        if (!window._gMapsLoading) {
            window._gMapsLoading = true;
            window._gMapsCallbacks = [initMap];
            window._gMapsReady = function() {
                window._gMapsCallbacks.forEach(function(cb) { cb(); });
                window._gMapsCallbacks = [];
                window._gMapsLoaded = true;
            };
            var script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(apiKey) + '&callback=_gMapsReady';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        } else if (window._gMapsLoaded) {
            initMap();
        } else {
            window._gMapsCallbacks.push(initMap);
        }
    }
})();
</script>
