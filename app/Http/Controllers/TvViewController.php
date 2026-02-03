<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;

class TvViewController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function index()
    {
        // Re-use the same queue data for consistency
        $data = $this->firebaseService->getQueueData();
        return view('tv.index', ['data' => $data]);
    }
}
