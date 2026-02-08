<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
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

        $users = $this->firebaseService->getStaffUsers($clinicId, $hospitalId);
        $clinics = $this->firebaseService->getClinics();
        $hospitals = $this->firebaseService->getHospitals();

        return view('users.index', compact('users', 'clinics', 'hospitals'));
    }

    public function create()
    {
        $clinics = $this->firebaseService->getClinics();
        $hospitals = $this->firebaseService->getHospitals();
        $roles = $this->getAllowedRoles();
        return view('users.create', compact('clinics', 'hospitals', 'roles'));
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

        $userId = $this->firebaseService->createStaffUser($data);

        if ($userId) {
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

        $clinics = $this->firebaseService->getClinics();
        $hospitals = $this->firebaseService->getHospitals();
        $roles = $this->getAllowedRoles();

        return view('users.edit', compact('user', 'clinics', 'hospitals', 'roles'));
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

        $success = $this->firebaseService->updateStaffUser($id, $data);

        if ($success) {
            return redirect()->route('users.index')->with('success', __('messages.user_updated'));
        }

        return back()->with('error', __('messages.user_update_failed'))->withInput();
    }

    public function destroy(string $id)
    {
        $success = $this->firebaseService->deleteStaffUser($id);

        if ($success) {
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
