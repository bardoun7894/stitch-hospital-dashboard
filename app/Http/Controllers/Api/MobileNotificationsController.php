<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MobileNotificationsController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Get user's notifications
     * GET /api/mobile/notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('firebase_user')['uid'];
            $notifications = $this->firebaseService->getUserNotifications($userId);

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            \Log::error('Get notifications error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get notifications'
            ], 500);
        }
    }

    /**
     * Mark a single notification as read
     * POST /api/mobile/notifications/{notificationId}/read
     */
    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        try {
            $userId = $request->input('firebase_user')['uid'];
            $this->firebaseService->markNotificationRead($userId, $notificationId);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            \Log::error('Mark notification read error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     * POST /api/mobile/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('firebase_user')['uid'];
            $count = $this->firebaseService->markAllNotificationsRead($userId);

            return response()->json([
                'success' => true,
                'message' => "Marked {$count} notifications as read"
            ]);
        } catch (\Exception $e) {
            \Log::error('Mark all notifications read error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notifications as read'
            ], 500);
        }
    }

    /**
     * Save/update FCM token for push notifications
     * POST /api/mobile/notifications/fcm-token
     */
    public function saveFcmToken(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('firebase_user')['uid'];
            $token = $request->input('token');

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token is required'
                ], 422);
            }

            $this->firebaseService->saveFcmToken($userId, $token);

            return response()->json([
                'success' => true,
                'message' => 'FCM token saved'
            ]);
        } catch (\Exception $e) {
            \Log::error('Save FCM token error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save FCM token'
            ], 500);
        }
    }
}
