<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;

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
        // Fetch queue data from Firebase for today
        $data = $this->firebase->getQueueData();
        return view('reports.daily', compact('data'));
    }

    public function doctorLoad()
    {
        // For multi-doctor load, we typically aggregate bookings
        // For MVP, we'll fetch doctors and their booking counts for today
        $doctors = $this->firebase->getDoctors();
        $report = [];

        foreach ($doctors as $doctor) {
            $stats = $this->firebase->getQueueData($doctor['id']);
            $report[] = [
                'name' => $doctor['name'],
                'booked' => $stats['stats']['bookings_today'] ?? 0,
                'arrived' => $stats['stats']['arrived'] ?? 0,
            ];
        }

        return view('reports.doctor_load', compact('report'));
    }
}
