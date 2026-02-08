<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * QueueController - Manages clinic queue operations for reception.
 * 
 * Provides endpoints for:
 * - Advancing the queue (Next)
 * - Skipping a patient
 * - Pausing/resuming queue
 * - Getting current queue state
 */
class QueueController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Get current queue state for a clinic/doctor.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $clinicId = $request->query('clinic_id') ?: ($currentUser['clinic_id'] ?? null);

            $queueData = $this->firebaseService->getQueueData($clinicId);

            return response()->json([
                'success' => true,
                'data' => $queueData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Advance queue to next patient.
     * Updates now_serving to the next token number.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function next(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clinic_id' => 'required|string',
            'doctor_id' => 'required|string',
            'date' => 'required|date',
        ]);

        try {
            $firestore = $this->firebaseService->getFirestore();
            $dateKey = date('Y-m-d', strtotime($validated['date']));
            
            $queueRef = $firestore->collection('clinics')
                ->document($validated['clinic_id'])
                ->collection('doctors')
                ->document($validated['doctor_id'])
                ->collection('dates')
                ->document($dateKey);
            
            $queueDoc = $queueRef->snapshot();
            
            $currentNowServing = $queueDoc->exists() ? ($queueDoc->data()['now_serving'] ?? 0) : 0;
            $lastIssued = $queueDoc->exists() ? ($queueDoc->data()['last_issued'] ?? 0) : 0;
            
            // Don't advance past the last issued token
            if ($currentNowServing >= $lastIssued && $lastIssued > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'لا يوجد مرضى في الانتظار',
                    'message' => 'No patients waiting in queue',
                ], 400);
            }
            
            $newNowServing = $currentNowServing + 1;
            
            $queueRef->set([
                'now_serving' => $newNowServing,
                'last_issued' => $lastIssued,
                'is_paused' => $queueDoc->exists() ? ($queueDoc->data()['is_paused'] ?? false) : false,
                'updated_at' => new \DateTime(),
            ], ['merge' => true]);
            
            // T043: Send proximity notifications to patients whose turn is approaching
            try {
                $this->firebaseService->sendQueueProximityNotifications(
                    $validated['clinic_id'],
                    $validated['doctor_id'],
                    $dateKey
                );
            } catch (\Exception $e) {
                // Log error but don't fail the queue advance
                \Log::warning('Failed to send proximity notifications: ' . $e->getMessage());
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'now_serving' => $newNowServing,
                    'last_issued' => $lastIssued,
                    'remaining' => $lastIssued - $newNowServing,
                ],
                'message' => "تم استدعاء الرقم {$newNowServing}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Skip current patient and move to next.
     * Increments now_serving by 1 (same as next, but semantically different).
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function skip(Request $request): JsonResponse
    {
        // Skip is essentially the same as next - just move to next number
        // The skipped patient's booking should be marked appropriately
        $validated = $request->validate([
            'clinic_id' => 'required|string',
            'doctor_id' => 'required|string',
            'date' => 'required|date',
            'booking_id' => 'nullable|string',
        ]);

        try {
            $firestore = $this->firebaseService->getFirestore();
            
            // If booking_id provided, mark it as skipped (no-show)
            if (!empty($validated['booking_id'])) {
                $bookingRef = $firestore->collection('bookings')->document($validated['booking_id']);
                $bookingRef->update([
                    ['path' => 'status', 'value' => 'noShow'],
                    ['path' => 'updated_at', 'value' => new \DateTime()],
                ]);
            }
            
            // Then advance the queue
            return $this->next($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pause or resume the queue.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function togglePause(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clinic_id' => 'required|string',
            'doctor_id' => 'required|string',
            'date' => 'required|date',
            'paused' => 'required|boolean',
        ]);

        try {
            $firestore = $this->firebaseService->getFirestore();
            $dateKey = date('Y-m-d', strtotime($validated['date']));
            
            $queueRef = $firestore->collection('clinics')
                ->document($validated['clinic_id'])
                ->collection('doctors')
                ->document($validated['doctor_id'])
                ->collection('dates')
                ->document($dateKey);
            
            $queueRef->set([
                'is_paused' => $validated['paused'],
                'updated_at' => new \DateTime(),
            ], ['merge' => true]);
            
            $statusText = $validated['paused'] ? 'تم إيقاف الطابور مؤقتاً' : 'تم استئناف الطابور';
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_paused' => $validated['paused'],
                ],
                'message' => $statusText,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get queue statistics for a specific date.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clinic_id' => 'required|string',
            'doctor_id' => 'required|string',
            'date' => 'required|date',
        ]);

        try {
            $firestore = $this->firebaseService->getFirestore();
            $dateKey = date('Y-m-d', strtotime($validated['date']));
            
            $queueRef = $firestore->collection('clinics')
                ->document($validated['clinic_id'])
                ->collection('doctors')
                ->document($validated['doctor_id'])
                ->collection('dates')
                ->document($dateKey);
            
            $queueDoc = $queueRef->snapshot();
            
            if (!$queueDoc->exists()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'now_serving' => 0,
                        'last_issued' => 0,
                        'remaining' => 0,
                        'is_paused' => false,
                    ],
                ]);
            }
            
            $data = $queueDoc->data();
            $nowServing = $data['now_serving'] ?? 0;
            $lastIssued = $data['last_issued'] ?? 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'now_serving' => $nowServing,
                    'last_issued' => $lastIssued,
                    'remaining' => max(0, $lastIssued - $nowServing),
                    'is_paused' => $data['is_paused'] ?? false,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Recall a patient (send notification/alert TV).
     */
    public function recall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'required|string',
        ]);

        try {
            $this->firebaseService->recallPatient($validated['booking_id']);
            return response()->json([
                'success' => true,
                'message' => 'تم استدعاء المريض مرة أخرى',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Re-insert a skipped patient back into the active queue.
     */
    public function reinsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'required|string',
        ]);

        try {
            $this->firebaseService->reinsertPatient($validated['booking_id']);
            return response()->json([
                'success' => true,
                'message' => 'تم إعادة المريض إلى قائمة الانتظار',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
