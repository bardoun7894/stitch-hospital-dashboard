<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HospitalController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function index()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $hospitalId = $currentUser['hospital_id'] ?? null;

            $cacheKey = 'hospital_index_' . md5("{$role}_{$hospitalId}");

            $hospitals = Cache::remember($cacheKey, 60, function () use ($role, $hospitalId) {
                if ($role === 'super_admin') {
                    $hospitals = $this->firebaseService->getHospitals();
                } elseif ($hospitalId) {
                    $hospital = $this->firebaseService->getHospitalById($hospitalId);
                    $hospitals = $hospital ? [$hospital] : [];
                } else {
                    $hospitals = [];
                }

                // Get clinic counts per hospital
                $allClinics = $this->firebaseService->getClinics();
                $clinicsByHospital = [];
                foreach ($allClinics as $clinic) {
                    $hid = $clinic['hospital_id'] ?? 'standalone';
                    $clinicsByHospital[$hid] = ($clinicsByHospital[$hid] ?? 0) + 1;
                }

                foreach ($hospitals as &$h) {
                    $h['clinic_count'] = $clinicsByHospital[$h['id']] ?? 0;
                }
                unset($h);

                return $hospitals;
            });

            return view('hospital.index', compact('hospitals'));
        } catch (\Throwable $e) {
            Log::error('Hospital index error: ' . $e->getMessage());
            $hospitals = [];
            return view('hospital.index', compact('hospitals'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function create()
    {
        return view('hospital.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            $hospitalId = $this->firebaseService->createHospital($validated);

            if ($hospitalId) {
                return redirect()->route('hospital.index')->with('success', __('messages.hospital_created'));
            }

            return back()->with('error', __('messages.hospital_create_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Create hospital error: ' . $e->getMessage());
            return back()->with('error', __('messages.hospital_create_failed'))->withInput();
        }
    }

    public function edit($id)
    {
        try {
            $hospital = $this->firebaseService->getHospitalById($id);
            if (!$hospital) {
                return redirect()->route('hospital.index')->with('error', __('messages.hospital_not_found'));
            }

            return view('hospital.edit', compact('hospital'));
        } catch (\Throwable $e) {
            Log::error('Hospital edit form error: ' . $e->getMessage());
            return redirect()->route('hospital.index')->with('error', __('messages.hospital_not_found'));
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            $success = $this->firebaseService->updateHospital($id, $validated);

            if ($success) {
                return redirect()->route('hospital.index')->with('success', __('messages.hospital_updated'));
            }

            return back()->with('error', __('messages.hospital_update_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Update hospital error: ' . $e->getMessage());
            return back()->with('error', __('messages.hospital_update_failed'))->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $success = $this->firebaseService->deleteHospital($id);

            if (request()->expectsJson()) {
                return response()->json(['success' => $success]);
            }

            if ($success) {
                return redirect()->route('hospital.index')->with('success', __('messages.hospital_deleted'));
            }

            return back()->with('error', __('messages.hospital_delete_failed'));
        } catch (\Throwable $e) {
            Log::error('Delete hospital error: ' . $e->getMessage());
            if (request()->expectsJson()) {
                return response()->json(['success' => false], 500);
            }

            return back()->with('error', __('messages.hospital_delete_failed'));
        }
    }
}
