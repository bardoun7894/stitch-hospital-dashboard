<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use Illuminate\Console\Command;

class SendDoseReminders extends Command
{
    protected $signature = 'medications:send-reminders {--window=5 : Minutes window ahead to check for due doses}';
    protected $description = 'Send FCM push notifications for medication doses that are due within the next N minutes';

    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    public function handle(): int
    {
        $windowMinutes = (int) $this->option('window');
        $this->info("Checking for doses due within {$windowMinutes} minutes...");

        $firestore = $this->firebaseService->getFirestore();
        if (!$firestore) {
            $this->error('Firestore client not available.');
            return self::FAILURE;
        }

        try {
            // Query all active prescriptions with reminders enabled
            $snapshot = $firestore->collection('prescriptions')
                ->where('status', '=', 'active')
                ->where('reminders_enabled', '=', true)
                ->documents();

            $now = new \DateTime();
            $windowEnd = (clone $now)->modify("+{$windowMinutes} minutes");
            $notificationsSent = 0;

            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;

                $prescription = $doc->data();
                $prescription['id'] = $doc->id();
                $patientId = $prescription['patient_id'] ?? null;

                if (!$patientId) continue;

                $startDate = isset($prescription['start_date'])
                    ? new \DateTime($prescription['start_date'])
                    : null;

                if (!$startDate) continue;

                $medications = $prescription['medications'] ?? [];

                foreach ($medications as $medIndex => $med) {
                    $durationDays = (int) ($med['duration_days'] ?? 0);
                    $intervalHours = (float) ($med['interval_hours'] ?? 0);
                    $firstDoseTime = $med['first_dose_time'] ?? '08:00';

                    if ($durationDays <= 0 || $intervalHours <= 0) continue;

                    $medEndDate = (clone $startDate)->modify("+{$durationDays} days");
                    if ($now > $medEndDate) continue;

                    // Check if this medication is snoozed
                    $snoozedUntil = $med['snoozed_until'] ?? null;
                    if ($snoozedUntil) {
                        $snoozeTime = new \DateTime($snoozedUntil);
                        if ($now < $snoozeTime) continue; // Still snoozed
                    }

                    // Calculate next dose
                    $timeParts = explode(':', $firstDoseTime);
                    $firstDose = (clone $startDate)
                        ->setTime((int) ($timeParts[0] ?? 8), (int) ($timeParts[1] ?? 0));

                    if ($now < $firstDose) {
                        $nextDose = $firstDose;
                    } else {
                        $intervalMinutes = (int) ($intervalHours * 60);
                        $diffMinutes = (int) (($now->getTimestamp() - $firstDose->getTimestamp()) / 60);
                        $intervalsPassed = (int) floor($diffMinutes / $intervalMinutes);
                        $nextDose = (clone $firstDose)->modify("+" . (($intervalsPassed + 1) * $intervalMinutes) . " minutes");
                    }

                    if ($nextDose > $medEndDate) continue;

                    // Check if dose falls within the reminder window [now, now + windowMinutes]
                    if ($nextDose >= $now && $nextDose <= $windowEnd) {
                        // Deduplicate: check if we already sent a reminder for this dose
                        $doseKey = $doc->id() . '_' . $medIndex . '_' . $nextDose->format('Y-m-d_H-i');
                        $lastSentKey = $med['last_reminder_sent'] ?? null;
                        if ($lastSentKey === $doseKey) continue; // Already sent

                        // Get patient FCM token
                        $userDoc = $firestore->collection('users')->document($patientId)->snapshot();
                        if (!$userDoc->exists()) continue;

                        $userData = $userDoc->data();
                        $fcmToken = $userData['fcm_token'] ?? null;
                        if (!$fcmToken) continue;

                        $medName = $med['name'] ?? 'دواء';
                        $doseAmount = $med['dose_amount'] ?? '';
                        $doseUnit = $med['dose_unit'] ?? '';
                        $doseTime = $nextDose->format('H:i');

                        $title = "حان موعد الدواء - {$medName}";
                        $body = "الجرعة: {$doseAmount} {$doseUnit} - الساعة {$doseTime}";

                        $this->firebaseService->sendFCMNotification(
                            $fcmToken,
                            $title,
                            $body,
                            [
                                'type' => 'medication_reminder',
                                'user_id' => $patientId,
                                'prescription_id' => $doc->id(),
                                'medication_index' => (string) $medIndex,
                                'medication_name' => $medName,
                                'dose_time' => $doseTime,
                            ]
                        );

                        // Store in-app notification
                        $this->firebaseService->storeNotification(
                            $patientId,
                            $title,
                            $body,
                            'reminder',
                            [
                                'prescription_id' => $doc->id(),
                                'medication_index' => (string) $medIndex,
                            ]
                        );

                        // Mark as sent to prevent duplicates
                        $medications[$medIndex]['last_reminder_sent'] = $doseKey;
                        $this->firebaseService->updatePrescriptionMedications($doc->id(), $medications);

                        $notificationsSent++;
                        $this->line("  -> Sent reminder for {$medName} to patient {$patientId}");
                    }
                }
            }

            $this->info("Done. {$notificationsSent} reminder(s) sent.");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            \Log::error('SendDoseReminders error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
