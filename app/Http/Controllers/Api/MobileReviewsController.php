<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MobileReviewsController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Submit a review for a completed booking.
     * POST /api/mobile/reviews
     */
    public function store(Request $request): JsonResponse
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
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            // Get booking details and verify ownership + status
            $booking = $this->firebaseService->getBookingDetails($validated['booking_id']);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.booking_not_found')
                ], 404);
            }

            // Verify booking belongs to patient
            if (($booking['patient_id'] ?? '') !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.unauthorized_access')
                ], 403);
            }

            // Verify booking is completed
            if (($booking['status'] ?? '') !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.invalid_status')
                ], 400);
            }

            // Check if already reviewed
            if ($this->firebaseService->hasPatientReviewed($userId, $validated['booking_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.already_reviewed')
                ], 409);
            }

            // Get patient name
            $patient = $this->firebaseService->getPatientDetails($userId);
            $patientName = $patient['name'] ?? '';

            $reviewId = $this->firebaseService->createReview([
                'patient_id' => $userId,
                'patient_name' => $patientName,
                'doctor_id' => $booking['doctor_id'] ?? '',
                'doctor_name' => $booking['doctor_name'] ?? '',
                'clinic_id' => $booking['clinic_id'] ?? '',
                'booking_id' => $validated['booking_id'],
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? '',
            ]);

            if (!$reviewId) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.unknown_error')
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => ['review_id' => $reviewId],
                'message' => __('messages.review_submitted')
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Create review error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.unknown_error')
            ], 500);
        }
    }

    /**
     * Get reviews for a specific doctor (public).
     * GET /api/mobile/doctors/{doctorId}/reviews
     */
    public function doctorReviews(Request $request, string $doctorId): JsonResponse
    {
        try {
            $reviews = $this->firebaseService->getReviewsForDoctor($doctorId);

            // Also get doctor details for aggregate info
            $doctor = $this->firebaseService->getDoctorById($doctorId);

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews,
                    'avg_rating' => $doctor['avg_rating'] ?? 0,
                    'total_reviews' => $doctor['total_reviews'] ?? 0,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Get doctor reviews error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.unknown_error')
            ], 500);
        }
    }

    /**
     * Resolve the authenticated user's Firebase UID from the request.
     */
    private function resolveUserId(Request $request): ?string
    {
        $uid = data_get($request->input('firebase_user'), 'uid');
        return is_string($uid) && $uid !== '' ? $uid : null;
    }
}
