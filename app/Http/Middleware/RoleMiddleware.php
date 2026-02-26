<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Session;

/**
 * RoleMiddleware - Role-based access control for dashboard routes.
 * 
 * Matches the 6-role system from Flutter:
 * - patient: View own bookings only
 * - reception: Manage clinic queue and bookings
 * - doctor: View own schedule
 * - clinic_admin: Configure clinic settings
 * - hospital_manager: View all clinics in hospital
 * - super_admin: Full access
 * 
 * Usage in routes:
 *   Route::middleware(['role:reception,clinic_admin'])->group(function() { ... });
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Allowed roles (comma-separated or multiple params)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Get current user from session (set by LoginController)
        $userId = Session::get('firebase_user_id');
        
        if (!$userId) {
            // No authenticated user - redirect to login
            return redirect()->route('login')->with('error', 'يجب تسجيل الدخول للوصول إلى هذه الصفحة');
        }

        $userRole = Session::get('firebase_user_role', 'patient');

        // Super admin always has access
        if ($userRole === 'super_admin') {
            return $next($request);
        }

        // Check if user's role is in the allowed roles
        $allowedRoles = [];
        foreach ($roles as $role) {
            // Handle comma-separated roles like "role:reception,clinic_admin"
            $allowedRoles = array_merge($allowedRoles, explode(',', $role));
        }

        if (in_array($userRole, $allowedRoles)) {
            return $next($request);
        }

        // Access denied
        abort(403, 'ليس لديك صلاحية للوصول إلى هذه الصفحة');
    }

    /**
     * Helper to check if user has specific permission.
     * Can be used in controllers/views.
     */
    public static function hasRole(string $role): bool
    {
        $userRole = Session::get('firebase_user_role', 'patient');
        
        if (!Session::has('firebase_user_id')) {
            return false;
        }
        
        // Super admin has all permissions
        if ($userRole === 'super_admin') {
            return true;
        }

        return $userRole === $role;
    }

    /**
     * Helper to check if user can manage queue.
     * Matches Flutter's canManageQueue getter.
     */
    public static function canManageQueue(): bool
    {
        return self::hasAnyRole(['reception', 'clinic_admin', 'super_admin']);
    }

    /**
     * Helper to check if user has any of the specified roles.
     */
    public static function hasAnyRole(array $roles): bool
    {
        if (!Session::has('firebase_user_id')) {
            return false;
        }

        $userRole = Session::get('firebase_user_role', 'patient');
        
        if ($userRole === 'super_admin') {
            return true;
        }

        return in_array($userRole, $roles);
    }

    /**
     * Get current authenticated user info from session.
     */
    public static function getCurrentUser(): ?array
    {
        if (!Session::has('firebase_user_id')) {
            return null;
        }

        return [
            'id' => Session::get('firebase_user_id'),
            'name' => Session::get('firebase_user_name'),
            'email' => Session::get('firebase_user_email'),
            'role' => Session::get('firebase_user_role'),
            'clinic_id' => Session::get('firebase_user_clinic_id'),
            'hospital_id' => Session::get('firebase_user_hospital_id'),
            'doctor_id' => Session::get('firebase_user_doctor_id'),
        ];
    }
}
