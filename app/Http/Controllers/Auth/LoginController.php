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
        $allowedRoles = ['reception', 'doctor', 'clinic_admin', 'hospital_manager', 'super_admin'];
        $userRole = $user['role'] ?? 'patient';

        if (!in_array($userRole, $allowedRoles)) {
            return back()->withErrors([
                'email' => __('auth.unauthorized_role'),
            ])->withInput(['email' => $email]);
        }

        // For MVP: Simple password check (in production, use Firebase Auth SDK)
        // This is a temporary solution - production should use Firebase Auth
        $storedPassword = $user['password_hash'] ?? null;
        
        if ($storedPassword && !password_verify($password, $storedPassword)) {
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

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        Session::forget([
            'firebase_user_id',
            'firebase_user_email',
            'firebase_user_role',
            'firebase_user_name',
            'firebase_user_clinic_id',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
