<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\FirebaseService;

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
     * The Firebase service instance.
     */
    protected FirebaseService $firebaseService;

    /**
     * Create a new middleware instance.
     */
    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Allowed roles (comma-separated or multiple params)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Get current user from session or Firebase
        $currentUser = $this->firebaseService->getCurrentUser();
        
        if (!$currentUser) {
            // No authenticated user
            return redirect()->route('login')->with('error', 'يجب تسجيل الدخول للوصول إلى هذه الصفحة');
        }

        $userRole = $currentUser['role'] ?? 'patient';

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
        $firebaseService = app(FirebaseService::class);
        $currentUser = $firebaseService->getCurrentUser();
        
        if (!$currentUser) {
            return false;
        }

        $userRole = $currentUser['role'] ?? 'patient';
        
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
        $firebaseService = app(FirebaseService::class);
        $currentUser = $firebaseService->getCurrentUser();
        
        if (!$currentUser) {
            return false;
        }

        $userRole = $currentUser['role'] ?? 'patient';
        
        if ($userRole === 'super_admin') {
            return true;
        }

        return in_array($userRole, $roles);
    }
}
