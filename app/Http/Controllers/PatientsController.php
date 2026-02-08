<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PatientsController extends Controller
{
    protected $firebase;

    public function __construct(\App\Services\FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index(Request $request)
    {
        try {
            $query = $request->input('search');
            if ($query) {
                // Don't cache search results as they vary per query
                $patients = $this->firebase->searchPatients($query);
            } else {
                $patients = Cache::remember('patients_index', 30, function () {
                    return $this->firebase->getPatients();
                });
            }

            return view('patients.index', compact('patients', 'query'));
        } catch (\Throwable $e) {
            Log::error('Patients index error: ' . $e->getMessage());
            $patients = [];
            $query = $request->input('search');
            return view('patients.index', compact('patients', 'query'))
                ->with('error', __('messages.unknown_error'));
        }
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
            'national_id' => 'nullable|string|max:20',
        ]);

        try {
            $id = $this->firebase->createPatient($validated);

            if ($id) {
                return redirect()->route('patients.index')->with('success', __('messages.patient_created'));
            }

            return back()->with('error', __('messages.patient_creation_failed'));
        } catch (\Throwable $e) {
            Log::error('Create patient error: ' . $e->getMessage());
            return back()->with('error', __('messages.patient_creation_failed'))->withInput();
        }
    }

    public function show($id)
    {
        try {
            $patient = $this->firebase->getPatientDetails($id);
            if (!$patient) {
                return redirect()->route('patients.index')->with('error', __('messages.patient_not_found'));
            }

            return view('patients.show', compact('patient'));
        } catch (\Throwable $e) {
            Log::error('Show patient error: ' . $e->getMessage());
            return redirect()->route('patients.index')->with('error', __('messages.patient_not_found'));
        }
    }

    public function edit($id)
    {
        try {
            $patient = $this->firebase->getPatientDetails($id);
            if (!$patient) {
                return redirect()->route('patients.index')->with('error', __('messages.patient_not_found'));
            }

            return view('patients.edit', compact('patient'));
        } catch (\Throwable $e) {
            Log::error('Edit patient error: ' . $e->getMessage());
            return redirect()->route('patients.index')->with('error', __('messages.patient_not_found'));
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'national_id' => 'nullable|string|max:20',
            'gender' => 'nullable|in:Male,Female',
            'dob' => 'nullable|date',
            'blood_type' => 'nullable|string|max:5',
        ]);

        try {
            $success = $this->firebase->updatePatient($id, $validated);

            if ($success) {
                return redirect()->route('patients.show', $id)->with('success', __('messages.patient_updated'));
            }

            return back()->with('error', __('messages.patient_update_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Update patient error: ' . $e->getMessage());
            return back()->with('error', __('messages.patient_update_failed'))->withInput();
        }
    }
}
