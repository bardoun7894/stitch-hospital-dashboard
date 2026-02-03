<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ClinicsController extends Controller
{
    protected $firebase;

    public function __construct(\App\Services\FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        $clinics = $this->firebase->getClinics();
        return view('clinics.index', compact('clinics'));
    }
}
