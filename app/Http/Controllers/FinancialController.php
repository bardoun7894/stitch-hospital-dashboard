<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Log;

class FinancialController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Display the financial dashboard.
     */
    public function index(Request $request)
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $hospitalId = $currentUser['hospital_id'] ?? null;
            $userClinicId = $currentUser['clinic_id'] ?? null;
            $clinicId = $request->input('clinic_id');

            // Default date range: current month
            $dateFrom = $request->input('date_from', date('Y-m-01'));
            $dateTo = $request->input('date_to', date('Y-m-d'));

            $filters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];

            if ($clinicId) {
                $filters['clinic_id'] = $clinicId;
            } elseif ($role === 'hospital_manager' && $hospitalId && !$userClinicId) {
                // Hospital manager without specific clinic: scope to first hospital clinic
                $hospitalClinics = $this->firebase->getClinicsForHospital($hospitalId);
                if (!empty($hospitalClinics)) {
                    $filters['clinic_id'] = $hospitalClinics[0]['id'] ?? null;
                }
            } elseif ($userClinicId) {
                $filters['clinic_id'] = $userClinicId;
            }

            $stats = $this->firebase->getFinancialStats($filters);

            // Scope the clinic dropdown list based on role
            if ($role === 'super_admin') {
                $clinics = $this->firebase->getClinics();
            } elseif ($role === 'hospital_manager' && $hospitalId) {
                $clinics = $this->firebase->getClinicsForHospital($hospitalId);
            } elseif ($userClinicId) {
                $clinic = $this->firebase->getClinic($userClinicId);
                $clinics = $clinic ? [$clinic] : [];
            } else {
                $clinics = $this->firebase->getClinics();
            }

            return view('financial.index', compact(
                'stats',
                'clinics',
                'dateFrom',
                'dateTo',
                'clinicId'
            ));
        } catch (\Throwable $e) {
            Log::error('Financial dashboard error: ' . $e->getMessage());

            $stats = [
                'total_revenue' => 0,
                'cash_revenue' => 0,
                'online_revenue' => 0,
                'waived_count' => 0,
                'total_transactions' => 0,
                'avg_transaction_value' => 0,
                'daily_breakdown' => [],
                'recent_transactions' => [],
            ];
            $clinics = [];
            $dateFrom = date('Y-m-01');
            $dateTo = date('Y-m-d');
            $clinicId = null;

            return view('financial.index', compact(
                'stats',
                'clinics',
                'dateFrom',
                'dateTo',
                'clinicId'
            ))->with('error', __('messages.unknown_error'));
        }
    }

    /**
     * Return chart data as JSON for AJAX loading.
     */
    public function chartData(Request $request)
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $hospitalId = $currentUser['hospital_id'] ?? null;
            $userClinicId = $currentUser['clinic_id'] ?? null;

            $dateFrom = $request->input('date_from', date('Y-m-01'));
            $dateTo = $request->input('date_to', date('Y-m-d'));
            $clinicId = $request->input('clinic_id');

            $filters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];

            if ($clinicId) {
                $filters['clinic_id'] = $clinicId;
            } elseif ($role === 'hospital_manager' && $hospitalId && !$userClinicId) {
                $hospitalClinics = $this->firebase->getClinicsForHospital($hospitalId);
                if (!empty($hospitalClinics)) {
                    $filters['clinic_id'] = $hospitalClinics[0]['id'] ?? null;
                }
            } elseif ($userClinicId) {
                $filters['clinic_id'] = $userClinicId;
            }

            $stats = $this->firebase->getFinancialStats($filters);

            return response()->json([
                'daily_breakdown' => $stats['daily_breakdown'],
                'total_revenue' => $stats['total_revenue'],
                'cash_revenue' => $stats['cash_revenue'],
                'online_revenue' => $stats['online_revenue'],
                'waived_count' => $stats['waived_count'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Financial chart data error: ' . $e->getMessage());

            return response()->json([
                'daily_breakdown' => [],
                'total_revenue' => 0,
                'cash_revenue' => 0,
                'online_revenue' => 0,
                'waived_count' => 0,
            ], 500);
        }
    }
}
