<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeHospitalMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;

class HospitalRegistrationController extends Controller
{
    protected $firebase;

    public function __construct(\App\Services\FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function showForm()
    {
        return view('hospital.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'contact_person' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6|confirmed',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        try {
            // Check if email already exists
            $existingUser = $this->firebase->getUserByEmail($validated['contact_email']);
            if ($existingUser) {
                return back()->with('error', __('messages.email_already_registered'))->withInput();
            }

            // Create hospital with pending status
            $hospitalData = [
                'name' => $validated['name'],
                'name_en' => $validated['name_en'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'contact_person' => $validated['contact_person'],
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $validated['contact_phone'] ?? null,
                'status' => 'pending',
            ];

            $hospitalId = $this->firebase->createHospital($hospitalData);

            if (!$hospitalId) {
                return back()->with('error', __('messages.unknown_error'))->withInput();
            }

            // Create a hospital_manager user account
            $userData = [
                'name' => $validated['contact_person'],
                'email' => $validated['contact_email'],
                'password' => $validated['password'],
                'role' => 'hospital_manager',
                'hospital_id' => $hospitalId,
                'phone' => $validated['contact_phone'] ?? $validated['phone'] ?? '',
            ];

            $userId = $this->firebase->createStaffUser($userData);

            if ($userId) {
                // Update hospital with submitted_by
                $this->firebase->updateHospital($hospitalId, [
                    'submitted_by' => $userId,
                ]);

                // Auto-login the new user
                Session::put('firebase_user_id', $userId);
                Session::put('firebase_user_email', $validated['contact_email']);
                Session::put('firebase_user_role', 'hospital_manager');
                Session::put('firebase_user_name', $validated['contact_person']);
                Session::put('firebase_user_clinic_id', null);
                Session::put('firebase_user_hospital_id', $hospitalId);

                // Send welcome email notification
                try {
                    Mail::to($validated['contact_email'])->send(new WelcomeHospitalMail($hospitalData));
                } catch (\Throwable $e) {
                    Log::warning('Failed to send welcome email: ' . $e->getMessage());
                }

                // Redirect to hospital show page with setup wizard
                return redirect()->route('hospital.show', ['id' => $hospitalId, 'setup' => 1])
                    ->with('success', __('messages.hospital_registered_welcome'));
            }

            return back()->with('error', __('messages.unknown_error'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Hospital registration error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'))->withInput();
        }
    }

    public function success()
    {
        return view('hospital.register-success');
    }
}
