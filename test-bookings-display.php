#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Bookings Display with Arabic Support\n\n";

$service = app(App\Services\FirebaseService::class);

$bookings = $service->getBookings();
echo "📋 Total Bookings: " . count($bookings) . "\n\n";

if (!empty($bookings)) {
    echo "First 5 bookings:\n";
    foreach (array_slice($bookings, 0, 5) as $i => $booking) {
        echo "\n" . ($i + 1) . ". Booking: " . ($booking['id'] ?? 'No ID') . "\n";
        echo "   Patient: " . ($booking['patient'] ?? 'N/A') . "\n";
        echo "   Doctor: " . ($booking['doctor_name'] ?? 'N/A') . "\n";
        echo "   Clinic: " . ($booking['clinic'] ?? 'N/A') . "\n";
        echo "   Status: " . ($booking['status'] ?? 'N/A') . "\n";
        echo "   Token: " . ($booking['token_number'] ?? 'Not assigned') . "\n";
    }
}

echo "\n✅ Test completed!\n";
