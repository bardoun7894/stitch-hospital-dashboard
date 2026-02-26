<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Cache;

class TvViewController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function index(Request $request)
    {
        $clinicId = $request->query('clinic_id');
        $serviceTime = max(1, (int) $request->query('service_time', 10));
        $customAnnouncement = $request->query('announcement');

        $cacheKey = 'tv_queue_data' . ($clinicId ? "_{$clinicId}" : '');

        $data = Cache::remember($cacheKey, 15, function () use ($clinicId) {
            return $this->firebaseService->getQueueData($clinicId);
        });

        // Fetch clinic settings (name, logo) if a clinic_id is provided
        $clinicSettings = [
            'name' => __('messages.city_central_clinic'),
            'logo_url' => null,
        ];

        if ($clinicId) {
            $clinicCacheKey = "tv_clinic_settings_{$clinicId}";
            $clinic = Cache::remember($clinicCacheKey, 300, function () use ($clinicId) {
                return $this->firebaseService->getClinic($clinicId);
            });

            if ($clinic) {
                $localeName = app()->getLocale() === 'ar'
                    ? ($clinic['name'] ?? $clinic['name_en'] ?? null)
                    : ($clinic['name_en'] ?? $clinic['name'] ?? null);

                $clinicSettings['name'] = $localeName ?: $clinicSettings['name'];
                $clinicSettings['logo_url'] = $clinic['logo_url'] ?? $clinic['logo'] ?? null;
            }
        }

        // Calculate estimated wait times for next_up patients
        $nowServingToken = $data['current_serving']['token_num'] ?? $data['queue_state']['now_serving'] ?? 0;

        foreach ($data['next_up'] as $index => &$patient) {
            $position = $index + 1;
            $estimatedMinutes = $position * $serviceTime;
            $patient['estimated_wait'] = $estimatedMinutes;
            $patient['estimated_wait_display'] = "~{$estimatedMinutes} " . __('messages.minutes_short');
        }
        unset($patient);

        // Also enrich bookings that are in waiting state with estimated wait
        $waitingPosition = 0;
        foreach ($data['bookings'] as &$booking) {
            if (in_array($booking['raw_status'] ?? $booking['status'], ['confirmed', 'arrived']) && ($booking['token_num'] ?? 0) > $nowServingToken) {
                $waitingPosition++;
                $estimatedMinutes = $waitingPosition * $serviceTime;
                $booking['estimated_wait'] = $estimatedMinutes;
                $booking['estimated_wait_display'] = "~{$estimatedMinutes} " . __('messages.minutes_short');
            }
        }
        unset($booking);

        // Build announcements list
        $announcements = [];
        if ($customAnnouncement) {
            $announcements[] = $customAnnouncement;
        }
        $announcements[] = __('messages.tv_announcement_arrive_early');
        $announcements[] = __('messages.tv_announcement_id_ready');
        $announcements[] = __('messages.ticker_masks');

        return view('tv.index', [
            'data' => $data,
            'clinicSettings' => $clinicSettings,
            'serviceTime' => $serviceTime,
            'announcements' => $announcements,
        ]);
    }
}
