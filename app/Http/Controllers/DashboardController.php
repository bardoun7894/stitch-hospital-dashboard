<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $clinicId = $currentUser['clinic_id'] ?? null;
            $hospitalId = $currentUser['hospital_id'] ?? null;

            $cacheKey = 'dashboard_data_' . md5("{$role}_{$clinicId}_{$hospitalId}");

            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['stats'])) {
                $stats = $cached['stats'];
                $bookings = $cached['bookings'];
                $clinics = $cached['clinics'];
                $alerts = $cached['alerts'];
            } else {
                $allClinics = $this->firebase->getClinics();

                if ($role === 'super_admin') {
                    $bookings = $this->firebase->getBookings();
                    $clinics = $allClinics;
                } elseif ($role === 'hospital_manager' && $hospitalId) {
                    $clinics = array_values(array_filter($allClinics, fn($c) => ($c['hospital_id'] ?? null) === $hospitalId));
                    $firstClinicId = !empty($clinics) ? ($clinics[0]['id'] ?? null) : null;
                    $bookings = $this->firebase->getBookings(20, null, null, $firstClinicId);
                } elseif ($clinicId) {
                    $clinics = array_values(array_filter($allClinics, fn($c) => ($c['id'] ?? null) === $clinicId));
                    $bookings = $this->firebase->getBookings(20, null, null, $clinicId);
                } else {
                    $clinics = $allClinics;
                    $bookings = $this->firebase->getBookings();
                }

                $stats = $this->firebase->getDashboardStats();
                $alerts = $this->firebase->getAlerts();

                Cache::put($cacheKey, compact('stats', 'bookings', 'clinics', 'alerts'), 60);
            }

            return view('dashboard.index', compact('stats', 'bookings', 'clinics', 'alerts'));
        } catch (\Throwable $e) {
            Log::error('Dashboard load error: ' . $e->getMessage());

            $stats = [
                'total' => '0',
                'waiting' => '0',
                'avg_wait' => '0m',
                'no_show' => '0%',
                'total_trend' => '0%',
                'total_trend_type' => 'neutral',
                'waiting_trend' => '0%',
                'waiting_trend_type' => 'neutral',
            ];

            $bookings = ['data' => [], 'next_cursor' => null];
            $clinics = [];
            $alerts = [];

            return view('dashboard.index', compact('stats', 'bookings', 'clinics', 'alerts'))
                ->with('error', __('messages.unknown_error'));
        }
    }
}
