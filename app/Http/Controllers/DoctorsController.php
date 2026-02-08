<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Log;

class DoctorsController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $clinicId = $currentUser['clinic_id'] ?? null;
            $role = $currentUser['role'] ?? 'patient';

            if ($role === 'super_admin') {
                $doctors = $this->firebase->getDoctors();
            } elseif ($clinicId) {
                $doctors = $this->firebase->getDoctors($clinicId);
            } else {
                $doctors = $this->firebase->getDoctors();
            }

            return view('doctors.index', compact('doctors'));
        } catch (\Throwable $e) {
            Log::error('Doctors index error: ' . $e->getMessage());
            $doctors = [];
            return view('doctors.index', compact('doctors'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function create()
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';

            if ($role === 'super_admin') {
                $clinics = $this->firebase->getClinics();
            } elseif ($currentUser['hospital_id'] ?? null) {
                $clinics = $this->firebase->getClinicsForHospital($currentUser['hospital_id']);
            } elseif ($currentUser['clinic_id'] ?? null) {
                $clinic = $this->firebase->getClinic($currentUser['clinic_id']);
                $clinics = $clinic ? [$clinic] : [];
            } else {
                $clinics = $this->firebase->getClinics();
            }

            return view('doctors.create', compact('clinics'));
        } catch (\Throwable $e) {
            Log::error('Doctors create form error: ' . $e->getMessage());
            $clinics = [];
            return view('doctors.create', compact('clinics'))
                ->with('error', __('messages.unknown_error'));
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'specialty' => 'required|string|max:255',
            'clinic_id' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'consultation_fee' => 'nullable|numeric|min:0',
            'status' => 'required|in:available,busy,off',
        ]);

        try {
            $id = $this->firebase->createDoctor($validated);

            if ($id) {
                return redirect()->route('doctors.index')->with('success', __('messages.doctor_created'));
            }

            return back()->with('error', __('messages.doctor_creation_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Create doctor error: ' . $e->getMessage());
            return back()->with('error', __('messages.doctor_creation_failed'))->withInput();
        }
    }

    public function edit($id)
    {
        try {
            $doctor = $this->firebase->getDoctorById($id);
            if (!$doctor) {
                return redirect()->route('doctors.index')->with('error', __('messages.doctor_not_found'));
            }

            $currentUser = RoleMiddleware::getCurrentUser();
            $role = $currentUser['role'] ?? 'patient';

            if ($role === 'super_admin') {
                $clinics = $this->firebase->getClinics();
            } elseif ($currentUser['hospital_id'] ?? null) {
                $clinics = $this->firebase->getClinicsForHospital($currentUser['hospital_id']);
            } elseif ($currentUser['clinic_id'] ?? null) {
                $clinic = $this->firebase->getClinic($currentUser['clinic_id']);
                $clinics = $clinic ? [$clinic] : [];
            } else {
                $clinics = $this->firebase->getClinics();
            }

            return view('doctors.edit', compact('doctor', 'clinics'));
        } catch (\Throwable $e) {
            Log::error('Doctors edit form error: ' . $e->getMessage());
            return redirect()->route('doctors.index')->with('error', __('messages.doctor_not_found'));
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'specialty' => 'required|string|max:255',
            'clinic_id' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'consultation_fee' => 'nullable|numeric|min:0',
            'status' => 'required|in:available,busy,off',
        ]);

        try {
            $success = $this->firebase->updateDoctor($id, $validated);

            if ($success) {
                return redirect()->route('doctors.index')->with('success', __('messages.doctor_updated'));
            }

            return back()->with('error', __('messages.doctor_update_failed'))->withInput();
        } catch (\Throwable $e) {
            Log::error('Update doctor error: ' . $e->getMessage());
            return back()->with('error', __('messages.doctor_update_failed'))->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $success = $this->firebase->deleteDoctor($id);

            if ($success) {
                return response()->json(['success' => true, 'message' => __('messages.doctor_deleted')]);
            }

            return response()->json(['success' => false, 'error' => __('messages.doctor_delete_failed')], 500);
        } catch (\Throwable $e) {
            Log::error('Delete doctor error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => __('messages.doctor_delete_failed')], 500);
        }
    }
}
