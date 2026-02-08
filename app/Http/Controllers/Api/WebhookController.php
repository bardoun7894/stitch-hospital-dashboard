<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\FirebaseService;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Handle Stripe webhook events.
     * Processes payment confirmations and updates booking status.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleStripeWebhook(Request $request): JsonResponse
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response()->json(['error' => __('messages.invalid_payload')], 400);
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            return response()->json(['error' => __('messages.invalid_signature')], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSuccess($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentFailure($event->data->object);
                break;

            case 'charge.refunded':
                $this->handleRefund($event->data->object);
                break;

            default:
                // Unexpected event type
                \Log::info('Unhandled Stripe webhook event: ' . $event->type);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful payment.
     * Updates booking status to 'confirmed' and generates token number.
     * 
     * @param object $paymentIntent
     */
    protected function handlePaymentSuccess($paymentIntent): void
    {
        try {
            $bookingId = $paymentIntent->metadata->booking_id ?? null;
            
            if (!$bookingId) {
                \Log::error('Payment success webhook missing booking_id in metadata');
                return;
            }

            $firestore = $this->firebaseService->getFirestore();
            $bookingRef = $firestore->collection('bookings')->document($bookingId);
            $booking = $bookingRef->snapshot();

            if (!$booking->exists()) {
                \Log::error("Booking not found: $bookingId");
                return;
            }

            $bookingData = $booking->data();
            
            // Generate token number
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

            // Update booking
            $now = new \Google\Cloud\Core\Timestamp(new \DateTime());
            $bookingRef->update([
                ['path' => 'status', 'value' => 'confirmed'],
                ['path' => 'payment_status', 'value' => 'paid'],
                ['path' => 'payment_intent_id', 'value' => $paymentIntent->id],
                ['path' => 'token_number', 'value' => $newTokenNumber],
                ['path' => 'payment_date', 'value' => $now],
                ['path' => 'updated_at', 'value' => $now],
            ]);

            \Log::channel('payments')->info("Payment successful for booking $bookingId, token: $newTokenNumber");

            // Send FCM notification to patient with token number
            $this->sendPaymentSuccessNotification($bookingId, $bookingData, $newTokenNumber);
            
        } catch (\Exception $e) {
            \Log::error('Error handling payment success: ' . $e->getMessage());
        }
    }

    /**
     * Send notification to patient after successful payment.
     */
    protected function sendPaymentSuccessNotification(string $bookingId, array $bookingData, int $tokenNumber): void
    {
        try {
            $userId = $bookingData['patient_id'] ?? null;
            if (!$userId) return;

            $firestore = $this->firebaseService->getFirestore();
            $userDoc = $firestore->collection('users')->document($userId)->snapshot();
            
            if (!$userDoc->exists()) return;
            
            $userData = $userDoc->data();
            $fcmToken = $userData['fcm_token'] ?? null;

            // Get doctor and clinic names for notification
            $doctorName = $bookingData['doctor_name'] ?? 'الطبيب';
            $clinicName = $bookingData['clinic_name'] ?? 'العيادة';

            $title = 'تم تأكيد حجزك!';
            $body = "رقم دورك: $tokenNumber\n$doctorName - $clinicName";

            // Store notification in Firestore
            $this->firebaseService->storeNotification(
                $userId,
                $title,
                $body,
                'booking',
                [
                    'booking_id' => $bookingId,
                    'token_number' => (string)$tokenNumber,
                    'type' => 'payment_success',
                ]
            );

            // Send push notification if FCM token exists
            if ($fcmToken) {
                $this->firebaseService->sendFCMNotification(
                    $fcmToken,
                    $title,
                    $body,
                    [
                        'type' => 'payment_success',
                        'booking_id' => $bookingId,
                        'token_number' => (string)$tokenNumber,
                        'user_id' => $userId,
                    ]
                );
            }

            \Log::channel('api')->info("Payment success notification sent to user $userId for booking $bookingId");
        } catch (\Exception $e) {
            \Log::error('Error sending payment success notification: ' . $e->getMessage());
        }
    }

    /**
     * Handle failed payment.
     * Updates booking payment status to 'failed'.
     * 
     * @param object $paymentIntent
     */
    protected function handlePaymentFailure($paymentIntent): void
    {
        try {
            $bookingId = $paymentIntent->metadata->booking_id ?? null;
            
            if (!$bookingId) {
                \Log::error('Payment failure webhook missing booking_id in metadata');
                return;
            }

            $firestore = $this->firebaseService->getFirestore();
            $bookingRef = $firestore->collection('bookings')->document($bookingId);
            
            $bookingRef->update([
                ['path' => 'payment_status', 'value' => 'failed'],
                ['path' => 'payment_error', 'value' => $paymentIntent->last_payment_error->message ?? 'Unknown error'],
                ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
            ]);

            \Log::channel('payments')->warning("Payment failed for booking $bookingId");

            // Send FCM notification to patient about payment failure
            $booking = $bookingRef->snapshot();
            if ($booking->exists()) {
                $this->sendPaymentFailureNotification(
                    $bookingId,
                    $booking->data(),
                    $paymentIntent->last_payment_error->message ?? 'Unknown error'
                );
            }
            
        } catch (\Exception $e) {
            \Log::error('Error handling payment failure: ' . $e->getMessage());
        }
    }

    /**
     * Send notification to patient after payment failure.
     */
    protected function sendPaymentFailureNotification(string $bookingId, array $bookingData, string $errorMessage): void
    {
        try {
            $userId = $bookingData['patient_id'] ?? null;
            if (!$userId) return;

            $firestore = $this->firebaseService->getFirestore();
            $userDoc = $firestore->collection('users')->document($userId)->snapshot();
            
            if (!$userDoc->exists()) return;
            
            $userData = $userDoc->data();
            $fcmToken = $userData['fcm_token'] ?? null;

            $doctorName = $bookingData['doctor_name'] ?? 'الطبيب';
            $clinicName = $bookingData['clinic_name'] ?? 'العيادة';

            $title = 'فشلت عملية الدفع';
            $body = "لم تتم عملية الدفع لحجزك مع $doctorName في $clinicName. يرجى المحاولة مرة أخرى.";

            // Store notification in Firestore
            $this->firebaseService->storeNotification(
                $userId,
                $title,
                $body,
                'payment',
                [
                    'booking_id' => $bookingId,
                    'type' => 'payment_failed',
                    'error' => $errorMessage,
                ]
            );

            // Send push notification if FCM token exists
            if ($fcmToken) {
                $this->firebaseService->sendFCMNotification(
                    $fcmToken,
                    $title,
                    $body,
                    [
                        'type' => 'payment_failed',
                        'booking_id' => $bookingId,
                        'user_id' => $userId,
                    ]
                );
            }

            \Log::channel('api')->info("Payment failure notification sent to user $userId for booking $bookingId");
        } catch (\Exception $e) {
            \Log::error('Error sending payment failure notification: ' . $e->getMessage());
        }
    }

    /**
     * Handle refund.
     * Updates booking status to reflect refund.
     * 
     * @param object $charge
     */
    protected function handleRefund($charge): void
    {
        try {
            // Find booking by payment intent ID
            $paymentIntentId = $charge->payment_intent;
            
            $firestore = $this->firebaseService->getFirestore();
            $bookingsRef = $firestore->collection('bookings');
            
            // Query for booking with this payment intent
            $query = $bookingsRef->where('payment_intent_id', '=', $paymentIntentId);
            $documents = $query->documents();
            
            foreach ($documents as $doc) {
                if ($doc->exists()) {
                    $now = new \Google\Cloud\Core\Timestamp(new \DateTime());
                    $doc->reference()->update([
                        ['path' => 'payment_status', 'value' => 'refunded'],
                        ['path' => 'refund_date', 'value' => $now],
                        ['path' => 'refund_amount', 'value' => $charge->amount_refunded / 100], // Convert from cents
                        ['path' => 'updated_at', 'value' => $now],
                    ]);
                    
                    \Log::info("Refund processed for booking " . $doc->id());
                }
            }
            
        } catch (\Exception $e) {
            \Log::error('Error handling refund: ' . $e->getMessage());
        }
    }

    /**
     * Create a payment intent for a booking.
     * Called from mobile app to initiate payment.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ]);

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int)($validated['amount'] * 100), // Convert to cents
                'currency' => strtolower($validated['currency']),
                'metadata' => [
                    'booking_id' => $validated['booking_id'],
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return response()->json([
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error creating payment intent: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => __('messages.payment_intent_failed'),
            ], 500);
        }
    }
}
