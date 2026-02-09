<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MobileBookingController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Get user's bookings
     * GET /api/mobile/bookings
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

        $locale = $request->input('locale', 'ar');

        try {
            $bookings = Cache::remember("mobile_user_bookings_{$userId}_{$locale}", 30, function () use ($userId, $locale) {
                return $this->firebaseService->getUserBookings($userId, $locale);
            });

            return response()->json([
                'success' => true,
                'data' => $bookings,
                'message' => __('messages.bookings_retrieved')
            ]);
        } catch (\Throwable $e) {
            Log::error('Get bookings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.failed_to_retrieve_bookings')
            ], 500);
        }
    }

    /**
     * Get specific booking details
     * GET /api/mobile/bookings/{bookingId}
     */
    public function show(Request $request, string $bookingId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        $locale = $request->input('locale', 'ar');

        try {
            $booking = Cache::remember("mobile_booking_{$bookingId}_{$locale}", 60, function () use ($bookingId, $locale) {
                return $this->firebaseService->getBookingDetails($bookingId, $locale);
            });

            // Verify booking belongs to user
            if ($booking['patient_id'] !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.unauthorized_access')
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $booking
            ]);
        } catch (\Throwable $e) {
            Log::error('Get booking details error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.booking_not_found')
            ], 404);
        }
    }

    /**
     * Create a new booking
     * POST /api/mobile/bookings
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
            'clinic_id' => 'required|string',
            'doctor_id' => 'required|string',
            'scheduled_date' => 'required|date',
            'notes' => 'nullable|string',
            'family_member_ids' => 'nullable|array',
            'family_member_ids.*' => 'string',
            'includes_self' => 'nullable|boolean',
        ]);

        try {
            // Denormalize doctor and patient names
            $doctorName = '';
            $patientName = '';
            $doctor = null;
            try {
                $doctor = $this->firebaseService->getDoctorById($validated['doctor_id']);
                $doctorName = $doctor['name'] ?? '';
                $patient = $this->firebaseService->getPatientDetails($userId);
                $patientName = $patient['name'] ?? '';
            } catch (\Throwable $e) {
                // Non-critical, continue without denormalized names
            }

            // Check if current time falls within the doctor's working hours
            $hoursCheck = $this->firebaseService->isWithinWorkingHours($validated['clinic_id'], $validated['doctor_id']);
            if (!$hoursCheck['within_hours']) {
                return response()->json([
                    'success' => false,
                    'message' => $hoursCheck['message'],
                    'data' => ['sessions' => $hoursCheck['sessions']],
                ], 400);
            }

            // Check if patient is eligible for a follow-up booking (time window + same clinic)
            $followupCheck = $this->firebaseService->isFollowupEligible($validated['doctor_id'], $userId, $validated['clinic_id']);
            $isFollowup = $followupCheck['eligible'];
            $treatmentPlanId = $isFollowup ? ($followupCheck['plan']['id'] ?? "{$validated['doctor_id']}_{$userId}") : null;

            $bookingId = $this->firebaseService->createMobileBooking([
                'patient_id' => $userId,
                'clinic_id' => $validated['clinic_id'],
                'doctor_id' => $validated['doctor_id'],
                'scheduled_date' => $validated['scheduled_date'],
                'notes' => $validated['notes'] ?? '',
                'family_member_ids' => $validated['family_member_ids'] ?? [],
                'includes_self' => $validated['includes_self'] ?? true,
                'doctor_name' => $doctorName,
                'patient_name' => $patientName,
                'status' => 'pending',
                'payment_status' => $isFollowup ? 'waived_followup' : 'unpaid',
                'is_followup' => $isFollowup,
                'treatment_plan_id' => $treatmentPlanId,
            ]);

            $paymentNote = $isFollowup
                ? null
                : 'الدفع عند الوصول - لن يتم عرض رقم الدور الا بعد تأكيد الدفع';

            return response()->json([
                'success' => true,
                'data' => [
                    'booking_id' => $bookingId,
                    'is_followup' => $isFollowup,
                    'followup_reason' => $isFollowup ? null : ($followupCheck['reason'] ?? null),
                    'payment_note' => $paymentNote,
                ],
                'message' => __('messages.booking_created')
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Create booking error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.booking_creation_error')
            ], 500);
        }
    }

    /**
     * Cancel a booking
     * POST /api/mobile/bookings/{bookingId}/cancel
     */
    public function cancel(Request $request, string $bookingId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string',
        ]);

        try {
            // Verify booking belongs to user
            $booking = $this->firebaseService->getBookingDetails($bookingId);
            if ($booking['patient_id'] !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.unauthorized_access')
                ], 403);
            }

            $this->firebaseService->cancelBooking($bookingId, $validated['reason'] ?? '');

            return response()->json([
                'success' => true,
                'message' => __('messages.booking_cancelled')
            ]);
        } catch (\Throwable $e) {
            Log::error('Cancel booking error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.cancel_failed')
            ], 500);
        }
    }

    /**
     * Confirm arrival at clinic
     * POST /api/mobile/bookings/{bookingId}/arrive
     */
    public function confirmArrival(Request $request, string $bookingId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        try {
            // Verify booking belongs to user
            $booking = $this->firebaseService->getBookingDetails($bookingId);
            if ($booking['patient_id'] !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.unauthorized_access')
                ], 403);
            }

            // Verify geofence (within clinic radius)
            $clinic = $this->firebaseService->getClinicById($booking['clinic_id']);
            $distance = $this->calculateDistance(
                $validated['latitude'],
                $validated['longitude'],
                $clinic['location']['latitude'],
                $clinic['location']['longitude']
            );

            if ($distance > ($clinic['geofence_radius_meters'] ?? 100)) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.not_within_geofence'),
                    'data' => ['distance' => round($distance, 2)]
                ], 400);
            }

            // Mark as arrived
            $this->firebaseService->markArrived($bookingId);

            return response()->json([
                'success' => true,
                'message' => __('messages.arrival_confirmed')
            ]);
        } catch (\Throwable $e) {
            Log::error('Confirm arrival error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.arrival_failed')
            ], 500);
        }
    }

    /**
     * Reschedule a booking to a new date
     * POST /api/mobile/bookings/{bookingId}/reschedule
     */
    public function reschedule(Request $request, string $bookingId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => __('messages.authentication_failed')
            ], 401);
        }

        $validated = $request->validate([
            'new_date' => 'required|date|after_or_equal:today',
        ]);

        try {
            // Verify booking belongs to user
            $booking = $this->firebaseService->getBookingDetails($bookingId);
            if ($booking['patient_id'] !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.unauthorized_access')
                ], 403);
            }

            // Only allow reschedule for pending or confirmed bookings
            $status = $booking['status'] ?? '';
            if (!in_array($status, ['pending', 'confirmed', 'acceptedAwaitingPayment'])) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.cannot_reschedule')
                ], 400);
            }

            $this->firebaseService->rescheduleBooking($bookingId, $validated['new_date']);

            return response()->json([
                'success' => true,
                'message' => __('messages.booking_rescheduled')
            ]);
        } catch (\Throwable $e) {
            Log::error('Reschedule booking error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => __('messages.reschedule_failed')
            ], 500);
        }
    }

    /**
     * Calculate distance between two coordinates (in meters)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }


    /**
     * Confirm payment for pay-at-arrival bookings and assign token number.
     * POST /api/mobile/bookings/{bookingId}/confirm-payment
     */
    public function confirmPayment(Request $request, string $bookingId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed'
            ], 401);
        }

        try {
            $firestore = $this->firebaseService->getFirestore();
            $bookingRef = $firestore->collection('bookings')->document($bookingId);
            $booking = $bookingRef->snapshot();

            if (!$booking->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'الحجز غير موجود'
                ], 404);
            }

            $bookingData = $booking->data();

            // Verify booking belongs to user
            if (($bookingData['patient_id'] ?? '') !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $currentStatus = $bookingData['status'] ?? '';
            if ($currentStatus !== 'acceptedAwaitingPayment') {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تأكيد الدفع - الحالة: ' . $currentStatus
                ], 400);
            }

            // Get queue state and assign next token
            $clinicId = $bookingData['clinic_id'];
            $doctorId = $bookingData['doctor_id'];
            $scheduledDate = $bookingData['scheduled_date']->toDateTime();
            $dateKey = $scheduledDate->format('Y-m-d');

            $queueRef = $firestore->collection('clinics')
                ->document($clinicId)
                ->collection('doctors')
                ->document($doctorId)
                ->collection('dates')
                ->document($dateKey);

            $queueDoc = $queueRef->snapshot();
            $lastIssued = $queueDoc->exists() ? ($queueDoc->data()['last_issued'] ?? 0) : 0;
            $newTokenNumber = $lastIssued + 1;

            // Update queue state
            $nowServing = $queueDoc->exists() ? ($queueDoc->data()['now_serving'] ?? 0) : 0;
            $isPaused = $queueDoc->exists() ? ($queueDoc->data()['is_paused'] ?? false) : false;
            $queueRef->set([
                'last_issued' => $newTokenNumber,
                'now_serving' => $nowServing,
                'is_paused' => $isPaused,
                'status' => $isPaused ? 'paused' : 'running',
                'updated_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            ], ['merge' => true]);

            // Update booking with confirmed status and token
            $bookingRef->update([
                ['path' => 'status', 'value' => 'confirmed'],
                ['path' => 'token_number', 'value' => $newTokenNumber],
                ['path' => 'payment_status', 'value' => 'pay_on_arrival'],
                ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'token_number' => $newTokenNumber,
                    'status' => 'confirmed',
                ],
                'message' => 'تم تأكيد الحجز وإصدار رقم الدور'
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Confirm payment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل تأكيد الدفع: ' . $e->getMessage()
            ], 500);
        }
    }
    private function resolveUserId(Request $request): ?string
    {
        $uid = data_get($request->input('firebase_user'), 'uid');
        return is_string($uid) && $uid !== '' ? $uid : null;
    }
}
