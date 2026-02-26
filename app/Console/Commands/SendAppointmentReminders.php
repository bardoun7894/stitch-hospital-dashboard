<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';
    protected $description = 'Send appointment reminders for tomorrow\'s bookings';

    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    public function handle(): int
    {
        $tomorrow = (new \DateTime('+1 day'))->format('Y-m-d');
        $this->info("Checking bookings for {$tomorrow}...");

        $firestore = $this->firebaseService->getFirestore();
        if (!$firestore) {
            $this->error('Firestore client not available.');
            return self::FAILURE;
        }

        try {
            // Get tomorrow's confirmed bookings
            $bookings = $this->firebaseService->getBookingsForDate($tomorrow, [
                'confirmed',
                'acceptedAwaitingPayment',
            ]);

            if (empty($bookings)) {
                $this->info('No bookings found for tomorrow. Nothing to send.');
                return self::SUCCESS;
            }

            $sent = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($bookings as $booking) {
                $bookingId = $booking['id'] ?? null;
                if (!$bookingId) continue;

                // Skip if reminder already sent
                if (!empty($booking['reminder_sent'])) {
                    $skipped++;
                    continue;
                }

                try {
                    $patientId = $booking['patient_id'] ?? $booking['user_id'] ?? null;
                    $doctorName = $booking['doctor_name'] ?? 'Doctor';
                    $clinicName = $booking['clinic_name'] ?? 'Clinic';
                    $tokenNumber = $booking['token_number'] ?? '-';

                    if (!$patientId) {
                        $this->line("  [SKIP] Booking {$bookingId}: no patient ID");
                        $skipped++;
                        continue;
                    }

                    // Fetch patient data for FCM token and phone
                    $userDoc = $firestore->collection('users')->document($patientId)->snapshot();
                    if (!$userDoc->exists()) {
                        $this->line("  [SKIP] Booking {$bookingId}: patient {$patientId} not found");
                        $skipped++;
                        continue;
                    }

                    $userData = $userDoc->data();
                    $fcmToken = $userData['fcm_token'] ?? null;
                    $phone = $userData['phone'] ?? null;

                    // Build notification content
                    $titleEn = 'Appointment Reminder';
                    $titleAr = 'تذكير بالموعد';
                    $bodyEn = "You have an appointment tomorrow with Dr. {$doctorName} at {$clinicName}. Token #{$tokenNumber}";
                    $bodyAr = "لديك موعد غداً مع د. {$doctorName} في {$clinicName}. رقم الدور #{$tokenNumber}";

                    // Use Arabic as primary (matching existing app pattern), English as fallback
                    $title = $titleAr;
                    $body = $bodyAr;

                    $notificationData = [
                        'type' => 'reminder',
                        'user_id' => $patientId,
                        'booking_id' => $bookingId,
                        'doctor_name' => $doctorName,
                        'clinic_name' => $clinicName,
                        'token_number' => (string) $tokenNumber,
                        'scheduled_date' => $tomorrow,
                        'title_en' => $titleEn,
                        'body_en' => $bodyEn,
                        'title_ar' => $titleAr,
                        'body_ar' => $bodyAr,
                    ];

                    // Send FCM push notification if token available
                    if ($fcmToken) {
                        $this->firebaseService->sendFCMNotification(
                            $fcmToken,
                            $title,
                            $body,
                            $notificationData
                        );
                    } else {
                        // Store in-app notification even without FCM token
                        $this->firebaseService->storeNotification(
                            $patientId,
                            $title,
                            $body,
                            'reminder',
                            $notificationData
                        );
                    }

                    // Send SMS if phone number available
                    if ($phone) {
                        $smsMessage = "Reminder: Your appointment is tomorrow at {$clinicName} with Dr. {$doctorName}. Token #{$tokenNumber}. Please arrive on time.";
                        $this->firebaseService->sendSMS($phone, $smsMessage);
                    }

                    // Mark booking as reminded
                    $this->firebaseService->markReminderSent($bookingId);

                    $sent++;
                    $this->line("  [OK] Booking {$bookingId}: reminder sent to patient {$patientId}");

                } catch (\Exception $e) {
                    $failed++;
                    $this->error("  [FAIL] Booking {$bookingId}: " . $e->getMessage());
                    \Log::error("Appointment reminder failed for booking {$bookingId}: " . $e->getMessage());
                    // Continue processing other bookings
                }
            }

            $total = count($bookings);
            $this->info("Done. Total: {$total}, Sent: {$sent}, Skipped: {$skipped}, Failed: {$failed}");
            \Log::info("Appointment reminders: Total={$total}, Sent={$sent}, Skipped={$skipped}, Failed={$failed}");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            \Log::error('SendAppointmentReminders error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
