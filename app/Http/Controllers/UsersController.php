<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class UsersController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function index()
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';

        // Scope users based on current user's role
        $clinicId = null;
        $hospitalId = null;

        if ($role === 'clinic_admin') {
            $clinicId = $currentUser['clinic_id'] ?? null;
        } elseif ($role === 'hospital_manager') {
            $hospitalId = $currentUser['hospital_id'] ?? null;
        }
        // super_admin sees all (no filter)

        $cacheKey = 'users_index_' . md5("{$role}_{$clinicId}_{$hospitalId}");

        $data = Cache::remember($cacheKey, 45, function () use ($role, $clinicId, $hospitalId) {
            $hospitals = $this->firebaseService->getHospitals();
            $clinics = $this->firebaseService->getClinics();

            return [
                'users' => $this->firebaseService->getStaffUsers($clinicId, $hospitalId),
                'clinics' => $clinics,
                'hospitals' => $hospitals,
            ];
        });

        return view('users.index', $data);
    }

    public function create()
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';

        // Filter hospitals and clinics based on role
        if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
            $hospital = $this->firebaseService->getHospitalById($currentUser['hospital_id']);
            $hospitals = $hospital ? [$hospital] : [];
            $clinics = $this->firebaseService->getClinicsForHospital($currentUser['hospital_id']);
        } elseif ($role === 'clinic_admin' && ($currentUser['clinic_id'] ?? null)) {
            $clinic = $this->firebaseService->getClinic($currentUser['clinic_id']);
            $clinics = $clinic ? [$clinic] : [];
            // Get the hospital this clinic belongs to
            $hospitalId = $clinic['hospital_id'] ?? null;
            if ($hospitalId) {
                $hospital = $this->firebaseService->getHospitalById($hospitalId);
                $hospitals = $hospital ? [$hospital] : [];
            } else {
                $hospitals = [];
            }
        } else {
            $hospitals = $this->firebaseService->getHospitals();
            $clinics = $this->firebaseService->getClinics();
        }

        $roles = $this->getAllowedRoles();

        // Build clinic-to-hospital mapping for JS filtering
        $clinicHospitalMap = [];
        foreach ($clinics as $c) {
            $clinicHospitalMap[$c['id']] = $c['hospital_id'] ?? '';
        }

        return view('users.create', compact('clinics', 'hospitals', 'roles', 'clinicHospitalMap'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:reception,doctor,clinic_admin,hospital_manager,super_admin',
            'clinic_id' => 'nullable|string',
            'hospital_id' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
        ]);

        // Enforce ownership: auto-set hospital_id for hospital_manager
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';

        if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
            $data['hospital_id'] = $currentUser['hospital_id'];
            // Validate that selected clinic belongs to this hospital
            if (!empty($data['clinic_id'])) {
                $clinic = $this->firebaseService->getClinic($data['clinic_id']);
                if (!$clinic || ($clinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                    return back()->with('error', __('messages.clinic_not_in_hospital'))->withInput();
                }
            }
        } elseif ($role === 'clinic_admin' && ($currentUser['clinic_id'] ?? null)) {
            $data['clinic_id'] = $currentUser['clinic_id'];
            $clinic = $this->firebaseService->getClinic($currentUser['clinic_id']);
            $data['hospital_id'] = $clinic['hospital_id'] ?? null;
        }

        // Auto-set hospital_id from clinic if not set
        if (empty($data['hospital_id']) && !empty($data['clinic_id'])) {
            $clinic = $this->firebaseService->getClinic($data['clinic_id']);
            if ($clinic) {
                $data['hospital_id'] = $clinic['hospital_id'] ?? null;
            }
        }

        $userId = $this->firebaseService->createStaffUser($data);

        if ($userId) {
            $this->firebaseService->logActivity('user.created', [
                'target_user_id' => $userId,
                'user_name' => $data['name'],
                'user_email' => $data['email'],
                'user_role' => $data['role'],
            ]);

            // If role is doctor and clinic_id is set, also create a doctor record
            if ($data['role'] === 'doctor' && !empty($data['clinic_id'])) {
                $doctorData = [
                    'name' => $data['name'],
                    'name_en' => $data['name'],
                    'specialty' => 'General Medicine',
                    'clinic_id' => $data['clinic_id'],
                    'hospital_id' => $data['hospital_id'] ?? '',
                    'phone' => $data['phone'] ?? '',
                    'status' => 'available',
                    'user_id' => $userId,
                ];
                $doctorId = $this->firebaseService->createDoctor($doctorData);
                if ($doctorId) {
                    $this->firebaseService->logActivity('doctor.created', [
                        'doctor_id' => $doctorId,
                        'doctor_name' => $data['name'],
                        'from_user' => $userId,
                    ]);
                }
            }

            return redirect()->route('users.index')->with('success', __('messages.user_created'));
        }

        return back()->with('error', __('messages.user_creation_failed'))->withInput();
    }

    public function edit(string $id)
    {
        $firestore = $this->firebaseService->getFirestore();
        if (!$firestore) {
            return redirect()->route('users.index')->with('error', __('messages.error'));
        }

        $doc = $firestore->get("users/{$id}");
        if (!$doc) {
            return redirect()->route('users.index')->with('error', __('messages.error'));
        }

        $user = (new \App\Services\FirestoreDocument($doc))->data();
        $user['id'] = $id;

        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';

        // Filter hospitals and clinics based on role
        if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
            // Ownership check
            if (($user['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                abort(403);
            }
            $hospital = $this->firebaseService->getHospitalById($currentUser['hospital_id']);
            $hospitals = $hospital ? [$hospital] : [];
            $clinics = $this->firebaseService->getClinicsForHospital($currentUser['hospital_id']);
        } elseif ($role === 'clinic_admin' && ($currentUser['clinic_id'] ?? null)) {
            // Ownership check
            if (($user['clinic_id'] ?? null) !== $currentUser['clinic_id']) {
                abort(403);
            }
            $clinic = $this->firebaseService->getClinic($currentUser['clinic_id']);
            $clinics = $clinic ? [$clinic] : [];
            $hospitalId = $clinic['hospital_id'] ?? null;
            if ($hospitalId) {
                $hospital = $this->firebaseService->getHospitalById($hospitalId);
                $hospitals = $hospital ? [$hospital] : [];
            } else {
                $hospitals = [];
            }
        } else {
            $hospitals = $this->firebaseService->getHospitals();
            $clinics = $this->firebaseService->getClinics();
        }

        $roles = $this->getAllowedRoles();

        // Build clinic-to-hospital mapping for JS filtering
        $clinicHospitalMap = [];
        foreach ($clinics as $c) {
            $clinicHospitalMap[$c['id']] = $c['hospital_id'] ?? '';
        }

        return view('users.edit', compact('user', 'clinics', 'hospitals', 'roles', 'clinicHospitalMap'));
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|min:6',
            'role' => 'required|string|in:reception,doctor,clinic_admin,hospital_manager,super_admin',
            'clinic_id' => 'nullable|string',
            'hospital_id' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        // Enforce ownership
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';

        if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
            $data['hospital_id'] = $currentUser['hospital_id'];
            if (!empty($data['clinic_id'])) {
                $clinic = $this->firebaseService->getClinic($data['clinic_id']);
                if (!$clinic || ($clinic['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                    return back()->with('error', __('messages.clinic_not_in_hospital'))->withInput();
                }
            }
        } elseif ($role === 'clinic_admin' && ($currentUser['clinic_id'] ?? null)) {
            $data['clinic_id'] = $currentUser['clinic_id'];
            $clinic = $this->firebaseService->getClinic($currentUser['clinic_id']);
            $data['hospital_id'] = $clinic['hospital_id'] ?? null;
        }

        // Auto-set hospital_id from clinic if not set
        if (empty($data['hospital_id']) && !empty($data['clinic_id'])) {
            $clinic = $this->firebaseService->getClinic($data['clinic_id']);
            if ($clinic) {
                $data['hospital_id'] = $clinic['hospital_id'] ?? null;
            }
        }

        $success = $this->firebaseService->updateStaffUser($id, $data);

        if ($success) {
            $this->firebaseService->logActivity('user.updated', [
                'target_user_id' => $id,
                'user_name' => $data['name'],
                'user_email' => $data['email'],
                'user_role' => $data['role'],
            ]);

            return redirect()->route('users.index')->with('success', __('messages.user_updated'));
        }

        return back()->with('error', __('messages.user_update_failed'))->withInput();
    }

    public function destroy(string $id)
    {
        // Ownership check
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';

        if ($role === 'hospital_manager' && ($currentUser['hospital_id'] ?? null)) {
            $firestore = $this->firebaseService->getFirestore();
            if ($firestore) {
                $doc = $firestore->get("users/{$id}");
                if ($doc) {
                    $user = (new \App\Services\FirestoreDocument($doc))->data();
                    if (($user['hospital_id'] ?? null) !== $currentUser['hospital_id']) {
                        return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
                    }
                }
            }
        }

        $success = $this->firebaseService->deleteStaffUser($id);

        if ($success) {
            $this->firebaseService->logActivity('user.deleted', [
                'target_user_id' => $id,
            ]);

            return response()->json(['success' => true, 'message' => __('messages.user_deleted')]);
        }

        return response()->json(['success' => false, 'error' => __('messages.user_delete_failed')], 500);
    }

    protected function getAllowedRoles(): array
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';

        // super_admin can assign any role
        if ($role === 'super_admin') {
            return ['reception', 'doctor', 'clinic_admin', 'hospital_manager', 'super_admin'];
        }

        // hospital_manager can assign up to clinic_admin
        if ($role === 'hospital_manager') {
            return ['reception', 'doctor', 'clinic_admin'];
        }

        // clinic_admin can assign reception and doctor
        return ['reception', 'doctor'];
    }
}
