<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MobileHospitalsController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Get all hospitals
     * GET /api/mobile/hospitals
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $hospitals = $this->firebaseService->getHospitals();

            return response()->json([
                'success' => true,
                'data' => $hospitals
            ]);
        } catch (\Exception $e) {
            \Log::error('Get hospitals error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get hospitals'
            ], 500);
        }
    }

    /**
     * Get specific hospital details
     * GET /api/mobile/hospitals/{hospitalId}
     */
    public function show(string $hospitalId): JsonResponse
    {
        try {
            $hospital = $this->firebaseService->getHospitalById($hospitalId);

            if (!$hospital) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hospital not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $hospital
            ]);
        } catch (\Exception $e) {
            \Log::error('Get hospital error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get hospital'
            ], 500);
        }
    }

    /**
     * Get clinics for a specific hospital
     * GET /api/mobile/hospitals/{hospitalId}/clinics
     */
    public function getClinics(string $hospitalId): JsonResponse
    {
        try {
            $hospital = $this->firebaseService->getHospitalById($hospitalId);

            if (!$hospital) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hospital not found'
                ], 404);
            }

            $clinics = $this->firebaseService->getClinicsForHospital($hospitalId);

            return response()->json([
                'success' => true,
                'data' => $clinics
            ]);
        } catch (\Exception $e) {
            \Log::error('Get hospital clinics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get clinics'
            ], 500);
        }
    }
}
