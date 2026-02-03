#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing FirebaseRestClient with GeoPoint and Timestamp support\n\n";

$service = app(App\Services\FirebaseService::class);

// Test getClinics
echo "📍 Testing getClinics():\n";
$clinics = $service->getClinics();
echo "   Count: " . count($clinics) . "\n";
if (!empty($clinics)) {
    $firstClinic = $clinics[0];
    echo "   First clinic: {$firstClinic['name']}\n";
    if (isset($firstClinic['location'])) {
        echo "   Location (GeoPoint): " . json_encode($firstClinic['location']) . "\n";
    }
    if (isset($firstClinic['working_hours'])) {
        echo "   Working hours present: YES\n";
    }
}
echo "\n";

// Test getBookings
echo "📋 Testing getBookings():\n";
$bookings = $service->getBookings();
echo "   Count: " . count($bookings) . "\n";
if (!empty($bookings)) {
    $firstBooking = $bookings[0];
    echo "   First booking structure: " . json_encode(array_keys($firstBooking)) . "\n";
    if (isset($firstBooking['id'])) {
        echo "   First booking ID: {$firstBooking['id']}\n";
    }
    if (isset($firstBooking['patient'])) {
        echo "   Patient: {$firstBooking['patient']}\n";
    }
    if (isset($firstBooking['status'])) {
        echo "   Status: {$firstBooking['status']}\n";
    }
}
echo "\n";

// Test getAlerts
echo "⚠️  Testing getAlerts():\n";
$alerts = $service->getAlerts();
echo "   Count: " . count($alerts) . "\n";
echo "\n";

// Test getSettings
echo "⚙️  Testing getSettings():\n";
$settings = $service->getSettings();
echo "   Hospital name: {$settings['hospital_name']}\n";
echo "\n";

// Test getDashboardStats
echo "📊 Testing getDashboardStats():\n";
$stats = $service->getDashboardStats();
echo "   Total: {$stats['total']}\n";
echo "   Waiting: {$stats['waiting']}\n";
echo "   Avg Wait: {$stats['avg_wait']}\n";
echo "   No Show: {$stats['no_show']}\n";
echo "\n";

echo "✅ All tests completed!\n";
