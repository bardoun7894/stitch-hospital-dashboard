<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FirebaseService;

class BookingsController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Display list of bookings.
     */
    public function index()
    {
        $data = $this->firebaseService->getQueueData();
        return view('bookings.index', ['data' => $data]);
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
            
            $bookingRef->update([
                ['path' => 'status', 'value' => 'acceptedAwaitingPayment'],
                ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
            ]);
            
            // TODO: Send notification to patient
            
            return response()->json([
                'success' => true,
                'message' => __('messages.booking_accepted'),
                'data' => [
                    'id' => $id,
                    'status' => 'acceptedAwaitingPayment',
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

            $currentStatus = $booking->data()['status'] ?? '';
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

            // TODO: Send notification to patient

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
            
            // TODO: Send notification to patient with token number
            
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
            'scheduled_date' => 'required|date', // Y-m-d
            'time_slot' => 'required|date_format:H:i',
            'type' => 'required|string'
        ]);
        
        // basic validation logic
        // Verify slot again?
        // Create booking
        
        $firestore = $this->firebaseService->getFirestore();
        if (!$firestore) return back()->with('error', __('messages.error'));

        try {
            // Get patient and doctor details for denormalization
            $patient = $this->firebaseService->getPatientDetails($data['patient_id']);
            // We need doctor name too, usually we store it.
            $doctors = $this->firebaseService->getDoctors(); // Cache these?
            $doctorName = __('messages.doctor_label');
            $clinicId = 'cardiology_center'; // fallback

            foreach($doctors as $d) {
                if ($d['id'] === $data['doctor_id']) {
                    $doctorName = $d['name'];
                    $clinicId = $d['clinic_id'] ?? 'cardiology_center';
                    break;
                }
            }

            $dateTime = new \DateTime($data['scheduled_date'] . ' ' . $data['time_slot']);

            $firestore->collection('bookings')->add([
                'patient_id' => $data['patient_id'],
                'doctor_id' => $data['doctor_id'],
                'clinic_id' => $clinicId,
                'scheduled_date' => new \Google\Cloud\Core\Timestamp($dateTime),
                'status' => 'confirmed', // Manual booking is confirmed
                'type' => $data['type'],
                'is_manual' => true,
                'created_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                'updated_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                // Denormalized data if needed
                'doctor_name' => $doctorName,
                'patient_name' => $patient['name'] ?? __('messages.unknown_error')
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
