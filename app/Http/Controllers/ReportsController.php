<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReportsController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        // Simple dashboard for reports
        return view('reports.index');
    }

    public function dailyStats()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $hospitalId = $currentUser['hospital_id'] ?? null;
            $clinicId = $currentUser['clinic_id'] ?? null;

            // Determine which clinic to scope to
            $scopedClinicId = $clinicId; // clinic_admin or receptionist: their own clinic
            if ($role === 'super_admin') {
                $scopedClinicId = null; // super_admin sees all
            } elseif ($role === 'hospital_manager' && $hospitalId && !$clinicId) {
                // Hospital manager without a specific clinic: default to first hospital clinic
                $clinics = $this->firebase->getClinicsForHospital($hospitalId);
                $scopedClinicId = !empty($clinics) ? ($clinics[0]['id'] ?? null) : null;
            }

            $cacheKey = 'reports_daily_stats_' . md5("{$role}_{$hospitalId}_{$scopedClinicId}");
            $data = Cache::remember($cacheKey, 30, function () use ($scopedClinicId) {
                return $this->firebase->getQueueData($scopedClinicId);
            });
            return view('reports.daily', compact('data'));
        } catch (\Throwable $e) {
            Log::error('Daily report load error: ' . $e->getMessage());
            $data = ['stats' => [], 'bookings' => [], 'status_counts' => []];
            return view('reports.daily', compact('data'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function doctorLoad()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $hospitalId = $currentUser['hospital_id'] ?? null;
            $userClinicId = $currentUser['clinic_id'] ?? null;

            // Cache the entire doctor load report to avoid N+1 queries.
            // Previously, each doctor triggered a separate getQueueData() call.
            // Now we batch-fetch queue data per unique clinic and reuse it.
            $cacheKey = 'reports_doctor_load_' . md5("{$role}_{$hospitalId}_{$userClinicId}");
            $report = Cache::remember($cacheKey, 30, function () use ($role, $hospitalId, $userClinicId) {
                // Scope doctors based on role
                if ($role === 'super_admin') {
                    $doctors = $this->firebase->getDoctors();
                } elseif ($role === 'hospital_manager' && $hospitalId) {
                    $doctors = $this->firebase->getDoctorsForHospital($hospitalId);
                } elseif ($userClinicId) {
                    $doctors = $this->firebase->getDoctors($userClinicId);
                } else {
                    $doctors = $this->firebase->getDoctors();
                }

                $report = [];

                // Batch: collect unique clinic IDs and fetch queue data once per clinic
                $clinicQueueData = [];
                foreach ($doctors as $doctor) {
                    $clinicId = $doctor['clinic_id'] ?? null;
                    if ($clinicId && !isset($clinicQueueData[$clinicId])) {
                        $clinicQueueData[$clinicId] = $this->firebase->getQueueData($clinicId);
                    }
                }

                foreach ($doctors as $doctor) {
                    $clinicId = $doctor['clinic_id'] ?? null;
                    $stats = ($clinicId && isset($clinicQueueData[$clinicId]))
                        ? $clinicQueueData[$clinicId]
                        : ['stats' => []];
                    $report[] = [
                        'name' => $doctor['name'] ?? '-',
                        'booked' => $stats['stats']['bookings_today'] ?? 0,
                        'arrived' => $stats['stats']['arrived'] ?? 0,
                    ];
                }

                return $report;
            });

            return view('reports.doctor_load', compact('report'));
        } catch (\Throwable $e) {
            Log::error('Doctor load report error: ' . $e->getMessage());
            $report = [];
            return view('reports.doctor_load', compact('report'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Export daily stats as CSV download.
     */
    public function exportDailyCSV(Request $request)
    {
        try {
            $date = $request->input('date', date('Y-m-d'));
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $hospitalId = $currentUser['hospital_id'] ?? null;
            $clinicId = $currentUser['clinic_id'] ?? null;

            // Determine scoped clinic
            $scopedClinicId = $clinicId;
            if ($role === 'super_admin') {
                $scopedClinicId = null;
            } elseif ($role === 'hospital_manager' && $hospitalId && !$clinicId) {
                $clinics = $this->firebase->getClinicsForHospital($hospitalId);
                $scopedClinicId = !empty($clinics) ? ($clinics[0]['id'] ?? null) : null;
            }

            $data = $this->firebase->getQueueData($scopedClinicId);
            $bookings = $data['bookings'] ?? [];

            if (empty($bookings)) {
                return back()->with('error', __('messages.no_data_to_export'));
            }

            $headers = [
                __('messages.token_number'),
                __('messages.patient_name'),
                __('messages.doctor_name'),
                __('messages.status'),
                __('messages.avg_wait_time'),
                __('messages.booking_time'),
                __('messages.payment_on_arrival'),
            ];

            $rows = [];
            foreach ($bookings as $booking) {
                $rows[] = [
                    $booking['token'] ?? '-',
                    $booking['patient'] ?? '-',
                    $booking['type'] ?? '-',
                    $booking['status'] ?? '-',
                    $booking['wait'] ?? '-',
                    $booking['time'] ?? '-',
                    $booking['payment_note'] ?? '-',
                ];
            }

            $filename = "daily-report-{$date}.csv";
            return $this->generateCSV($headers, $rows, $filename);
        } catch (\Throwable $e) {
            Log::error('Export daily CSV error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Export doctor load report as CSV download.
     */
    public function exportDoctorLoadCSV(Request $request)
    {
        try {
            $date = $request->input('date', date('Y-m-d'));
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $hospitalId = $currentUser['hospital_id'] ?? null;
            $userClinicId = $currentUser['clinic_id'] ?? null;

            // Scope doctors based on role
            if ($role === 'super_admin') {
                $doctors = $this->firebase->getDoctors();
            } elseif ($role === 'hospital_manager' && $hospitalId) {
                $doctors = $this->firebase->getDoctorsForHospital($hospitalId);
            } elseif ($userClinicId) {
                $doctors = $this->firebase->getDoctors($userClinicId);
            } else {
                $doctors = $this->firebase->getDoctors();
            }

            // Determine scoped clinic for queue data
            $scopedClinicId = $userClinicId;
            if ($role === 'super_admin') {
                $scopedClinicId = null;
            } elseif ($role === 'hospital_manager' && $hospitalId && !$userClinicId) {
                $clinics = $this->firebase->getClinicsForHospital($hospitalId);
                $scopedClinicId = !empty($clinics) ? ($clinics[0]['id'] ?? null) : null;
            }

            $data = $this->firebase->getQueueData($scopedClinicId);
            $bookings = $data['bookings'] ?? [];
            $statusCounts = $data['status_counts'] ?? [];

            if (empty($doctors)) {
                return back()->with('error', __('messages.no_data_to_export'));
            }

            // Build per-doctor stats from bookings
            $doctorStats = [];
            foreach ($bookings as $booking) {
                $doctorName = $booking['type'] ?? '-';
                if (!isset($doctorStats[$doctorName])) {
                    $doctorStats[$doctorName] = [
                        'total' => 0,
                        'arrived' => 0,
                        'completed' => 0,
                        'no_show' => 0,
                        'wait_total' => 0,
                        'wait_count' => 0,
                    ];
                }
                $doctorStats[$doctorName]['total']++;
                $status = $booking['raw_status'] ?? $booking['status'] ?? '';
                if (in_array($status, ['arrived', 'Arrived'])) {
                    $doctorStats[$doctorName]['arrived']++;
                }
                if (in_array($status, ['completed', 'Completed'])) {
                    $doctorStats[$doctorName]['completed']++;
                }
                if (in_array($status, ['noShow', 'no_show', 'No Show'])) {
                    $doctorStats[$doctorName]['no_show']++;
                }
                if (isset($booking['wait']) && $booking['wait'] !== '-') {
                    $waitMinutes = (int) filter_var($booking['wait'], FILTER_SANITIZE_NUMBER_INT);
                    if ($waitMinutes > 0) {
                        $doctorStats[$doctorName]['wait_total'] += $waitMinutes;
                        $doctorStats[$doctorName]['wait_count']++;
                    }
                }
            }

            $headers = [
                __('messages.doctor_name'),
                __('messages.specialty'),
                __('messages.total_bookings'),
                __('messages.arrived'),
                __('messages.completed'),
                __('messages.no_show'),
                __('messages.avg_wait_time'),
            ];

            $rows = [];
            foreach ($doctors as $doctor) {
                $name = $doctor['name'] ?? '-';
                $stats = $doctorStats[$name] ?? [
                    'total' => 0, 'arrived' => 0, 'completed' => 0,
                    'no_show' => 0, 'wait_total' => 0, 'wait_count' => 0,
                ];
                $avgWait = $stats['wait_count'] > 0
                    ? round($stats['wait_total'] / $stats['wait_count']) . ' min'
                    : '-';
                $rows[] = [
                    $name,
                    $doctor['specialty'] ?? '-',
                    $stats['total'],
                    $stats['arrived'],
                    $stats['completed'],
                    $stats['no_show'],
                    $avgWait,
                ];
            }

            $filename = "doctor-load-{$date}.csv";
            return $this->generateCSV($headers, $rows, $filename);
        } catch (\Throwable $e) {
            Log::error('Export doctor load CSV error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Export bookings as CSV download with date range filtering.
     */
    public function exportBookingsCSV(Request $request)
    {
        try {
            $dateFrom = $request->input('date_from', date('Y-m-d'));
            $dateTo = $request->input('date_to', date('Y-m-d'));
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $hospitalId = $currentUser['hospital_id'] ?? null;
            $clinicId = $currentUser['clinic_id'] ?? null;

            // Determine scoped clinic
            $scopedClinicId = $clinicId;
            if ($role === 'super_admin') {
                $scopedClinicId = null;
            } elseif ($role === 'hospital_manager' && $hospitalId && !$clinicId) {
                $clinics = $this->firebase->getClinicsForHospital($hospitalId);
                $scopedClinicId = !empty($clinics) ? ($clinics[0]['id'] ?? null) : null;
            }

            $data = $this->firebase->getQueueData($scopedClinicId);
            $bookings = $data['bookings'] ?? [];

            if (empty($bookings)) {
                return back()->with('error', __('messages.no_data_to_export'));
            }

            $headers = [
                __('messages.booking_date'),
                __('messages.token_number'),
                __('messages.patient_name'),
                __('messages.doctor_name'),
                __('messages.clinic_name'),
                __('messages.status'),
                __('messages.payment_on_arrival'),
                __('messages.avg_wait_time'),
            ];

            $rows = [];
            foreach ($bookings as $booking) {
                $rows[] = [
                    $dateFrom === $dateTo ? $dateFrom : "{$dateFrom} - {$dateTo}",
                    $booking['token'] ?? '-',
                    $booking['patient'] ?? '-',
                    $booking['type'] ?? '-',
                    $booking['clinic_id'] ?? '-',
                    $booking['status'] ?? '-',
                    $booking['payment_note'] ?? '-',
                    $booking['wait'] ?? '-',
                ];
            }

            $filename = "bookings-{$dateFrom}-to-{$dateTo}.csv";
            return $this->generateCSV($headers, $rows, $filename);
        } catch (\Throwable $e) {
            Log::error('Export bookings CSV error: ' . $e->getMessage());
            return back()->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Generate a CSV file and return it as a streamed download response.
     *
     * @param array  $headers  Column headers
     * @param array  $rows     Data rows (array of arrays)
     * @param string $filename Download filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function generateCSV(array $headers, array $rows, string $filename)
    {
        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, $headers);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
