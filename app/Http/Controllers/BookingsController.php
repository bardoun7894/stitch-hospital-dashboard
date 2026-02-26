<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FirebaseService;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Cache;
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
        $hospitalId = $currentUser['hospital_id'] ?? null;
        $userClinicId = $currentUser['clinic_id'] ?? null;
        $clinicId = $request->query('clinic_id') ?: $userClinicId;

        // Doctor sees only their own bookings
        if ($role === 'doctor') {
            $doctorId = $currentUser['doctor_id'] ?? null;
            $today = date('Y-m-d');
            $doctorBookings = $doctorId ? $this->firebaseService->getBookingsForDoctor($doctorId, $today) : [];

            // Build a minimal data structure compatible with bookings.index view
            $queueState = ($userClinicId && $doctorId)
                ? $this->firebaseService->getDoctorQueueState($userClinicId, $doctorId, $today)
                : ['now_serving' => 0, 'last_issued' => 0, 'is_paused' => false, 'remaining' => 0];

            $formattedBookings = [];
            $statusCounts = ['pending' => 0, 'accepted' => 0, 'confirmed' => 0, 'arrived' => 0, 'completed' => 0, 'cancelled' => 0, 'noShow' => 0];
            foreach ($doctorBookings as $b) {
                $rawStatus = $b['status'] ?? 'pending';
                $displayStatus = match($rawStatus) {
                    'pending' => 'Pending',
                    'acceptedAwaitingPayment' => 'Accepted',
                    'confirmed' => 'Confirmed',
                    'arrived' => 'Arrived',
                    'completed' => 'Completed',
                    'cancelledByClinic', 'cancelled' => 'Cancelled',
                    'noShow', 'skipped' => 'No Show',
                    default => ucfirst($rawStatus),
                };
                $color = match($rawStatus) {
                    'confirmed' => 'blue',
                    'arrived' => 'green',
                    'pending' => 'yellow',
                    'acceptedAwaitingPayment' => 'blue',
                    'completed' => 'green',
                    default => 'gray',
                };

                // Count statuses
                $countKey = match($rawStatus) {
                    'pending' => 'pending',
                    'acceptedAwaitingPayment' => 'accepted',
                    'confirmed' => 'confirmed',
                    'arrived' => 'arrived',
                    'completed' => 'completed',
                    'cancelledByClinic', 'cancelled' => 'cancelled',
                    'noShow', 'skipped' => 'noShow',
                    default => 'pending',
                };
                $statusCounts[$countKey] = ($statusCounts[$countKey] ?? 0) + 1;

                $scheduledDate = $b['scheduled_date'] ?? null;
                $timeDisplay = '';
                if ($scheduledDate instanceof \Google\Cloud\Core\Timestamp) {
                    $timeDisplay = $scheduledDate->get()->format('h:i A');
                }

                $formattedBookings[] = [
                    'id' => $b['id'] ?? '',
                    'token' => $b['token_number'] ?? '-',
                    'patient' => $b['patient_name'] ?? '-',
                    'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($b['patient_name'] ?? 'P') . '&background=e5e7eb&color=111418&size=32',
                    'type' => $b['type'] ?? '-',
                    'status' => $displayStatus,
                    'color' => $color,
                    'time' => $timeDisplay,
                    'is_followup' => $b['is_followup'] ?? false,
                    'payment_status' => $b['payment_status'] ?? null,
                    'payment_note' => $b['payment_note'] ?? '',
                    'is_arrived' => $rawStatus === 'arrived',
                ];
            }

            $data = [
                'bookings' => $formattedBookings,
                'status_counts' => $statusCounts,
                'queue_state' => [
                    'clinic_id' => $userClinicId ?? '',
                    'doctor_id' => $doctorId ?? '',
                    'date' => $today,
                    'is_paused' => $queueState['is_paused'] ?? false,
                ],
                'current_serving' => [
                    'token' => $queueState['now_serving'] ?? '00',
                    'patient' => __('messages.waiting_text'),
                    'type' => '-',
                    'id' => '',
                ],
                'next_up' => [],
                'skipped' => [],
            ];

            return view('bookings.index', [
                'data' => $data,
                'clinics' => [],
                'selectedClinicId' => $userClinicId,
            ]);
        }

        // Cache the clinic list separately (changes infrequently)
        $clinicsCacheKey = 'bookings_clinics_' . md5("{$role}_{$hospitalId}_{$userClinicId}");
        $clinics = Cache::remember($clinicsCacheKey, 300, function () use ($role, $hospitalId, $userClinicId) {
            if ($role === 'super_admin') {
                return $this->firebaseService->getClinics();
            } elseif ($role === 'hospital_manager' && $hospitalId) {
                return $this->firebaseService->getClinicsForHospital($hospitalId);
            } elseif ($userClinicId) {
                $clinic = $this->firebaseService->getClinic($userClinicId);
                return $clinic ? [$clinic] : [];
            } else {
                return $this->firebaseService->getClinics();
            }
        });

        // For hospital_manager without specific clinic selected, use first hospital clinic
        if ($role === 'hospital_manager' && $hospitalId && !$clinicId && !empty($clinics)) {
            $clinicId = $clinics[0]['id'] ?? null;
        }

        // Queue data is time-sensitive, use a short cache TTL
        $dataCacheKey = 'bookings_queue_data_' . md5(($clinicId ?? 'all') . "_{$role}_{$hospitalId}");
        $data = Cache::remember($dataCacheKey, 60, function () use ($clinicId) {
            return $this->firebaseService->getQueueData($clinicId);
        });

        // Build per-doctor booking counts for reception/clinic_admin/hospital_manager
        $doctorsToday = [];
        if (in_array($role, ['reception', 'clinic_admin', 'hospital_manager', 'super_admin']) && !empty($data['bookings'])) {
            $doctorsCacheKey = 'bookings_doctors_list_' . md5(($clinicId ?? 'all') . "_{$role}_{$hospitalId}");
            $doctorsList = Cache::remember($doctorsCacheKey, 300, function () use ($clinicId, $role, $hospitalId) {
                if ($clinicId) {
                    return $this->firebaseService->getDoctors($clinicId);
                } elseif ($role === 'hospital_manager' && $hospitalId) {
                    return $this->firebaseService->getDoctorsForHospital($hospitalId);
                }
                return $this->firebaseService->getDoctors();
            });

            // Build a name lookup from doctor list
            $doctorNames = [];
            foreach ($doctorsList as $doc) {
                $doctorNames[$doc['id']] = $doc['name'] ?? $doc['id'];
            }

            // Count bookings per doctor (online vs walk-in)
            $doctorCounts = [];
            foreach ($data['bookings'] as $booking) {
                $did = $booking['doctor_id'] ?? '';
                if (empty($did)) continue;
                if (!isset($doctorCounts[$did])) {
                    $doctorCounts[$did] = ['online' => 0, 'walkin' => 0, 'total' => 0];
                }
                $type = strtolower($booking['type'] ?? '');
                if (str_contains($type, 'walk') || str_contains($type, 'حضور')) {
                    $doctorCounts[$did]['walkin']++;
                } else {
                    $doctorCounts[$did]['online']++;
                }
                $doctorCounts[$did]['total']++;
            }

            // Build final array with doctor names
            foreach ($doctorCounts as $did => $counts) {
                $doctorsToday[] = [
                    'id' => $did,
                    'name' => $doctorNames[$did] ?? $did,
                    'online' => $counts['online'],
                    'walkin' => $counts['walkin'],
                    'total' => $counts['total'],
                ];
            }

            // Sort by total bookings descending
            usort($doctorsToday, fn($a, $b) => $b['total'] <=> $a['total']);
        }

        return view('bookings.index', [
            'data' => $data,
            'clinics' => $clinics ?? [],
            'selectedClinicId' => $clinicId,
            'doctorsToday' => $doctorsToday,
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

            // Audit log: booking accepted
            $this->firebaseService->logActivity('booking.accepted', [
                'booking_id' => $id,
                'patient_name' => $bookingData['patient_name'] ?? '',
                'doctor_name' => $bookingData['doctor_name'] ?? '',
            ]);

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

            // Audit log: booking rejected
            $this->firebaseService->logActivity('booking.rejected', [
                'booking_id' => $id,
                'patient_name' => $bookingData['patient_name'] ?? '',
                'reason' => $validated['reason'] ?? '',
            ]);

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
            
            // Audit log: booking confirmed (payment)
            $this->firebaseService->logActivity('booking.confirmed', [
                'booking_id' => $id,
                'patient_name' => $bookingData['patient_name'] ?? '',
                'token_number' => $newTokenNumber,
            ]);

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
    /**
     * Record cash payment for a booking at reception.
     * Changes status from 'acceptedAwaitingPayment' to 'confirmed'.
     * Assigns a token number and marks payment as cash.
     *
     * @param Request $request
     * @param string $id Booking ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function confirmCashPayment(Request $request, string $id)
    {
        try {
            $currentUser = RoleMiddleware::getCurrentUser();
            $confirmedBy = $currentUser['name'] ?? $currentUser['email'] ?? $currentUser['id'] ?? 'staff';

            $tokenNumber = $this->firebaseService->confirmCashPayment($id, $confirmedBy);

            // Send notification to patient
            $firestore = $this->firebaseService->getFirestore();
            $bookingDoc = $firestore->collection('bookings')->document($id)->snapshot();
            if ($bookingDoc->exists()) {
                $bookingData = $bookingDoc->data();
                $patientId = $bookingData['patient_id'] ?? null;
                if ($patientId) {
                    $doctorName = $bookingData['doctor_name'] ?? 'الطبيب';
                    $clinicName = $bookingData['clinic_name'] ?? 'العيادة';

                    $this->sendBookingStatusNotification(
                        $patientId,
                        $id,
                        'booking_confirmed',
                        'تم تأكيد حجزك!',
                        "رقم دورك: $tokenNumber\n$doctorName - $clinicName\nتم تأكيد الدفع النقدي",
                        ['token_number' => (string) $tokenNumber]
                    );
                }
            }

            return redirect()->back()->with('success', __('messages.cash_payment_confirmed_token', ['token' => $tokenNumber]));
        } catch (\Exception $e) {
            Log::error('Cash payment error: ' . $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function create()
    {
        $doctors = $this->firebaseService->getDoctors();
        $patients = $this->firebaseService->getPatients();
        $selectedDoctorId = request()->query('doctor_id', '');

        return view('bookings.create', compact('doctors', 'patients', 'selectedDoctorId'));
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

         // Audit log: booking cancelled
         $this->firebaseService->logActivity('booking.cancelled', [
             'booking_id' => $id,
             'reason' => $request->input('reason', __('messages.admin_cancellation')),
         ]);

         return response()->json(['success' => true, 'message' => __('messages.booking_cancelled_msg')]);
    }
}
