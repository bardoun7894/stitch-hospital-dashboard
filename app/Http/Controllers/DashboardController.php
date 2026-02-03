<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        $bookings = $this->firebase->getBookings();

        // Get real-time stats from Firebase (falls back to mock if no data)
        $stats = $this->firebase->getDashboardStats();

        // Get clinics for the table
        $clinics = $this->firebase->getClinics();

        // Get alerts
        $alerts = $this->firebase->getAlerts();

        return view('dashboard.index', compact('stats', 'bookings', 'clinics', 'alerts'));
    }
}
