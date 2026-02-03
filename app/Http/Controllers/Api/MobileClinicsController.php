<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MobileClinicsController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Get all clinics
     * GET /api/mobile/clinics
     */
    public function index(Request $request): JsonResponse
    {
        $locale = $request->input('locale', 'ar');

        try {
            $clinics = $this->firebaseService->getClinicsFull();

            // Apply localization
            $localizedClinics = array_map(function ($clinic) use ($locale) {
                return $this->localizeClinic($clinic, $locale);
            }, $clinics);

            return response()->json([
                'success' => true,
                'data' => $localizedClinics
            ]);
        } catch (\Exception $e) {
            \Log::error('Get clinics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_to_get_clinics')
            ], 500);
        }
    }

    /**
     * Get specific clinic details
     * GET /api/mobile/clinics/{clinicId}
     */
    public function show(Request $request, string $clinicId): JsonResponse
    {
        $locale = $request->input('locale', 'ar');

        try {
            $clinic = $this->firebaseService->getClinicById($clinicId);
            $localizedClinic = $this->localizeClinic($clinic, $locale);

            return response()->json([
                'success' => true,
                'data' => $localizedClinic
            ]);
        } catch (\Exception $e) {
            \Log::error('Get clinic error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.clinic_not_found')
            ], 404);
        }
    }

    /**
     * Get doctors for a specific clinic
     * GET /api/mobile/clinics/{clinicId}/doctors
     */
    public function getDoctors(Request $request, string $clinicId): JsonResponse
    {
        $locale = $request->input('locale', 'ar');

        try {
            $doctors = $this->firebaseService->getDoctors($clinicId);

            // Apply localization
            $localizedDoctors = array_map(function ($doctor) use ($locale) {
                return $this->localizeDoctor($doctor, $locale);
            }, $doctors);

            return response()->json([
                'success' => true,
                'data' => $localizedDoctors
            ]);
        } catch (\Exception $e) {
            \Log::error('Get doctors error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_to_get_doctors')
            ], 500);
        }
    }

    /**
     * Get available time slots for a doctor
     * GET /api/mobile/clinics/{clinicId}/doctors/{doctorId}/slots
     */
    public function getAvailableSlots(Request $request, string $clinicId, string $doctorId): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        try {
            $slots = $this->firebaseService->getAvailableSlots(
                $clinicId,
                $doctorId,
                $validated['date']
            );

            return response()->json([
                'success' => true,
                'data' => $slots
            ]);
        } catch (\Exception $e) {
            \Log::error('Get slots error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_to_get_slots')
            ], 500);
        }
    }

    /**
     * Apply localization to clinic data
     */
    private function localizeClinic(array $clinic, string $locale): array
    {
        if ($locale === 'en' && isset($clinic['name_en'])) {
            $clinic['name'] = $clinic['name_en'];
        }
        return $clinic;
    }

    /**
     * Apply localization to doctor data
     */
    private function localizeDoctor(array $doctor, string $locale): array
    {
        if ($locale === 'en' && isset($doctor['name_en'])) {
            $doctor['name'] = $doctor['name_en'];
        }
        return $doctor;
    }
}
