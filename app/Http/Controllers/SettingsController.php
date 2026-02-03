<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    protected $firebase;

    public function __construct(\App\Services\FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        $settings = $this->firebase->getSettings();
        
        // Fetch clinic settings (Assuming 'u001' or user's assigned clinic)
        // ideally: $user = $this->firebase->getCurrentUser(); $clinicid = $user->clinic_id;
        $clinicId = 'u001'; 
        $clinic = $this->firebase->getClinic($clinicId);
        
        if (!$clinic) {
            // Mock if not found to prevent crash
            $clinic = [
                'id' => 'u001',
                'name' => __('messages.stitch_clinic') . ' 1',
                'geofence_radius' => 100, // meters
                'working_hours' => [
                    'start' => '09:00',
                    'end' => '17:00'
                ]
            ];
        }

        return view('settings.index', compact('settings', 'clinic'));
    }

    public function updateClinic(Request $request)
    {
        $clinicId = 'u001'; // Static for MVP
        
        $data = $request->validate([
            'geofence_radius' => 'required|numeric|min:50|max:1000',
            'open_time' => 'required', // HH:MM
            'close_time' => 'required', // HH:MM
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $updateData = [
            'geofence_radius' => (int)$data['geofence_radius'],
            'working_hours' => [
                'start' => $data['open_time'],
                'end' => $data['close_time']
            ],
            'location' => [
                'latitude' => (float)($data['latitude'] ?? 25.2048),
                'longitude' => (float)($data['longitude'] ?? 55.2708),
            ],
            'updated_at' => new \Datetime()
        ];

        $success = $this->firebase->updateClinic($clinicId, $updateData);

        if ($success) {
            return back()->with('success', __('messages.settings_updated'));
        } else {
            return back()->with('error', __('messages.settings_update_failed'));
        }
    }
}

