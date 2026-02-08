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
     * Display prescriptions list.
     */
    public function index()
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';
        $doctorId = null;

        // If the logged-in user is a doctor, only show their prescriptions
        if ($role === 'doctor') {
            $doctorId = $currentUser['id'] ?? null;
        }

        $prescriptions = $this->firebaseService->getPrescriptions($doctorId);
        $doctors = $this->firebaseService->getDoctors();

        return view('medications.index', [
            'prescriptions' => $prescriptions,
            'doctors' => $doctors,
        ]);
    }

    /**
     * Store a new prescription with medications.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'doctor_id' => 'required|string',
            'clinic_id' => 'required|string',
            'medications' => 'required|array|min:1',
            'medications.*.name' => 'required|string|max:255',
            'medications.*.duration_days' => 'required|integer|min:1|max:365',
            'medications.*.interval_hours' => 'required|numeric|min:0.5|max:24',
            'medications.*.dose_amount' => 'required|string|max:100',
            'medications.*.dose_unit' => 'required|string|in:ml,mg,pill,tablet,capsule,drop,spoon',
            'medications.*.first_dose_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Fetch denormalized names
            $patient = $this->firebaseService->getPatientDetails($validated['patient_id']);
            $doctor = $this->firebaseService->getDoctorById($validated['doctor_id']);

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
     */
    public function destroy(string $id): JsonResponse
    {
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
     * Search patients (reuses existing search).
     */
    public function searchPatients(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $patients = $this->firebaseService->searchPatients($query);

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
