<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MobileQueueController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Get queue status for a specific clinic/doctor/date
     * GET /api/mobile/queue/status
     */
    public function getStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clinic_id' => 'required|string',
            'doctor_id' => 'required|string',
            'date' => 'required|date_format:Y-m-d',
        ]);

        try {
            $queueState = $this->firebaseService->getQueueState(
                $validated['clinic_id'],
                $validated['doctor_id'],
                $validated['date']
            );

            return response()->json([
                'success' => true,
                'data' => $queueState
            ]);
        } catch (\Throwable $e) {
            Log::error('Get queue status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_to_get_queue_status')
            ], 500);
        }
    }

    /**
     * Get user's current position in queue
     * GET /api/mobile/queue/my-position
     */
    public function getMyPosition(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        $validated = $request->validate([
            'booking_id' => 'required|string',
        ]);

        try {
            $booking = $this->firebaseService->getBookingDetails($validated['booking_id']);

            // Verify booking belongs to user
            if ($booking['patient_id'] !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.unauthorized_access')
                ], 403);
            }

            if (!isset($booking['token_number'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.no_token_assigned')
                ], 400);
            }

            // Get queue state
            $queueState = $this->firebaseService->getQueueState(
                $booking['clinic_id'],
                $booking['doctor_id'],
                date('Y-m-d', strtotime($booking['scheduled_date']))
            );

            $myToken = $booking['token_number'];
            $nowServing = $queueState['now_serving'] ?? 0;
            $remaining = max(0, $myToken - $nowServing);

            return response()->json([
                'success' => true,
                'data' => [
                    'my_token' => $myToken,
                    'now_serving' => $nowServing,
                    'remaining' => $remaining,
                    'estimated_wait_minutes' => $remaining * 5, // 5 minutes per patient average
                    'queue_status' => $queueState['status'] ?? 'running',
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Get my position error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_to_get_position')
            ], 500);
        }
    }

    private function resolveUserId(Request $request): ?string
    {
        $uid = data_get($request->input('firebase_user'), 'uid');
        return is_string($uid) && $uid !== '' ? $uid : null;
    }
}
