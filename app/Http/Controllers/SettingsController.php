<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    protected $firebase;

    public function __construct(\App\Services\FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $clinicId = $currentUser['clinic_id'] ?? null;

            $cacheKey = 'settings_index_' . md5($clinicId ?? 'none');

            $cached = Cache::remember($cacheKey, 60, function () use ($clinicId) {
                return [
                    'settings' => $this->firebase->getSettings(),
                    'clinic' => $clinicId ? $this->firebase->getClinic($clinicId) : null,
                ];
            });

            $settings = $cached['settings'];
            $clinic = $cached['clinic'];

            if (!$clinic) {
                $clinic = [
                    'id' => $clinicId ?? '',
                    'name' => __('messages.clinic'),
                    'geofence_radius' => 100,
                    'follow_up_window_days' => 30,
                    'working_hours' => [
                        'am' => ['start' => '08:00', 'end' => '12:00'],
                        'pm' => ['start' => '16:00', 'end' => '21:00'],
                    ]
                ];
            }

            return view('settings.index', compact('settings', 'clinic'));
        } catch (\Throwable $e) {
            Log::error('Settings page load error: ' . $e->getMessage());

            $settings = [];
            $clinic = [
                'id' => '',
                'name' => __('messages.clinic'),
                'geofence_radius' => 100,
                'follow_up_window_days' => 30,
                'working_hours' => [
                    'am' => ['start' => '08:00', 'end' => '12:00'],
                    'pm' => ['start' => '16:00', 'end' => '21:00'],
                ]
            ];

            return view('settings.index', compact('settings', 'clinic'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function updateClinic(Request $request)
    {
        $data = $request->validate([
            'geofence_radius' => 'required|numeric|min:50|max:1000',
            'follow_up_window_days' => 'required|integer|min:1|max:365',
            'open_time_am' => 'required', // HH:MM
            'close_time_am' => 'required', // HH:MM
            'open_time_pm' => 'required', // HH:MM
            'close_time_pm' => 'required', // HH:MM
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $clinicId = $currentUser['clinic_id'] ?? null;

            if (!$clinicId) {
                return back()->with('error', __('messages.no_clinic_assigned'));
            }

            $updateData = [
                'geofence_radius' => (int)$data['geofence_radius'],
                'follow_up_window_days' => (int)$data['follow_up_window_days'],
                'working_hours' => [
                    'am' => ['start' => $data['open_time_am'], 'end' => $data['close_time_am']],
                    'pm' => ['start' => $data['open_time_pm'], 'end' => $data['close_time_pm']],
                ],
                'location' => [
                    'latitude' => (float)($data['latitude'] ?? 25.2048),
                    'longitude' => (float)($data['longitude'] ?? 55.2708),
                ],
                'updated_at' => new \Datetime()
            ];

            $success = $this->firebase->updateClinic($clinicId, $updateData);

            if ($success) {
                // Invalidate settings cache after update
                Cache::forget('settings_index_' . md5($clinicId));
                return back()->with('success', __('messages.settings_updated'));
            }

            return back()->with('error', __('messages.settings_update_failed'));
        } catch (\Throwable $e) {
            Log::error('Update clinic settings error: ' . $e->getMessage());
            return back()->with('error', __('messages.settings_update_failed'));
        }
    }
}
