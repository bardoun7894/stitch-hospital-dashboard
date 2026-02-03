<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
        $userId = $request->input('firebase_user')['uid'];
        $locale = $request->input('locale', 'ar');

        try {
            $bookings = $this->firebaseService->getUserBookings($userId, $locale);

            return response()->json([
                'success' => true,
                'data' => $bookings,
                'message' => __('messages.bookings_retrieved')
            ]);
        } catch (\Exception $e) {
            \Log::error('Get bookings error: ' . $e->getMessage());
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
        $userId = $request->input('firebase_user')['uid'];
        $locale = $request->input('locale', 'ar');

        try {
            $booking = $this->firebaseService->getBookingDetails($bookingId, $locale);

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
        } catch (\Exception $e) {
            \Log::error('Get booking details error: ' . $e->getMessage());
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
        $userId = $request->input('firebase_user')['uid'];

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
            try {
                $doctor = $this->firebaseService->getDoctorById($validated['doctor_id']);
                $doctorName = $doctor['name'] ?? '';
                $patient = $this->firebaseService->getPatientDetails($userId);
                $patientName = $patient['name'] ?? '';
            } catch (\Exception $e) {
                // Non-critical, continue without denormalized names
            }

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
                'payment_status' => 'unpaid',
            ]);

            return response()->json([
                'success' => true,
                'data' => ['booking_id' => $bookingId],
                'message' => __('messages.booking_created')
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Create booking error: ' . $e->getMessage());
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
        $userId = $request->input('firebase_user')['uid'];

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
        } catch (\Exception $e) {
            \Log::error('Cancel booking error: ' . $e->getMessage());
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
        $userId = $request->input('firebase_user')['uid'];

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
        } catch (\Exception $e) {
            \Log::error('Confirm arrival error: ' . $e->getMessage());
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
        $userId = $request->input('firebase_user')['uid'];

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
        } catch (\Exception $e) {
            \Log::error('Reschedule booking error: ' . $e->getMessage());
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
}
