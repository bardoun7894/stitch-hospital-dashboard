<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
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
            // Fetch queue data from Firebase for today
            $data = $this->firebase->getQueueData();
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
            // For multi-doctor load, we typically aggregate bookings
            // For MVP, we'll fetch doctors and their booking counts for today
            $doctors = $this->firebase->getDoctors();
            $report = [];

            foreach ($doctors as $doctor) {
                $clinicId = $doctor['clinic_id'] ?? null;
                $stats = $clinicId ? $this->firebase->getQueueData($clinicId) : ['stats' => []];
                $report[] = [
                    'name' => $doctor['name'] ?? '-',
                    'booked' => $stats['stats']['bookings_today'] ?? 0,
                    'arrived' => $stats['stats']['arrived'] ?? 0,
                ];
            }

            return view('reports.doctor_load', compact('report'));
        } catch (\Throwable $e) {
            Log::error('Doctor load report error: ' . $e->getMessage());
            $report = [];
            return view('reports.doctor_load', compact('report'))
                ->with('error', __('messages.unknown_error'));
        }
    }
}
