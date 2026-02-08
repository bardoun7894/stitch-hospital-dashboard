<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
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
            $data = Cache::remember('reports_daily_stats', 30, function () {
                return $this->firebase->getQueueData();
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
            // Cache the entire doctor load report to avoid N+1 queries.
            // Previously, each doctor triggered a separate getQueueData() call.
            // Now we batch-fetch queue data per unique clinic and reuse it.
            $report = Cache::remember('reports_doctor_load', 30, function () {
                $doctors = $this->firebase->getDoctors();
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
}
