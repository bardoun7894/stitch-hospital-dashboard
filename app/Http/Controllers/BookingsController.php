<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Log;

class BookingsController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification to patient about booking status change.
     * 
     * @param string $userId Patient's user ID
     * @param string $bookingId Booking ID
     * @param string $type Notification type (booking_accepted, booking_rejected, etc.)
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $extraData Additional data for the notification
     */
    protected function sendBookingStatusNotification(
        string $userId,
        string $bookingId,
        string $type,
        string $title,
        string $body,
        array $extraData = []
    ): void {
        try {
            $firestore = $this->firebaseService->getFirestore();
            $userDoc = $firestore->collection('users')->document($userId)->snapshot();
            
            if (!$userDoc->exists()) {
                \Log::warning("User not found for notification: $userId");
                return;
            }
            
            $userData = $userDoc->data();
            $fcmToken = $userData['fcm_token'] ?? null;

            // Store notification in Firestore
            $this->firebaseService->storeNotification(
                $userId,
                $title,
                $body,
                'booking',
                array_merge([
                    'booking_id' => $bookingId,
                    'type' => $type,
                ], $extraData)
            );

            // Send push notification if FCM token exists
            if ($fcmToken) {
                $this->firebaseService->sendFCMNotification(
                    $fcmToken,
                    $title,
                    $body,
                    array_merge([
                        'type' => $type,
                        'booking_id' => $bookingId,
                        'user_id' => $userId,
                    ], $extraData)
                );
            }

            \Log::channel('api')->info("Booking status notification ($type) sent to user $userId for booking $bookingId");
        } catch (\Exception $e) {
            \Log::error("Error sending booking status notification: " . $e->getMessage());
        }
    }

    /**
     * Display list of bookings, filtered by clinic.
     */
    public function index(Request $request)
    {
        $currentUser = RoleMiddleware::getCurrentUser();
        $role = $currentUser['role'] ?? 'patient';
        $clinicId = $request->query('clinic_id') ?: ($currentUser['clinic_id'] ?? null);

        // Build clinic list for selector dropdown
        if (in_array($role, ['super_admin', 'hospital_manager'])) {
            $clinics = $this->firebaseService->getClinics();
        } elseif ($clinicId) {
            $clinic = $this->firebaseService->getClinic($clinicId);
            $clinics = $clinic ? [$clinic] : [];
        } else {
            $clinics = $this->firebaseService->getClinics();
        }

        $data = $this->firebaseService->getQueueData($clinicId);

        return view('bookings.index', [
            'data' => $data,
            'clinics' => $clinics ?? [],
            'selectedClinicId' => $clinicId,
        ]);
    }

    /**
     * Poll for pending bookings (AJAX endpoint for real-time notifications).
     */
    public function pendingBookings(): JsonResponse
    {
        $pending = $this->firebaseService->getPendingBookings();
        return response()->json([
            'count' => count($pending),
            'bookings' => $pending,
        ]);
    }

    /**
     * Accept a pending booking.
     * Changes status from 'pending' to 'acceptedAwaitingPayment'.
     * 
     * @param Request $request
     * @param string $id Booking ID
     * @return JsonResponse
     */
    public function accept(Request $request, string $id): JsonResponse
    {
        try {
            $firestore = $this->firebaseService->getFirestore();
            $bookingRef = $firestore->collection('bookings')->document($id);
            $booking = $bookingRef->snapshot();
            
            if (!$booking->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => __('messages.booking_not_found'),
                ], 404);
            }

            $currentStatus = $booking->data()['status'] ?? '';
            if ($currentStatus !== 'pending') {
                return response()->json([
                    'success' => false,
                    'error' => __('messages.cannot_accept') . ' - ' . __('messages.status') . ': ' . $currentStatus,
                ], 400);
            }
            
            $bookingData = $booking->data();

            // Working-hours enforcement at acceptance time
            $clinicId = $bookingData['clinic_id'] ?? '';
            $doctorId = $bookingData['doctor_id'] ?? '';
            $forceOverride = $request->boolean('force_override', false);

            if (!$forceOverride && $clinicId && $doctorId) {
                $hoursCheck = $this->firebaseService->isWithinWorkingHours($clinicId, $doctorId);
                if (!$hoursCheck['within_hours']) {
                    return response()->json([
                        'success' => false,
                        'error' => $hoursCheck['message'],
                        'data' => ['sessions' => $hoursCheck['sessions']],
                    ], 400);
                }
            }

            // Admin override: force a free follow-up regardless of window/clinic policy
            $forceFollowup = $request->boolean('force_followup', false);
            $isFollowup = $bookingData['is_followup'] ?? false;

            if ($forceFollowup && !$isFollowup) {
                // Admin is forcing this to be treated as a free follow-up
                $isFollowup = true;
                $bookingRef->update([
                    ['path' => 'is_followup', 'value' => true],
                    ['path' => 'payment_status', 'value' => 'waived_followup'],
                    ['path' => 'admin_override', 'value' => true],
                ]);
            }

            // Double-check follow-up eligibility at acceptance time (window may have expired, or different clinic)
            if ($isFollowup && !$forceFollowup) {
                $followupCheck = $this->firebaseService->isFollowupEligible(
                    $bookingData['doctor_id'] ?? '',
                    $bookingData['patient_id'] ?? '',
                    $bookingData['clinic_id'] ?? ''
                );
                $isFollowup = $followupCheck['eligible'];

                if (!$isFollowup) {
                    // Follow-up no longer valid, treat as normal paid booking
                    $followupDowngradeReason = $followupCheck['reason'] ?? 'unknown';
                    $bookingRef->update([
                        ['path' => 'is_followup', 'value' => false],
                        ['path' => 'payment_status', 'value' => 'unpaid'],
                        ['path' => 'followup_downgrade_reason', 'value' => $followupDowngradeReason],
                    ]);
                }
            }

            if ($isFollowup) {
                // Follow-up booking: skip payment, go directly to confirmed with token
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
                $isPaused = $queueDoc->exists() ? ($queueDoc->data()['is_paused'] ?? false) : false;
                $queueRef->set([
                    'last_issued' => $newTokenNumber,
                    'now_serving' => $queueDoc->exists() ? ($queueDoc->data()['now_serving'] ?? 0) : 0,
                    'is_paused' => $isPaused,
                    'status' => $isPaused ? 'paused' : 'running',
                    'updated_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                ], ['merge' => true]);

                // Update booking: confirmed + token, payment waived
                $bookingRef->update([
                    ['path' => 'status', 'value' => 'confirmed'],
                    ['path' => 'token_number', 'value' => $newTokenNumber],
                    ['path' => 'payment_status', 'value' => 'waived_followup'],
                    ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
                ]);

                // Send notification to patient
                $patientId = $bookingData['patient_id'] ?? null;
                if ($patientId) {
                    $doctorName = $bookingData['doctor_name'] ?? 'الطبيب';
                    $clinicName = $bookingData['clinic_name'] ?? 'العيادة';

                    $this->sendBookingStatusNotification(
                        $patientId,
                        $id,
                        'booking_confirmed',
                        'تم تأكيد حجز المتابعة!',
                        "رقم دورك: $newTokenNumber\n$doctorName - $clinicName\nحجز متابعة - بدون رسوم",
                        ['token_number' => (string) $newTokenNumber]
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => __('messages.followup_booking_confirmed') . ' - ' . __('messages.token_number') . ': ' . $newTokenNumber,
                    'data' => [
                        'id' => $id,
                        'status' => 'confirmed',
                        'token_number' => $newTokenNumber,
                        'is_followup' => true,
                    ],
                ]);
            }

            // Normal flow: set to acceptedAwaitingPayment
            $paymentNote = 'الدفع عند الوصول - لن يتم عرض رقم الدور الا بعد تأكيد الدفع';
            $bookingRef->update([
                ['path' => 'status', 'value' => 'acceptedAwaitingPayment'],
                ['path' => 'payment_note', 'value' => $paymentNote],
                ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
            ]);

            // Send notification to patient
            $patientId = $bookingData['patient_id'] ?? null;
            if ($patientId) {
                $doctorName = $bookingData['doctor_name'] ?? 'الطبيب';
                $clinicName = $bookingData['clinic_name'] ?? 'العيادة';

                $this->sendBookingStatusNotification(
                    $patientId,
                    $id,
                    'booking_accepted',
                    'تم قبول حجزك!',
                    "تم قبول حجزك مع $doctorName في $clinicName. يرجى إتمام الدفع للحصول على رقم دورك."
                );
            }

            $responseData = [
                'id' => $id,
                'status' => 'acceptedAwaitingPayment',
            ];

            // Include follow-up downgrade reason if it was originally a follow-up
            if (isset($followupDowngradeReason)) {
                $responseData['followup_downgraded'] = true;
                $responseData['followup_downgrade_reason'] = $followupDowngradeReason;
            }

            return response()->json([
                'success' => true,
                'message' => __('messages.booking_accepted'),
                'data' => $responseData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a pending booking.
     * Changes status from 'pending' to 'cancelledByClinic'.
     * 
     * @param Request $request
     * @param string $id Booking ID
     * @return JsonResponse
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $firestore = $this->firebaseService->getFirestore();
            $bookingRef = $firestore->collection('bookings')->document($id);
            $booking = $bookingRef->snapshot();
            
            if (!$booking->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => __('messages.booking_not_found'),
                ], 404);
            }

            $bookingData = $booking->data();
            $currentStatus = $bookingData['status'] ?? '';
            if ($currentStatus !== 'pending') {
                return response()->json([
                    'success' => false,
                    'error' => __('messages.cannot_reject') . ' - ' . __('messages.status') . ': ' . $currentStatus,
                ], 400);
            }

            $updates = [
                ['path' => 'status', 'value' => 'cancelledByClinic'],
                ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
            ];

            if (!empty($validated['reason'])) {
                $updates[] = ['path' => 'rejection_reason', 'value' => $validated['reason']];
            }

            $bookingRef->update($updates);

            // Send notification to patient
            $patientId = $bookingData['patient_id'] ?? null;
            if ($patientId) {
                $doctorName = $bookingData['doctor_name'] ?? 'الطبيب';
                $clinicName = $bookingData['clinic_name'] ?? 'العيادة';
                $reason = $validated['reason'] ?? '';
                
                $body = "عذراً، تم رفض طلب حجزك مع $doctorName في $clinicName.";
                if ($reason) {
                    $body .= " السبب: $reason";
                }
                
                $this->sendBookingStatusNotification(
                    $patientId,
                    $id,
                    'booking_rejected',
                    'تم رفض طلب الحجز',
                    $body,
                    ['reason' => $reason]
                );
            }

            return response()->json([
                'success' => true,
                'message' => __('messages.booking_rejected'),
                'data' => [
                    'id' => $id,
                    'status' => 'cancelledByClinic',
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
     * Confirm payment and assign token number.
     * Changes status from 'acceptedAwaitingPayment' to 'confirmed'.
     * Generates and assigns a token number.
     * 
     * @param Request $request
     * @param string $id Booking ID
     * @return JsonResponse
     */
    public function confirmPayment(Request $request, string $id): JsonResponse
    {
        try {
            $firestore = $this->firebaseService->getFirestore();
            $bookingRef = $firestore->collection('bookings')->document($id);
            $booking = $bookingRef->snapshot();
            
            if (!$booking->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => __('messages.booking_not_found'),
                ], 404);
            }

            $bookingData = $booking->data();
            $currentStatus = $bookingData['status'] ?? '';

            if ($currentStatus !== 'acceptedAwaitingPayment') {
                return response()->json([
                    'success' => false,
                    'error' => __('messages.cannot_confirm_payment') . ' - ' . __('messages.status') . ': ' . $currentStatus,
                ], 400);
            }

            // Get queue state and increment last_issued
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
            $isPaused = $queueDoc->exists() ? ($queueDoc->data()['is_paused'] ?? false) : false;
            $queueRef->set([
                'last_issued' => $newTokenNumber,
                'now_serving' => $queueDoc->exists() ? ($queueDoc->data()['now_serving'] ?? 0) : 0,
                'is_paused' => $isPaused,
                'status' => $isPaused ? 'paused' : 'running',
                'updated_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            ], ['merge' => true]);

            // Update booking with token
            $bookingRef->update([
                ['path' => 'status', 'value' => 'confirmed'],
                ['path' => 'token_number', 'value' => $newTokenNumber],
                ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
            ]);
            
            // Send notification to patient with token number
            $patientId = $bookingData['patient_id'] ?? null;
            if ($patientId) {
                $doctorName = $bookingData['doctor_name'] ?? 'الطبيب';
                $clinicName = $bookingData['clinic_name'] ?? 'العيادة';
                
                $this->sendBookingStatusNotification(
                    $patientId,
                    $id,
                    'booking_confirmed',
                    'تم تأكيد حجزك!',
                    "رقم دورك: $newTokenNumber\n$doctorName - $clinicName",
                    ['token_number' => (string) $newTokenNumber]
                );
            }
            
            return response()->json([
                'success' => true,
                'message' => __('messages.payment_confirmed') . ' - ' . __('messages.token_number') . ': ' . $newTokenNumber,
                'data' => [
                    'id' => $id,
                    'status' => 'confirmed',
                    'token_number' => $newTokenNumber,
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
     * Mark patient as arrived (from GPS confirmation or manual).
     * 
     * @param Request $request
     * @param string $id Booking ID
     * @return JsonResponse
     */
    public function markArrived(Request $request, string $id): JsonResponse
    {
        try {
            $firestore = $this->firebaseService->getFirestore();
            $bookingRef = $firestore->collection('bookings')->document($id);
            $booking = $bookingRef->snapshot();
            
            if (!$booking->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => __('messages.booking_not_found'),
                ], 404);
            }

            $currentStatus = $booking->data()['status'] ?? '';
            if ($currentStatus !== 'confirmed') {
                return response()->json([
                    'success' => false,
                    'error' => __('messages.cannot_mark_arrived') . ' - ' . __('messages.status') . ': ' . $currentStatus,
                ], 400);
            }

            $bookingRef->update([
                ['path' => 'status', 'value' => 'arrived'],
                ['path' => 'arrived_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
                ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.arrival_confirmed'),
                'data' => [
                    'id' => $id,
                    'status' => 'arrived',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function create()
    {
        $doctors = $this->firebaseService->getDoctors();
        // We need patients for the dropdown/search
        $patients = $this->firebaseService->getPatients(); 
        // Optimization: For many patients, we should use AJAX search. 
        // For MVP, passing all might be okay or just top 20 + search endpoint.
        // Let's rely on the search endpoint `patients.index` or just pass empty and expect AJAX.
        
        return view('bookings.create', compact('doctors', 'patients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|string',
            'doctor_id' => 'required|string',
            'scheduled_date' => 'required|date',
            'time_slot' => 'required|date_format:H:i',
            'type' => 'required|string'
        ]);

        $firestore = $this->firebaseService->getFirestore();
        if (!$firestore) return back()->with('error', __('messages.error'));

        try {
            $patient = $this->firebaseService->getPatientDetails($data['patient_id']);
            $doctor = $this->firebaseService->getDoctorById($data['doctor_id']);

            $doctorName = $doctor['name'] ?? __('messages.doctor_label');
            $clinicId = $doctor['clinic_id'] ?? '';

            // If no clinic_id from doctor, try current user's clinic
            if (empty($clinicId)) {
                $currentUser = RoleMiddleware::getCurrentUser();
                $clinicId = $currentUser['clinic_id'] ?? '';
            }

            $dateTime = new \DateTime($data['scheduled_date'] . ' ' . $data['time_slot']);
            $dateKey = $dateTime->format('Y-m-d');

            // Issue token number for manual booking (same logic as confirmPayment)
            $queueRef = $firestore->collection('clinics')
                ->document($clinicId)
                ->collection('doctors')
                ->document($data['doctor_id'])
                ->collection('dates')
                ->document($dateKey);

            $queueDoc = $queueRef->snapshot();
            $lastIssued = $queueDoc->exists() ? ($queueDoc->data()['last_issued'] ?? 0) : 0;
            $newTokenNumber = $lastIssued + 1;

            // Update queue state
            $queueRef->set([
                'last_issued' => $newTokenNumber,
                'now_serving' => $queueDoc->exists() ? ($queueDoc->data()['now_serving'] ?? 0) : 0,
                'is_paused' => $queueDoc->exists() ? ($queueDoc->data()['is_paused'] ?? false) : false,
                'updated_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            ], ['merge' => true]);

            $firestore->collection('bookings')->add([
                'patient_id' => $data['patient_id'],
                'doctor_id' => $data['doctor_id'],
                'clinic_id' => $clinicId,
                'scheduled_date' => new \Google\Cloud\Core\Timestamp($dateTime),
                'status' => 'confirmed',
                'token_number' => $newTokenNumber,
                'type' => $data['type'],
                'is_manual' => true,
                'created_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                'updated_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                'doctor_name' => $doctorName,
                'patient_name' => $patient['name'] ?? __('messages.unknown_error'),
                'clinic_name' => $doctor['clinic'] ?? '',
            ]);

            return redirect()->route('bookings.index')->with('success', __('messages.booking_created'));

        } catch (\Exception $e) {
            return back()->with('error', __('messages.booking_creation_error') . $e->getMessage());
        }
    }

    public function getSlots(Request $request)
    {
        $clinicId = $request->input('clinic_id');
        $doctorId = $request->input('doctor_id');
        $date = $request->input('date');

        if (!$doctorId || !$date) {
            return response()->json(['slots' => []]);
        }

        $slots = $this->firebaseService->getAvailableSlots($clinicId, $doctorId, $date);
        return response()->json(['slots' => $slots]);
    }
    public function reschedule($id)
    {
        $firestore = $this->firebaseService->getFirestore();
        if (!$firestore) return back()->with('error', __('messages.error'));

        $bookingDoc = $firestore->collection('bookings')->document($id)->snapshot();
        if (!$bookingDoc->exists()) return back()->with('error', __('messages.booking_not_found_msg'));
        
        $booking = $bookingDoc->data();
        $booking['id'] = $bookingDoc->id();
        
        if ($booking['scheduled_date'] instanceof \Google\Cloud\Core\Timestamp) {
            $dt = $booking['scheduled_date']->get();
            $booking['date'] = $dt->format('Y-m-d');
            $booking['time'] = $dt->format('H:i');
        } else {
             $booking['date'] = date('Y-m-d');
             $booking['time'] = '09:00';
        }
        
        $doctors = $this->firebaseService->getDoctors();
        return view('bookings.reschedule', compact('booking', 'doctors'));
    }

    public function processReschedule(Request $request, $id)
    {
        $data = $request->validate([
            'scheduled_date' => 'required|date',
            'time_slot' => 'required|date_format:H:i',
        ]);
        
        $firestore = $this->firebaseService->getFirestore();
        $bookingRef = $firestore->collection('bookings')->document($id);
        
        $dateTime = new \DateTime($data['scheduled_date'] . ' ' . $data['time_slot']);
        
        $bookingRef->update([
            ['path' => 'scheduled_date', 'value' => new \Google\Cloud\Core\Timestamp($dateTime)],
            ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
        ]);

        return redirect()->route('bookings.index')->with('success', __('messages.booking_rescheduled'));
    }

    public function cancel(Request $request, $id)
    {
         $firestore = $this->firebaseService->getFirestore();
         $bookingRef = $firestore->collection('bookings')->document($id);

         $bookingRef->update([
             ['path' => 'status', 'value' => 'cancelledByClinic'],
             ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
             ['path' => 'cancellation_reason', 'value' => $request->input('reason', __('messages.admin_cancellation'))]
         ]);

         return response()->json(['success' => true, 'message' => __('messages.booking_cancelled_msg')]);
    }
}
