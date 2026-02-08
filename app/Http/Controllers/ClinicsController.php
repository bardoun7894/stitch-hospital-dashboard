<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClinicsController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $hospitalId = $currentUser['hospital_id'] ?? null;
            $clinicId = $currentUser['clinic_id'] ?? null;

            $cacheKey = 'clinics_index_' . md5("{$role}_{$hospitalId}_{$clinicId}");

            $clinics = Cache::remember($cacheKey, 45, function () use ($role, $hospitalId, $clinicId) {
                if ($role === 'super_admin') {
                    return $this->firebase->getClinics();
                } elseif ($role === 'hospital_manager' && $hospitalId) {
                    return $this->firebase->getClinicsForHospital($hospitalId);
                } elseif ($clinicId) {
                    $clinic = $this->firebase->getClinic($clinicId);
                    return $clinic ? [$clinic] : [];
                } else {
                    return $this->firebase->getClinics();
                }
            });

            return view('clinics.index', compact('clinics'));
        } catch (\Throwable $e) {
            Log::error('Clinics index error: ' . $e->getMessage());
            $clinics = [];
            return view('clinics.index', compact('clinics'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function create()
    {
        try {
            $hospitals = $this->firebase->getHospitals();
            return view('clinics.create', compact('hospitals'));
        } catch (\Throwable $e) {
            Log::error('Clinics create form error: ' . $e->getMessage());
            $hospitals = [];
            return view('clinics.create', compact('hospitals'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'hospital_id' => 'nullable|string',
            'specialty' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'status' => 'required|in:active,paused,closed',
            'geofence_radius' => 'nullable|numeric|min:50|max:1000',
            'daily_capacity' => 'nullable|numeric|min:1',
            'open_time' => 'nullable|string',
            'close_time' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            if (!empty($validated['open_time']) && !empty($validated['close_time'])) {
                $validated['working_hours'] = [
                    'start' => $validated['open_time'],
                    'end' => $validated['close_time'],
                ];
            }

            $id = $this->firebase->createClinic($validated);

            if ($id) {
                return redirect()->route('clinics.index')->with('success', __('messages.clinic_created'));
            }

            return back()->with('error', __('messages.clinic_creation_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Create clinic error: ' . $e->getMessage());
            return back()->with('error', __('messages.clinic_creation_failed'))->withInput();
        }
    }

    public function edit($id)
    {
        try {
            $clinic = $this->firebase->getClinic($id);
            if (!$clinic) {
                return redirect()->route('clinics.index')->with('error', __('messages.clinic_not_found'));
            }

            $hospitals = $this->firebase->getHospitals();
            return view('clinics.edit', compact('clinic', 'hospitals'));
        } catch (\Throwable $e) {
            Log::error('Clinics edit form error: ' . $e->getMessage());
            return redirect()->route('clinics.index')->with('error', __('messages.clinic_not_found'));
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'hospital_id' => 'nullable|string',
            'specialty' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'status' => 'required|in:active,paused,closed',
            'geofence_radius' => 'nullable|numeric|min:50|max:1000',
            'daily_capacity' => 'nullable|numeric|min:1',
            'open_time' => 'nullable|string',
            'close_time' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            $updateData = [];
            $fields = ['name', 'name_en', 'hospital_id', 'specialty', 'icon', 'address', 'status', 'geofence_radius', 'daily_capacity'];
            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            if (!empty($validated['open_time']) && !empty($validated['close_time'])) {
                $updateData['working_hours'] = [
                    'start' => $validated['open_time'],
                    'end' => $validated['close_time'],
                ];
            }

            if (!empty($validated['latitude']) && !empty($validated['longitude'])) {
                $updateData['location'] = [
                    'latitude' => (float)$validated['latitude'],
                    'longitude' => (float)$validated['longitude'],
                ];
            }

            $success = $this->firebase->updateClinic($id, $updateData);

            if ($success) {
                return redirect()->route('clinics.index')->with('success', __('messages.clinic_updated'));
            }

            return back()->with('error', __('messages.clinic_update_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Update clinic error: ' . $e->getMessage());
            return back()->with('error', __('messages.clinic_update_failed'))->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $success = $this->firebase->deleteClinic($id);

            if ($success) {
                return response()->json(['success' => true, 'message' => __('messages.clinic_deleted')]);
            }

            return response()->json(['success' => false, 'error' => __('messages.clinic_delete_failed')], 500);
        } catch (\Throwable $e) {
            Log::error('Delete clinic error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => __('messages.clinic_delete_failed')], 500);
        }
    }
}
