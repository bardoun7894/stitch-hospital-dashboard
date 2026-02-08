<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Symfony\Component\HttpFoundation\Response;

class VerifyFirebaseToken
{
    protected $auth;

    public function __construct()
    {
        $credentialsPath = base_path(config('services.firebase.credentials', 'firebase.json'));

        if (file_exists($credentialsPath)) {
            try {
                $factory = (new Factory)->withServiceAccount($credentialsPath);
                $this->auth = $factory->createAuth();
            } catch (\Exception $e) {
                \Log::error('Firebase Auth Init Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->auth) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_unavailable')
            ], 503);
        }

        // Get token from Authorization header
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => __('messages.token_missing')
            ], 401);
        }

        try {
            // Verify the Firebase ID token
            $verifiedIdToken = $this->auth->verifyIdToken($token);
            $uid = $verifiedIdToken->claims()->get('sub');

            // Get user data from Firebase Auth
            $user = $this->auth->getUser($uid);

            // Attach user info to request
            $request->merge([
                'firebase_user' => [
                    'uid' => $uid,
                    'email' => $user->email,
                    'phone' => $user->phoneNumber,
                    'email_verified' => $user->emailVerified,
                ]
            ]);

            return $next($request);

        } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.invalid_token')
            ], 401);
        } catch (\Exception $e) {
            \Log::error('Token Verification Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }
    }
}
