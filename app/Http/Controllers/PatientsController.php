<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PatientsController extends Controller
{
    protected $firebase;

    public function __construct(\App\Services\FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index(Request $request)
    {
        $query = $request->input('search');
        if ($query) {
            $patients = $this->firebase->searchPatients($query);
        } else {
            $patients = $this->firebase->getPatients();
        }
        return view('patients.index', compact('patients', 'query'));
    }

    public function create()
    {
        return view('patients.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
        ]);

        $id = $this->firebase->createPatient($validated);

        if ($id) {
            return redirect()->route('patients.index')->with('success', __('messages.patient_created'));
        } else {
            return back()->with('error', __('messages.patient_creation_failed'));
        }
    }

    public function show($id)
    {
        $patient = $this->firebase->getPatientDetails($id);
        if (!$patient) {
            return redirect()->route('patients.index')->with('error', __('messages.patient_not_found'));
        }
        return view('patients.show', compact('patient'));
    }
}
