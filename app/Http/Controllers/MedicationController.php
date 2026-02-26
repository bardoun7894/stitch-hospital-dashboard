<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;

class MedicationController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Display prescriptions list scoped by role.
     */
    public function index()
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';
        $doctorId = $currentUser['doctor_id'] ?? null;
        $clinicId = $currentUser['clinic_id'] ?? null;
        $hospitalId = $currentUser['hospital_id'] ?? null;

        switch ($role) {
            case 'doctor':
                $prescriptions = $doctorId
                    ? $this->firebaseService->getPrescriptions($doctorId)
                    : [];
                break;
            case 'reception':
            case 'clinic_admin':
                $prescriptions = $clinicId
                    ? $this->firebaseService->getPrescriptionsForClinic($clinicId)
                    : [];
                break;
            case 'hospital_manager':
                $prescriptions = $hospitalId
                    ? $this->firebaseService->getPrescriptionsForHospital($hospitalId)
                    : [];
                break;
            case 'super_admin':
                $prescriptions = $this->firebaseService->getPrescriptions();
                break;
            default:
                $prescriptions = [];
                break;
        }

        // Get today's patients for the doctor's quick-select dropdown
        $todaysPatients = [];
        if ($role === 'doctor' && $doctorId) {
            $todaysPatients = $this->firebaseService->getTodaysPatientsForDoctor($doctorId);
        }

        $data = [
            'prescriptions' => $prescriptions,
            'doctors' => $this->firebaseService->getDoctors(),
            'currentUser' => $currentUser,
            'todaysPatients' => $todaysPatients,
        ];

        return view('medications.index', $data);
    }

    /**
     * Store a new prescription with medications.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'doctor_id' => 'required|string',
            'clinic_id' => 'nullable|string',
            'medications' => 'required|array|min:1',
            'medications.*.name' => 'required|string|max:255',
            'medications.*.duration_days' => 'required|integer|min:1|max:365',
            'medications.*.interval_hours' => 'required|numeric|min:0.5|max:24',
            'medications.*.dose_amount' => 'required|string|max:100',
            'medications.*.dose_unit' => 'required|string|in:ml,mg,pill,tablet,capsule,drop,spoon,suppository',
            'medications.*.first_dose_time' => 'required|date_format:H:i',
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

            // Calculate dose schedules for each medication
            foreach ($validated['medications'] as $index => $med) {
                $schedules = $this->calculateDoseSchedule(
                    $med['first_dose_time'],
                    (float) $med['interval_hours'],
                    (int) $med['duration_days']
                );
                $validated['medications'][$index]['dose_schedule'] = $schedules;
            }

            $prescriptionId = $this->firebaseService->createPrescription($validated);

            return response()->json([
                'success' => true,
                'message' => __('messages.prescription_created'),
                'data' => ['id' => $prescriptionId],
            ], 201);
        } catch (\Exception $e) {
            \Log::error('MedicationController@store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific prescription.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $prescription = $this->firebaseService->getPrescriptionById($id);

            if (!$prescription) {
                return response()->json([
                    'success' => false,
                    'error' => __('messages.prescription_not_found'),
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $prescription,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deactivate/delete a prescription.
     * Only the doctor who created the prescription can delete it.
     */
    public function destroy(string $id): JsonResponse
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        $currentDoctorId = $currentUser['doctor_id'] ?? null;

        // Verify ownership: only the prescribing doctor can delete
        if ($currentDoctorId) {
            $prescription = $this->firebaseService->getPrescriptionById($id);
            if ($prescription && ($prescription['doctor_id'] ?? '') !== $currentDoctorId) {
                return response()->json([
                    'success' => false,
                    'error' => __('messages.unauthorized_action'),
                ], 403);
            }
        }

        $success = $this->firebaseService->deactivatePrescription($id);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => __('messages.prescription_deleted'),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => __('messages.error'),
        ], 500);
    }

    /**
     * Search patients scoped by role.
     */
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

    /**
     * Calculate all dose times for a medication.
     *
     * @param string $firstDoseTime  e.g. "08:00"
     * @param float  $intervalHours  e.g. 4
     * @param int    $durationDays   e.g. 3
     * @return array List of dose time strings (H:i format)
     */
    private function calculateDoseSchedule(string $firstDoseTime, float $intervalHours, int $durationDays): array
    {
        $times = [];
        $intervalMinutes = (int) ($intervalHours * 60);
        $totalMinutes = $durationDays * 24 * 60;

        $start = \DateTime::createFromFormat('H:i', $firstDoseTime);
        if (!$start) {
            return [];
        }

        // Calculate number of doses per day based on interval
        $dosesPerDay = (int) floor(24 * 60 / $intervalMinutes);
        $dailyTimes = [];

        $current = clone $start;
        for ($i = 0; $i < $dosesPerDay; $i++) {
            $dailyTimes[] = $current->format('H:i');
            $current->modify("+{$intervalMinutes} minutes");
        }

        return $dailyTimes;
    }
}
