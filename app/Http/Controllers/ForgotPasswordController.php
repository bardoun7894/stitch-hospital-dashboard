<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMail;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Show the forgot-password form.
     */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Validate email, generate token, store in Firestore, send reset email.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');

        // Check user exists in Firestore
        $user = $this->firebaseService->getUserByEmail($email);

        if (!$user) {
            return back()->withErrors([
                'email' => __('messages.patient_not_found'),
            ])->withInput();
        }

        // Generate token and store in Firestore
        $token = Str::random(64);
        $stored = $this->firebaseService->storePasswordResetToken($email, $token);

        if (!$stored) {
            return back()->withErrors([
                'email' => __('messages.unknown_error'),
            ])->withInput();
        }

        // Build reset URL
        $resetUrl = route('password.reset', ['token' => $token]) . '?email=' . urlencode($email);

        // Send email
        try {
            Mail::to($email)->send(new PasswordResetMail($resetUrl));
        } catch (\Exception $e) {
            \Log::error('Password reset email failed: ' . $e->getMessage());
            return back()->withErrors([
                'email' => __('messages.unknown_error'),
            ])->withInput();
        }

        return back()->with('status', __('messages.reset_link_sent_message'));
    }

    /**
     * Show the reset-password form with the token.
     */
    public function showResetForm(string $token, Request $request)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Validate token + new password, update user password, delete token.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $token = $request->input('token');
        $email = $request->input('email');

        // Look up and validate token
        $tokenData = $this->firebaseService->getPasswordResetToken($token);

        if (!$tokenData) {
            return back()->withErrors([
                'email' => __('messages.invalid_reset_token'),
            ])->withInput();
        }

        // Ensure the token email matches
        if ($tokenData['email'] !== $email) {
            return back()->withErrors([
                'email' => __('messages.invalid_reset_token'),
            ])->withInput();
        }

        // Update user password
        $updated = $this->firebaseService->updateUserPassword($email, $request->input('password'));

        if (!$updated) {
            return back()->withErrors([
                'email' => __('messages.unknown_error'),
            ])->withInput();
        }

        // Clean up token
        $this->firebaseService->deletePasswordResetToken($token);

        return redirect()->route('login')->with('status', __('messages.password_reset_success'));
    }
}
