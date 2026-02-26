<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

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

            // Doctor gets a dedicated dashboard
            if ($role === 'doctor') {
                return $this->doctorDashboard($currentUser);
            }

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
                    $clinicIds = array_map(fn($c) => $c['id'] ?? '', $clinics);
                    $allBookings = $this->firebase->getBookings(100);
                    $bookings = array_values(array_filter($allBookings['data'] ?? [], function ($b) use ($clinicIds) {
                        return in_array($b['clinic_id'] ?? $b['clinic'] ?? '', $clinicIds);
                    }));
                    $bookings = ['data' => array_slice($bookings, 0, 20), 'next_cursor' => null];
                } elseif ($clinicId) {
                    $clinics = array_values(array_filter($allClinics, fn($c) => ($c['id'] ?? null) === $clinicId));
                    $bookings = $this->firebase->getBookings(20, null, null, $clinicId);
                } else {
                    $clinics = $allClinics;
                    $bookings = $this->firebase->getBookings();
                }

                $stats = $this->firebase->getDashboardStats();
                $alerts = $this->firebase->getAlerts();

                Cache::put($cacheKey, compact('stats', 'bookings', 'clinics', 'alerts'), 300);
            }

            // Enrich clinics with hospital_status and raw clinic_status
            $hospitals = $this->firebase->getHospitals();
            $hospitalStatusMap = [];
            $hospitalNameMap = [];
            foreach ($hospitals as $h) {
                $hospitalStatusMap[$h['id'] ?? ''] = $h['status'] ?? 'active';
                $hospitalNameMap[$h['id'] ?? ''] = $h['name'] ?? '';
            }
            foreach ($clinics as &$c) {
                $hid = $c['hospital_id'] ?? '';
                $c['hospital_status'] = $hospitalStatusMap[$hid] ?? 'active';
                $c['hospital_name'] = $hospitalNameMap[$hid] ?? '';
            }
            unset($c);

            // Pending hospitals count for super_admin
            $pendingHospitalsCount = 0;
            if ($role === 'super_admin') {
                $pendingHospitalsCount = count(array_filter($hospitals, fn($h) => ($h['status'] ?? '') === 'pending'));
            }

            // Reminder stats for clinic_admin+ roles
            $reminderStats = null;
            $adminRoles = ['clinic_admin', 'hospital_manager', 'super_admin'];
            if (in_array($role, $adminRoles)) {
                try {
                    $tomorrow = (new \DateTime('+1 day'))->format('Y-m-d');
                    $tomorrowBookings = $this->firebase->getBookingsForDate($tomorrow, [
                        'confirmed',
                        'acceptedAwaitingPayment',
                    ]);
                    $totalReminders = count($tomorrowBookings);
                    $sentReminders = 0;
                    foreach ($tomorrowBookings as $b) {
                        if (!empty($b['reminder_sent'])) {
                            $sentReminders++;
                        }
                    }
                    $reminderStats = [
                        'sent' => $sentReminders,
                        'total' => $totalReminders,
                    ];
                } catch (\Throwable $e) {
                    Log::warning('Failed to load reminder stats: ' . $e->getMessage());
                }
            }

            return view('dashboard.index', compact('stats', 'bookings', 'clinics', 'alerts', 'currentUser', 'pendingHospitalsCount', 'reminderStats'));
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
            $pendingHospitalsCount = 0;
            $reminderStats = null;
            $currentUser = $currentUser ?? ['name' => 'User'];

            return view('dashboard.index', compact('stats', 'bookings', 'clinics', 'alerts', 'currentUser', 'pendingHospitalsCount', 'reminderStats'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Render the dedicated doctor dashboard.
     */
    protected function doctorDashboard(array $currentUser)
    {
        try {
            $doctorId = $currentUser['doctor_id'] ?? null;
            $clinicId = $currentUser['clinic_id'] ?? null;

            $doctor = $doctorId ? $this->firebase->getDoctorById($doctorId) : null;
            $clinic = $clinicId ? $this->firebase->getClinicById($clinicId) : null;

            $today = date('Y-m-d');
            $bookings = $doctorId ? $this->firebase->getBookingsForDoctor($doctorId, $today) : [];

            $queueState = ($clinicId && $doctorId)
                ? $this->firebase->getDoctorQueueState($clinicId, $doctorId, $today)
                : ['now_serving' => 0, 'last_issued' => 0, 'is_paused' => false, 'remaining' => 0];

            return view('dashboard.doctor', compact('currentUser', 'doctor', 'clinic', 'bookings', 'queueState'));
        } catch (\Throwable $e) {
            Log::error('Doctor dashboard error: ' . $e->getMessage());
            return view('dashboard.doctor', [
                'currentUser' => $currentUser,
                'doctor' => null,
                'clinic' => null,
                'bookings' => [],
                'queueState' => ['now_serving' => 0, 'last_issued' => 0, 'is_paused' => false, 'remaining' => 0],
            ])->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Return JSON chart data for the dashboard charts.
     * - bookings_7days: array of {date, count} for the last 7 days
     * - status_breakdown: counts by status for today's bookings
     * - doctor_utilization: array of {name, bookings, capacity} per doctor
     */
    public function chartData(Request $request): JsonResponse
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $clinicId = $currentUser['clinic_id'] ?? null;

            $today = date('Y-m-d');
            $sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));

            // ── Bookings for last 7 days ──
            $rangeBookings = $this->firebase->getBookingsForDateRange($sevenDaysAgo, $today, $clinicId);

            // Build date => count map
            $dateCounts = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $dateCounts[$date] = 0;
            }
            foreach ($rangeBookings as $booking) {
                $date = $booking['scheduled_date'] ?? null;
                if ($date && isset($dateCounts[$date])) {
                    $dateCounts[$date]++;
                }
            }

            $bookings7days = [];
            foreach ($dateCounts as $date => $count) {
                $bookings7days[] = ['date' => $date, 'count' => $count];
            }

            // ── Status breakdown for today ──
            $todayBookings = $this->firebase->getBookingsForDateRange($today, $today, $clinicId);

            $statusBreakdown = [
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'noShow' => 0,
                'pending' => 0,
            ];
            foreach ($todayBookings as $booking) {
                $status = $booking['status'] ?? 'pending';
                if (isset($statusBreakdown[$status])) {
                    $statusBreakdown[$status]++;
                } else {
                    // Map other statuses into pending bucket
                    $statusBreakdown['pending']++;
                }
            }

            // ── Doctor utilization ──
            $doctorUtilization = $this->firebase->getDoctorUtilization($clinicId);

            return response()->json([
                'bookings_7days' => $bookings7days,
                'status_breakdown' => $statusBreakdown,
                'doctor_utilization' => $doctorUtilization,
            ]);
        } catch (\Throwable $e) {
            Log::error('Dashboard chartData error: ' . $e->getMessage());

            return response()->json([
                'bookings_7days' => [],
                'status_breakdown' => [
                    'confirmed' => 0,
                    'completed' => 0,
                    'cancelled' => 0,
                    'noShow' => 0,
                    'pending' => 0,
                ],
                'doctor_utilization' => [],
            ]);
        }
    }
}
