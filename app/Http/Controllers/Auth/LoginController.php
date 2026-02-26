<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * LoginController - Handles dashboard authentication via Firebase.
 * 
 * Staff members (reception, clinic_admin, hospital_manager, super_admin)
 * authenticate using email/password verified against Firebase Auth,
 * then their role is fetched from Firestore users collection.
 */
class LoginController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        // If already logged in, redirect to dashboard
        if (Session::has('firebase_user_id')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle login request.
     * 
     * For production: Verify Firebase ID token from client-side auth.
     * For MVP: Accept email and verify against Firestore users collection.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $email = $request->input('email');
        $password = $request->input('password');

        // Look up user by email in Firestore
        $user = $this->firebaseService->getUserByEmail($email);

        if (!$user) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ])->withInput(['email' => $email]);
        }

        // Check if user has staff role (not patient)
        // Map legacy/shorthand roles to canonical role names
        $roleMapping = [
            'admin' => 'super_admin',
            'manager' => 'hospital_manager',
            'nurse' => 'reception',
        ];
        $allowedRoles = ['reception', 'doctor', 'clinic_admin', 'hospital_manager', 'super_admin'];
        $userRole = $user['role'] ?? 'patient';
        $userRole = $roleMapping[$userRole] ?? $userRole;

        if (!in_array($userRole, $allowedRoles)) {
            return back()->withErrors([
                'email' => __('auth.unauthorized_role'),
            ])->withInput(['email' => $email]);
        }

        // Verify password against stored bcrypt hash
        $storedPassword = $user['password_hash'] ?? null;

        if (!$storedPassword) {
            // Account has no password set -- block login until password is configured
            return back()->withErrors([
                'email' => 'هذا الحساب غير مفعّل. يرجى التواصل مع المسؤول لتعيين كلمة مرور.',
            ])->withInput(['email' => $email]);
        }

        if (!password_verify($password, $storedPassword)) {
            return back()->withErrors([
                'password' => __('auth.password'),
            ])->withInput(['email' => $email]);
        }

        // Store user session
        Session::put('firebase_user_id', $user['id']);
        Session::put('firebase_user_email', $email);
        Session::put('firebase_user_role', $userRole);
        Session::put('firebase_user_name', $user['name'] ?? 'User');
        Session::put('firebase_user_clinic_id', $user['clinic_id'] ?? null);
        Session::put('firebase_user_hospital_id', $user['hospital_id'] ?? null);

        // If doctor role, look up the doctor record and store doctor_id
        if ($userRole === 'doctor') {
            $doctorRecord = $this->firebaseService->getDoctorByUserId($user['id']);
            if ($doctorRecord) {
                Session::put('firebase_user_doctor_id', $doctorRecord['id']);
                // Fill clinic_id and hospital_id from doctor record if not set on user
                if (empty($user['clinic_id']) && !empty($doctorRecord['clinic_id'])) {
                    Session::put('firebase_user_clinic_id', $doctorRecord['clinic_id']);
                }
                if (empty($user['hospital_id']) && !empty($doctorRecord['hospital_id'])) {
                    Session::put('firebase_user_hospital_id', $doctorRecord['hospital_id']);
                }
            }
        }

        // Audit log: successful login
        $this->firebaseService->logActivity('login', [
            'email' => $email,
        ], $user['id'], $user['name'] ?? 'User');

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        // Audit log: logout (before clearing session)
        $this->firebaseService->logActivity('logout', [
            'email' => Session::get('firebase_user_email', ''),
        ]);

        Session::forget([
            'firebase_user_id',
            'firebase_user_email',
            'firebase_user_role',
            'firebase_user_name',
            'firebase_user_clinic_id',
            'firebase_user_hospital_id',
            'firebase_user_doctor_id',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
