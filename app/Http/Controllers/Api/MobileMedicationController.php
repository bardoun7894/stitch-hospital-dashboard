<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MobileMedicationController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Get active prescriptions for the current user.
     * GET /api/mobile/medications
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        try {
            $prescriptions = $this->firebaseService->getPrescriptions(null, $userId, 'active');

            return response()->json([
                'success' => true,
                'data' => $prescriptions,
            ]);
        } catch (\Throwable $e) {
            Log::error('MobileMedicationController@index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve prescriptions',
            ], 500);
        }
    }

    /**
     * Get a specific prescription.
     * GET /api/mobile/medications/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        try {
            $prescription = $this->firebaseService->getPrescriptionById($id);

            if (!$prescription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prescription not found',
                ], 404);
            }

            // Ensure the patient can only see their own prescriptions
            if (($prescription['patient_id'] ?? '') !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $prescription,
            ]);
        } catch (\Throwable $e) {
            Log::error('MobileMedicationController@show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve prescription',
            ], 500);
        }
    }

    /**
     * Toggle medication reminders on/off for a prescription.
     * POST /api/mobile/medications/{id}/toggle-reminder
     */
    public function toggleReminder(Request $request, string $id): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        $enabled = $request->boolean('enabled', true);

        try {
            $prescription = $this->firebaseService->getPrescriptionById($id);

            if (!$prescription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prescription not found',
                ], 404);
            }

            if (($prescription['patient_id'] ?? '') !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $success = $this->firebaseService->togglePrescriptionReminders($id, $enabled);

            return response()->json([
                'success' => $success,
                'message' => $enabled ? 'Reminders enabled' : 'Reminders disabled',
                'data' => ['reminders_enabled' => $enabled],
            ]);
        } catch (\Throwable $e) {
            Log::error('MobileMedicationController@toggleReminder error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle reminder',
            ], 500);
        }
    }

    /**
     * Snooze the next dose reminder for a specific medication.
     * POST /api/mobile/medications/{id}/snooze
     */
    public function snoozeReminder(Request $request, string $id): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        $request->validate([
            'medication_index' => 'required|integer|min:0',
            'snooze_minutes' => 'nullable|integer|min:5|max:60',
        ]);

        $medicationIndex = $request->input('medication_index');
        $snoozeMinutes = $request->input('snooze_minutes', 15);

        try {
            $prescription = $this->firebaseService->getPrescriptionById($id);

            if (!$prescription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prescription not found',
                ], 404);
            }

            if (($prescription['patient_id'] ?? '') !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $medications = $prescription['medications'] ?? [];
            if (!isset($medications[$medicationIndex])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid medication index',
                ], 400);
            }

            $snoozedUntil = (new \DateTime())->modify("+{$snoozeMinutes} minutes")->format('Y-m-d\TH:i:s\Z');

            // Persist snooze state to Firestore
            $medications[$medicationIndex]['snoozed_until'] = $snoozedUntil;
            $this->firebaseService->updatePrescriptionMedications($id, $medications);

            return response()->json([
                'success' => true,
                'message' => "Reminder snoozed for {$snoozeMinutes} minutes",
                'data' => [
                    'snoozed_until' => $snoozedUntil,
                    'medication_name' => $medications[$medicationIndex]['name'] ?? '',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('MobileMedicationController@snoozeReminder error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to snooze reminder',
            ], 500);
        }
    }

    /**
     * Get upcoming doses across all active prescriptions.
     * GET /api/mobile/medications/upcoming
     */
    public function getUpcomingDoses(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        try {
            $doses = $this->firebaseService->getUpcomingDoses($userId);

            return response()->json([
                'success' => true,
                'data' => $doses,
            ]);
        } catch (\Throwable $e) {
            Log::error('MobileMedicationController@getUpcomingDoses error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming doses',
            ], 500);
        }
    }

    private function resolveUserId(Request $request): ?string
    {
        $uid = data_get($request->input('firebase_user'), 'uid');
        return is_string($uid) && $uid !== '' ? $uid : null;
    }
}
