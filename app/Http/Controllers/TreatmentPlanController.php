<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;

class TreatmentPlanController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function index()
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';
        $doctorId = $currentUser['doctor_id'] ?? null;
        $clinicId = $currentUser['clinic_id'] ?? null;
        $hospitalId = $currentUser['hospital_id'] ?? null;

        switch ($role) {
            case 'doctor':
                $plans = $doctorId
                    ? $this->firebaseService->getTreatmentPlans($doctorId)
                    : [];
                break;
            case 'reception':
            case 'clinic_admin':
                $plans = $clinicId
                    ? $this->firebaseService->getTreatmentPlansForClinic($clinicId)
                    : [];
                break;
            case 'hospital_manager':
                $plans = $hospitalId
                    ? $this->firebaseService->getTreatmentPlansForHospital($hospitalId)
                    : [];
                break;
            default:
                $plans = $this->firebaseService->getTreatmentPlans();
                break;
        }

        // Get today's patients for the doctor's quick-select dropdown
        $todaysPatients = [];
        if ($role === 'doctor' && $doctorId) {
            $todaysPatients = $this->firebaseService->getTodaysPatientsForDoctor($doctorId);
        }

        $data = [
            'plans' => $plans,
            'doctors' => $this->firebaseService->getDoctors(),
            'currentUser' => $currentUser,
            'todaysPatients' => $todaysPatients,
        ];

        return view('treatment-plans.index', $data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'doctor_id' => 'required|string',
            'clinic_id' => 'nullable|string',
            'diagnosis' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Fetch denormalized names
            $patient = $this->firebaseService->getPatientDetails($validated['patient_id']);
            $doctor = $this->firebaseService->getDoctorById($validated['doctor_id']);

            // Ensure clinic_id is set from doctor data if not provided
            if (empty($validated['clinic_id'])) {
                $validated['clinic_id'] = $doctor['clinic_id'] ?? '';
            }

            $validated['patient_name'] = $patient['name'] ?? '';
            $validated['patient_phone'] = $patient['phone'] ?? '';
            $validated['doctor_name'] = $doctor['name'] ?? '';

            $planId = $this->firebaseService->createTreatmentPlan($validated);

            return response()->json([
                'success' => true,
                'message' => __('messages.treatment_plan_created'),
                'data' => ['id' => $planId],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function complete(string $id): JsonResponse
    {
        $success = $this->firebaseService->completeTreatmentPlan($id);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => __('messages.treatment_plan_completed'),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => __('messages.error'),
        ], 500);
    }

    public function destroy(string $id): JsonResponse
    {
        $success = $this->firebaseService->deleteTreatmentPlan($id);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => __('messages.treatment_plan_deleted'),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => __('messages.error'),
        ], 500);
    }

    public function searchPatients(Request $request): JsonResponse
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';
        $doctorId = $currentUser['doctor_id'] ?? null;
        $clinicId = $currentUser['clinic_id'] ?? null;
        $hospitalId = $currentUser['hospital_id'] ?? null;
        $query = $request->query('q', '');

        $patients = $this->firebaseService->searchPatientsScoped($query, $role, $doctorId, $clinicId, $hospitalId);

        return response()->json([
            'success' => true,
            'data' => array_values($patients),
        ]);
    }
}
