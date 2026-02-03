<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use Illuminate\Http\Request;

class HospitalController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Display the hospital manager dashboard.
     */
    public function index()
    {
        $data = $this->firebaseService->getHospitalDashboardData();
        return view('hospital.index', ['data' => $data]);
    }
}
