<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Cache;

class TvViewController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function index()
    {
        $data = Cache::remember('tv_queue_data', 30, function () {
            return $this->firebaseService->getQueueData();
        });
        return view('tv.index', ['data' => $data]);
    }
}
