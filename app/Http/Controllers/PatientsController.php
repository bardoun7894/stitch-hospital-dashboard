<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Http\Middleware\RoleMiddleware;

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
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';
            $doctorId = $currentUser['doctor_id'] ?? null;
            $clinicId = $currentUser['clinic_id'] ?? null;
            $hospitalId = $currentUser['hospital_id'] ?? null;

            $query = $request->input('search');

            if ($query) {
                $patients = $this->firebase->searchPatientsScoped($query, $role, $doctorId, $clinicId, $hospitalId);
            } else {
                $cacheKey = "patients_{$role}_{$doctorId}_{$clinicId}_{$hospitalId}";
                $patients = Cache::remember($cacheKey, 30, function () use ($role, $doctorId, $clinicId, $hospitalId) {
                    switch ($role) {
                        case 'doctor':
                            return $doctorId ? $this->firebase->getPatientsForDoctor($doctorId) : [];
                        case 'reception':
                        case 'clinic_admin':
                            return $clinicId ? $this->firebase->getPatientsForClinic($clinicId) : [];
                        case 'hospital_manager':
                            return $hospitalId ? $this->firebase->getPatientsForHospital($hospitalId) : [];
                        default:
                            return $this->firebase->getPatients();
                    }
                });
            }

            $stats = $this->firebase->getPatientStats($patients);

            return view('patients.index', compact('patients', 'query', 'stats'));
        } catch (\Throwable $e) {
            Log::error('Patients index error: ' . $e->getMessage());
            $patients = [];
            $query = $request->input('search');
            $stats = ['total' => 0, 'new_this_month' => 0, 'pending_insurance' => 0];
            return view('patients.index', compact('patients', 'query', 'stats'))
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

            $visitHistory = $this->firebase->getPatientVisitHistory($id);
            $treatmentPlans = $this->firebase->getPatientTreatmentHistory($id);
            $prescriptions = $this->firebase->getPatientPrescriptionHistory($id);

            return view('patients.show', compact('patient', 'visitHistory', 'treatmentPlans', 'prescriptions'));
        } catch (\Throwable $e) {
            Log::error('Show patient error: ' . $e->getMessage());
            return redirect()->route('patients.index')->with('error', __('messages.patient_not_found'));
        }
    }

    public function updateMedical(Request $request, $id)
    {
        $validated = $request->validate([
            'allergies' => 'nullable|string',
            'chronic_conditions' => 'nullable|string',
            'blood_type' => 'nullable|string|max:5',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relation' => 'nullable|string|max:50',
            'medical_notes' => 'nullable|string|max:2000',
        ]);

        // Parse comma-separated strings into arrays
        $data = $validated;
        $data['allergies'] = !empty($validated['allergies'])
            ? array_map('trim', explode(',', $validated['allergies']))
            : [];
        $data['chronic_conditions'] = !empty($validated['chronic_conditions'])
            ? array_map('trim', explode(',', $validated['chronic_conditions']))
            : [];

        try {
            $success = $this->firebase->updatePatientMedicalInfo($id, $data);

            if ($success) {
                return redirect()->route('patients.show', $id)->with('success', __('messages.medical_info_updated'));
            }

            return back()->with('error', __('messages.patient_update_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Update patient medical info error: ' . $e->getMessage());
            return back()->with('error', __('messages.patient_update_failed'))->withInput();
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
