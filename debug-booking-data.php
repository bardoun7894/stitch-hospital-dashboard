#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Debugging Booking Data Structure\n\n";

try {
    $service = app(App\Services\FirebaseService::class);
    $firestore = $service->getFirestore();

    if ($firestore) {
        $docs = $firestore->collection('bookings')->documents();

        echo "📋 First booking raw data:\n";
        $count = 0;
        foreach ($docs as $doc) {
            if ($doc->exists()) {
                $data = $doc->data();
                echo "Booking ID: " . $doc->id() . "\n";
                echo "Raw data:\n";
                print_r($data);
                echo "\n";

                // Try to fetch doctor
                if (isset($data['clinic_id']) && isset($data['doctor_id'])) {
                    echo "Trying to fetch: clinics/{$data['clinic_id']}/doctors/{$data['doctor_id']}\n";
                }

                $count++;
                if ($count >= 2) break; // Show first 2 bookings
            }
        }
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
