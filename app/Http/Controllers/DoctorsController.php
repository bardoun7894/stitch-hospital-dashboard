<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DoctorsController extends Controller
{
    protected $firebase;

    public function __construct(\App\Services\FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        $doctors = $this->firebase->getDoctors();
        return view('doctors.index', compact('doctors'));
    }
}
