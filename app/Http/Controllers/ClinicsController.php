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

            // Build a hospital lookup map for all clinics
            $hospitalIds = array_unique(array_filter(array_column($clinics, 'hospital_id')));
            $hospitalsMap = [];
            foreach ($hospitalIds as $hId) {
                $hospital = $this->firebase->getHospitalById($hId);
                if ($hospital) {
                    $hospitalsMap[$hId] = $hospital;
                }
            }

            return view('clinics.index', compact('clinics', 'hospitalsMap'));
        } catch (\Throwable $e) {
            Log::error('Clinics index error: ' . $e->getMessage());
            $clinics = [];
            $hospitalsMap = [];
            return view('clinics.index', compact('clinics', 'hospitalsMap'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function show($id)
    {
        try {
            $clinic = $this->firebase->getClinic($id);
            if (!$clinic) {
                return redirect()->route('clinics.index')->with('error', __('messages.clinic_not_found'));
            }

            // Ownership check: hospital_manager can only view clinics of their hospital
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
                if (($clinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                    abort(403);
                }
            } elseif ($role === 'clinic_admin' && ($currentUser['clinic_id'] ?? null)) {
                if ($id !== $currentUser['clinic_id']) {
                    abort(403);
                }
            }

            // Get hospital info if linked
            $hospital = null;
            if (!empty($clinic['hospital_id'])) {
                $hospital = $this->firebase->getHospitalById($clinic['hospital_id']);
            }

            // Get doctors for this clinic
            $doctors = $this->firebase->getDoctors($id);

            $isSetupMode = request('setup') == 1;

            return view('clinics.show', compact('clinic', 'hospital', 'doctors', 'isSetupMode'));
        } catch (\Throwable $e) {
            Log::error('Clinic show error: ' . $e->getMessage());
            return redirect()->route('clinics.index')->with('error', __('messages.clinic_not_found'));
        }
    }

    public function create()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';

            // Hospital managers can only create clinics for their own hospital
            if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
                $hospital = $this->firebase->getHospitalById($currentUser['hospital_id']);
                $hospitals = $hospital ? [$hospital] : [];
            } else {
                $hospitals = $this->firebase->getHospitals();
            }

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
            'accepted_insurance' => 'nullable|array',
            'accepted_insurance.*' => 'string|max:255',
        ]);

        // Enforce hospital ownership: hospital_manager can only create clinics for their hospital
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';
        if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
            $validated['hospital_id'] = $currentUser['hospital_id'];
        }

        try {
            if (!empty($validated['open_time']) && !empty($validated['close_time'])) {
                $validated['working_hours'] = [
                    'start' => $validated['open_time'],
                    'end' => $validated['close_time'],
                ];
            }

            $validated['accepted_insurance'] = array_values(array_filter($validated['accepted_insurance'] ?? []));

            $id = $this->firebase->createClinic($validated);

            if ($id) {
                $this->firebase->logActivity('clinic.created', [
                    'clinic_id' => $id,
                    'clinic_name' => $validated['name'],
                ]);

                // If in setup mode, redirect back to hospital show page
                if ($request->input('setup') == 1 && !empty($validated['hospital_id'])) {
                    return redirect()->route('hospital.show', ['id' => $validated['hospital_id'], 'setup' => 1])
                        ->with('success', __('messages.clinic_created'));
                }
                return redirect()->route('clinics.show', $id)->with('success', __('messages.clinic_created'));
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

            // Ownership check: hospital_manager can only edit clinics of their hospital
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
                if (($clinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                    abort(403);
                }
                $hospital = $this->firebase->getHospitalById($currentUser['hospital_id']);
                $hospitals = $hospital ? [$hospital] : [];
            } else {
                $hospitals = $this->firebase->getHospitals();
            }

            return view('clinics.edit', compact('clinic', 'hospitals'));
        } catch (\Throwable $e) {
            Log::error('Clinics edit form error: ' . $e->getMessage());
            return redirect()->route('clinics.index')->with('error', __('messages.clinic_not_found'));
        }
    }

    public function update(Request $request, $id)
    {
        // Ownership check: hospital_manager can only update clinics of their hospital
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';
        if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
            $clinic = $this->firebase->getClinic($id);
            if (!$clinic || ($clinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                abort(403);
            }
        }

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
            'accepted_insurance' => 'nullable|array',
            'accepted_insurance.*' => 'string|max:255',
        ]);

        // Enforce hospital ownership: prevent reassigning clinic to another hospital
        if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
            $validated['hospital_id'] = $currentUser['hospital_id'];
        }

        try {
            $updateData = [];
            $fields = ['name', 'name_en', 'hospital_id', 'specialty', 'icon', 'address', 'status', 'geofence_radius', 'daily_capacity'];
            foreach ($fields as $field) {
                if (isset($validated[$field])) {
                    $updateData[$field] = $validated[$field];
                }
            }

            $updateData['accepted_insurance'] = array_values(array_filter($validated['accepted_insurance'] ?? []));

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
                $this->firebase->logActivity('clinic.updated', [
                    'clinic_id' => $id,
                    'clinic_name' => $validated['name'],
                ]);

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
            // Ownership check: hospital_manager can only delete clinics of their hospital
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
                $clinic = $this->firebase->getClinic($id);
                if (!$clinic || ($clinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                    return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
                }
            }

            $success = $this->firebase->deleteClinic($id);

            if ($success) {
                $this->firebase->logActivity('clinic.deleted', [
                    'clinic_id' => $id,
                ]);

                return response()->json(['success' => true, 'message' => __('messages.clinic_deleted')]);
            }

            return response()->json(['success' => false, 'error' => __('messages.clinic_delete_failed')], 500);
        } catch (\Throwable $e) {
            Log::error('Delete clinic error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => __('messages.clinic_delete_failed')], 500);
        }
    }

    /**
     * Show the holiday calendar for a clinic.
     */
    public function holidays($id)
    {
        try {
            $clinic = $this->firebase->getClinic($id);
            if (!$clinic) {
                return redirect()->route('clinics.index')->with('error', __('messages.clinic_not_found'));
            }

            $holidays = $this->firebase->getClinicHolidays($id);

            return view('clinics.holidays', compact('clinic', 'holidays'));
        } catch (\Throwable $e) {
            Log::error('Clinic holidays error: ' . $e->getMessage());
            return redirect()->route('clinics.show', $id)->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Store a new holiday for a clinic.
     */
    public function storeHoliday(Request $request, $id)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'is_recurring' => 'nullable|boolean',
        ]);

        try {
            $holidayData = [
                'date' => $validated['date'],
                'name' => $validated['name'],
                'name_en' => $validated['name_en'] ?? '',
                'is_recurring' => (bool)($validated['is_recurring'] ?? false),
            ];

            $holidayId = $this->firebase->addClinicHoliday($id, $holidayData);

            if ($holidayId) {
                $this->firebase->logActivity('clinic.holiday_added', [
                    'clinic_id' => $id,
                    'holiday_id' => $holidayId,
                    'holiday_date' => $validated['date'],
                    'holiday_name' => $validated['name'],
                ]);

                return redirect()->route('clinics.holidays', $id)->with('success', __('messages.holiday_added'));
            }

            return back()->with('error', __('messages.unknown_error'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Store clinic holiday error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'))->withInput();
        }
    }

    /**
     * Delete a holiday from a clinic.
     */
    public function deleteHoliday($clinicId, $holidayId)
    {
        try {
            $success = $this->firebase->deleteClinicHoliday($clinicId, $holidayId);

            if ($success) {
                $this->firebase->logActivity('clinic.holiday_deleted', [
                    'clinic_id' => $clinicId,
                    'holiday_id' => $holidayId,
                ]);

                return redirect()->route('clinics.holidays', $clinicId)->with('success', __('messages.holiday_deleted'));
            }

            return back()->with('error', __('messages.unknown_error'));
        } catch (\Throwable $e) {
            Log::error('Delete clinic holiday error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'));
        }
    }
}
