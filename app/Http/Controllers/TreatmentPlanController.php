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
        $doctorId = null;

        // If the logged-in user is a doctor, only show their plans
        if ($role === 'doctor') {
            $doctorId = $currentUser['id'] ?? null;
        }

        $plans = $this->firebaseService->getTreatmentPlans($doctorId);
        $doctors = $this->firebaseService->getDoctors();

        return view('treatment-plans.index', [
            'plans' => $plans,
            'doctors' => $doctors,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'doctor_id' => 'required|string',
            'clinic_id' => 'required|string',
            'diagnosis' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Fetch denormalized names
            $patient = $this->firebaseService->getPatientDetails($validated['patient_id']);
            $doctor = $this->firebaseService->getDoctorById($validated['doctor_id']);

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
        $query = $request->query('q', '');
        $patients = $this->firebaseService->searchPatients($query);

        return response()->json([
            'success' => true,
            'data' => array_values($patients),
        ]);
    }
}
