<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

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
            $clinics = Cache::remember("mobile_clinics_{$locale}", 300, function () use ($locale) {
                $clinics = $this->firebaseService->getClinicsFull();
                return array_map(function ($clinic) use ($locale) {
                    return $this->localizeClinic($clinic, $locale);
                }, $clinics);
            });

            return response()->json([
                'success' => true,
                'data' => $clinics
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
            $clinic = Cache::remember("mobile_clinic_{$clinicId}_{$locale}", 300, function () use ($clinicId, $locale) {
                $clinic = $this->firebaseService->getClinicById($clinicId);
                return $this->localizeClinic($clinic, $locale);
            });

            return response()->json([
                'success' => true,
                'data' => $clinic
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
            $doctors = Cache::remember("mobile_clinic_doctors_{$clinicId}_{$locale}", 300, function () use ($clinicId, $locale) {
                $doctors = $this->firebaseService->getDoctors($clinicId);
                return array_map(function ($doctor) use ($locale) {
                    return $this->localizeDoctor($doctor, $locale);
                }, $doctors);
            });

            return response()->json([
                'success' => true,
                'data' => $doctors
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
            $cacheKey = "mobile_slots_{$clinicId}_{$doctorId}_{$validated['date']}";
            $data = Cache::remember($cacheKey, 60, function () use ($clinicId, $doctorId, $validated) {
                $slots = $this->firebaseService->getAvailableSlots(
                    $clinicId,
                    $doctorId,
                    $validated['date']
                );
                $hoursCheck = $this->firebaseService->isWithinWorkingHours($clinicId, $doctorId);
                return [
                    'slots' => $slots,
                    'working_hours' => [
                        'within_hours' => $hoursCheck['within_hours'],
                        'sessions' => $hoursCheck['sessions'],
                        'message' => $hoursCheck['message'],
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
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

    /**
     * Get clinics and doctors by specialty
     * GET /api/mobile/specialties/{specialty}
     */
    public function getBySpecialty(Request $request, string $specialty): JsonResponse
    {
        $locale = $request->input('locale', 'ar');

        try {
            $data = Cache::remember("mobile_specialty_{$specialty}_{$locale}", 300, function () use ($specialty, $locale) {
                $clinics = $this->firebaseService->getClinicsBySpecialty($specialty);
                $doctors = $this->firebaseService->getDoctorsBySpecialty($specialty);
                return [
                    'clinics' => array_map(fn($c) => $this->localizeClinic($c, $locale), $clinics),
                    'doctors' => array_map(fn($d) => $this->localizeDoctor($d, $locale), $doctors),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get by specialty error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get data for specialty'
            ], 500);
        }
    }

    /**
     * Search across clinics, doctors, and hospitals
     * GET /api/mobile/search?q=query
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $locale = $request->input('locale', 'ar');

        try {
            $cacheKey = 'mobile_search_' . md5("{$query}_{$locale}");
            $results = Cache::remember($cacheKey, 120, function () use ($query, $locale) {
                $results = $this->firebaseService->searchAll($query);
                $results['clinics'] = array_map(fn($c) => $this->localizeClinic($c, $locale), $results['clinics']);
                $results['doctors'] = array_map(fn($d) => $this->localizeDoctor($d, $locale), $results['doctors']);
                $results['hospitals'] = array_map(function ($hospital) use ($locale) {
                    if ($locale === 'en' && isset($hospital['name_en'])) {
                        $hospital['name'] = $hospital['name_en'];
                    }
                    return $hospital;
                }, $results['hospitals']);
                return $results;
            });

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            \Log::error('Search error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Search failed'
            ], 500);
        }
    }
}
