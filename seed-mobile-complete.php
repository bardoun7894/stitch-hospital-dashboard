#!/usr/bin/env php
<?php
/**
 * Complete Mobile App Seeder
 * Seeds ALL fields required by Flutter mobile app:
 * - Hospitals
 * - Clinics (with GeoPoints, working hours)
 * - Doctors (as subcollections with ratings, schedules)
 * - Users (with roles: patient, reception, doctor)
 * - Bookings (with proper state machine)
 * - Queue States
 */

// Load Firebase credentials
$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
if (!file_exists($credentialsPath)) {
    die("Error: Firebase credentials not found at {$credentialsPath}\n");
}

$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

echo "🔥 Starting Complete Mobile App Seeder\n";
echo "📍 Project: {$projectId}\n\n";

// OAuth2 Functions
function getAccessToken($credentials) {
    $jwt = createJWT($credentials);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function createJWT($credentials) {
    $now = time();
    $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claimSet = [
        'iss' => $credentials['client_email'],
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ];
    $payload = base64_encode(json_encode($claimSet));
    $signatureInput = $header . '.' . $payload;
    openssl_sign($signatureInput, $signature, $credentials['private_key'], 'SHA256');
    $signature = base64_encode($signature);
    return $signatureInput . '.' . $signature;
}

function createDocument($projectId, $accessToken, $path, $documentId, $data) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$path}?documentId={$documentId}";

    // Convert data to Firestore format
    $fields = convertToFirestoreFields($data);
    $body = json_encode(['fields' => $fields]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return $httpCode >= 200 && $httpCode < 300;
}

function convertToFirestoreFields($data) {
    $fields = [];
    foreach ($data as $key => $value) {
        if ($key === 'location' && is_array($value) && isset($value['latitude'], $value['longitude'])) {
            // GeoPoint
            $fields[$key] = [
                'geoPointValue' => [
                    'latitude' => $value['latitude'],
                    'longitude' => $value['longitude']
                ]
            ];
        } elseif (is_string($value)) {
            $fields[$key] = ['stringValue' => $value];
        } elseif (is_int($value)) {
            $fields[$key] = ['integerValue' => (string)$value];
        } elseif (is_float($value) || is_double($value)) {
            $fields[$key] = ['doubleValue' => $value];
        } elseif (is_bool($value)) {
            $fields[$key] = ['booleanValue' => $value];
        } elseif (is_null($value)) {
            $fields[$key] = ['nullValue' => null];
        } elseif (is_array($value)) {
            // Check if it's a map or array
            if (array_keys($value) === range(0, count($value) - 1)) {
                // It's an array
                $arrayValues = [];
                foreach ($value as $item) {
                    if (is_string($item)) {
                        $arrayValues[] = ['stringValue' => $item];
                    } elseif (is_int($item)) {
                        $arrayValues[] = ['integerValue' => (string)$item];
                    } elseif (is_array($item)) {
                        $arrayValues[] = ['mapValue' => ['fields' => convertToFirestoreFields($item)]];
                    }
                }
                $fields[$key] = ['arrayValue' => ['values' => $arrayValues]];
            } else {
                // It's a map (nested object)
                $fields[$key] = ['mapValue' => ['fields' => convertToFirestoreFields($value)]];
            }
        }
    }
    return $fields;
}

// Get access token
echo "🔐 Getting access token...\n";
$accessToken = getAccessToken($credentials);
if (!$accessToken) {
    die("❌ Failed to get access token\n");
}
echo "✅ Access token obtained\n\n";

$now = date('Y-m-d\TH:i:s\Z');
$today = date('Y-m-d');
$hospitalId = 'hospital_001';

// 1. Seed Hospital
echo "🏥 Seeding Hospital...\n";
$success = createDocument($projectId, $accessToken, 'hospitals', $hospitalId, [
    'name' => 'مستشفى المسار الذكي',
    'name_en' => 'Smart Path Hospital',
    'address' => 'Riyadh, Saudi Arabia',
    'location' => ['latitude' => 24.7136, 'longitude' => 46.6753],
    'phone' => '+966-11-1234567',
    'created_at' => $now
]);
echo $success ? "  ✓ Hospital created\n" : "  ✗ Hospital failed\n";
echo "\n";

// 2. Seed Clinics (already have GeoPoints and working hours from seed-firebase-complete.php)
echo "🏥 Clinics already seeded with GeoPoints and working hours\n";
echo "   Skipping clinic seeding (use seed-firebase-complete.php if needed)\n\n";

// 3. Seed Users with Roles
echo "👥 Seeding Users (patients, reception, doctors)...\n";

$users = [
    // Doctor Users (these link to doctor documents)
    [
        'id' => 'user_doc_sarah',
        'name' => 'Dr. Sarah Miller',
        'phone' => '+966-50-1234567',
        'email' => 'sarah.miller@hospital.sa',
        'role' => 'doctor',
        'photo_url' => null,
        'clinic_id' => 'gen_med_001',
        'hospital_id' => $hospitalId,
        'fcm_token' => null,
        'locale' => 'ar',
        'created_at' => $now,
        'updated_at' => $now,
        'family_members' => []
    ],
    [
        'id' => 'user_doc_emily',
        'name' => 'Dr. Emily Wong',
        'phone' => '+966-50-2345678',
        'email' => 'emily.wong@hospital.sa',
        'role' => 'doctor',
        'clinic_id' => 'peds_001',
        'hospital_id' => $hospitalId,
        'locale' => 'ar',
        'created_at' => $now,
        'updated_at' => $now,
        'family_members' => []
    ],
    // Reception Users
    [
        'id' => 'user_reception_001',
        'name' => 'أحمد السعيد',
        'phone' => '+966-50-3456789',
        'email' => 'ahmed@hospital.sa',
        'role' => 'reception',
        'clinic_id' => 'gen_med_001',
        'hospital_id' => $hospitalId,
        'locale' => 'ar',
        'created_at' => $now,
        'updated_at' => $now,
        'family_members' => []
    ],
    // Patient Users
    [
        'id' => 'patient_sarah_jenkins',
        'name' => 'سارة جينكينز',
        'phone' => '+966-55-1234567',
        'email' => 'sarah.jenkins@example.com',
        'role' => 'patient',
        'photo_url' => null,
        'clinic_id' => null,
        'hospital_id' => null,
        'fcm_token' => null,
        'locale' => 'ar',
        'created_at' => $now,
        'updated_at' => $now,
        'family_members' => [
            ['name' => 'محمد جينكينز', 'relationship' => 'son', 'birth_date' => '2018-05-15']
        ]
    ],
    [
        'id' => 'patient_john_doe',
        'name' => 'جون دو',
        'phone' => '+966-55-2345678',
        'email' => 'john.doe@example.com',
        'role' => 'patient',
        'locale' => 'ar',
        'created_at' => $now,
        'updated_at' => $now,
        'family_members' => []
    ],
    [
        'id' => 'patient_emma_wilson',
        'name' => 'إيما ويلسون',
        'phone' => '+966-55-3456789',
        'email' => 'emma.wilson@example.com',
        'role' => 'patient',
        'locale' => 'ar',
        'created_at' => $now,
        'updated_at' => $now,
        'family_members' => []
    ],
];

foreach ($users as $user) {
    $id = $user['id'];
    unset($user['id']);
    $success = createDocument($projectId, $accessToken, 'users', $id, $user);
    echo $success ? "  ✓ User: {$user['name']}\n" : "  ✗ Failed: {$user['name']}\n";
}
echo "\n";

// 4. Seed Doctors as Subcollections
echo "👨‍⚕️ Seeding Doctors (as subcollections with ratings, schedules)...\n";

$doctors = [
    [
        'clinic_id' => 'gen_med_001',
        'id' => 'doc_sarah_miller',
        'user_id' => 'user_doc_sarah',
        'name' => 'د. سارة ميلر',
        'name_en' => 'Dr. Sarah Miller',
        'specialty' => 'General Medicine',
        'avatar_url' => null,
        'rating' => 4.8,
        'review_count' => 127,
        'status' => 'available',
        'schedule' => [
            'sunday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'monday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'tuesday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'wednesday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'thursday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'friday' => ['open' => '00:00', 'close' => '00:00', 'enabled' => false],
            'saturday' => ['open' => '09:00', 'close' => '13:00', 'enabled' => true],
        ],
        'created_at' => $now
    ],
    [
        'clinic_id' => 'peds_001',
        'id' => 'doc_emily_wong',
        'user_id' => 'user_doc_emily',
        'name' => 'د. إميلي وونغ',
        'name_en' => 'Dr. Emily Wong',
        'specialty' => 'Pediatrics',
        'avatar_url' => null,
        'rating' => 4.9,
        'review_count' => 203,
        'status' => 'available',
        'schedule' => [
            'sunday' => ['open' => '08:00', 'close' => '18:00', 'enabled' => true],
            'monday' => ['open' => '08:00', 'close' => '18:00', 'enabled' => true],
            'tuesday' => ['open' => '08:00', 'close' => '18:00', 'enabled' => true],
            'wednesday' => ['open' => '08:00', 'close' => '18:00', 'enabled' => true],
            'thursday' => ['open' => '08:00', 'close' => '18:00', 'enabled' => true],
            'friday' => ['open' => '00:00', 'close' => '00:00', 'enabled' => false],
            'saturday' => ['open' => '09:00', 'close' => '13:00', 'enabled' => true],
        ],
        'created_at' => $now
    ],
];

foreach ($doctors as $doctor) {
    $clinicId = $doctor['clinic_id'];
    $doctorId = $doctor['id'];
    unset($doctor['clinic_id'], $doctor['id']);

    $path = "clinics/{$clinicId}/doctors";
    $success = createDocument($projectId, $accessToken, $path, $doctorId, $doctor);
    echo $success ? "  ✓ Doctor: {$doctor['name_en']} in {$clinicId}\n" : "  ✗ Failed: {$doctor['name_en']}\n";
}
echo "\n";

// 5. Seed Bookings with Proper State Machine
echo "📅 Seeding Bookings (with proper state machine)...\n";

$bookings = [
    // State: pending (just created, awaiting reception approval)
    [
        'id' => 'book_pending_001',
        'patient_id' => 'patient_sarah_jenkins',
        'doctor_id' => 'doc_sarah_miller',
        'clinic_id' => 'gen_med_001',
        'scheduled_date' => date('Y-m-d\TH:i:s\Z', strtotime('tomorrow 09:00')),
        'status' => 'pending',
        'token_number' => null,
        'rejection_reason' => null,
        'payment_id' => null,
        'arrived_at' => null,
        'completed_at' => null,
        'created_at' => $now,
        'updated_at' => $now
    ],
    // State: acceptedAwaitingPayment
    [
        'id' => 'book_awaiting_payment_001',
        'patient_id' => 'patient_john_doe',
        'doctor_id' => 'doc_emily_wong',
        'clinic_id' => 'peds_001',
        'scheduled_date' => date('Y-m-d\TH:i:s\Z', strtotime('tomorrow 10:00')),
        'status' => 'acceptedAwaitingPayment',
        'token_number' => null, // No token until payment
        'rejection_reason' => null,
        'payment_id' => null,
        'arrived_at' => null,
        'completed_at' => null,
        'created_at' => $now,
        'updated_at' => $now
    ],
    // State: confirmed (paid, token issued)
    [
        'id' => 'book_confirmed_001',
        'patient_id' => 'patient_emma_wilson',
        'doctor_id' => 'doc_sarah_miller',
        'clinic_id' => 'gen_med_001',
        'scheduled_date' => date('Y-m-d\TH:i:s\Z', strtotime('tomorrow 11:00')),
        'status' => 'confirmed',
        'token_number' => 101, // Token assigned after payment!
        'rejection_reason' => null,
        'payment_id' => 'pay_demo_12345',
        'arrived_at' => null,
        'completed_at' => null,
        'created_at' => $now,
        'updated_at' => $now
    ],
    // State: arrived (patient entered geofence)
    [
        'id' => 'book_arrived_001',
        'patient_id' => 'patient_sarah_jenkins',
        'doctor_id' => 'doc_sarah_miller',
        'clinic_id' => 'gen_med_001',
        'scheduled_date' => date('Y-m-d\TH:i:s\Z', strtotime('today 09:30')),
        'status' => 'arrived',
        'token_number' => 102,
        'payment_id' => 'pay_demo_12346',
        'arrived_at' => date('Y-m-d\TH:i:s\Z', strtotime('today 09:25')),
        'completed_at' => null,
        'created_at' => date('Y-m-d\TH:i:s\Z', strtotime('yesterday')),
        'updated_at' => $now
    ],
    // State: completed
    [
        'id' => 'book_completed_001',
        'patient_id' => 'patient_john_doe',
        'doctor_id' => 'doc_sarah_miller',
        'clinic_id' => 'gen_med_001',
        'scheduled_date' => date('Y-m-d\TH:i:s\Z', strtotime('today 08:00')),
        'status' => 'completed',
        'token_number' => 100,
        'payment_id' => 'pay_demo_12347',
        'arrived_at' => date('Y-m-d\TH:i:s\Z', strtotime('today 07:55')),
        'completed_at' => date('Y-m-d\TH:i:s\Z', strtotime('today 08:20')),
        'created_at' => date('Y-m-d\TH:i:s\Z', strtotime('2 days ago')),
        'updated_at' => $now
    ],
];

foreach ($bookings as $booking) {
    $id = $booking['id'];
    unset($booking['id']);
    $success = createDocument($projectId, $accessToken, 'bookings', $id, $booking);
    echo $success ? "  ✓ Booking: {$id} (status: {$booking['status']})\n" : "  ✗ Failed: {$id}\n";
}
echo "\n";

// 6. Seed Queue States
echo "🔢 Seeding Queue States...\n";

$queueStates = [
    [
        'id' => 'gen_med_001_sarah_' . $today,
        'clinic_id' => 'gen_med_001',
        'doctor_id' => 'doc_sarah_miller',
        'date' => $today,
        'now_serving' => 102,
        'last_issued' => 105,
        'is_paused' => false,
        'updated_at' => $now
    ],
    [
        'id' => 'peds_001_emily_' . $today,
        'clinic_id' => 'peds_001',
        'doctor_id' => 'doc_emily_wong',
        'date' => $today,
        'now_serving' => 50,
        'last_issued' => 52,
        'is_paused' => false,
        'updated_at' => $now
    ],
];

foreach ($queueStates as $state) {
    $id = $state['id'];
    unset($state['id']);
    $success = createDocument($projectId, $accessToken, 'queue_states', $id, $state);
    echo $success ? "  ✓ Queue State: {$state['clinic_id']} / {$state['doctor_id']}\n" : "  ✗ Failed\n";
}
echo "\n";

echo "✅ Complete mobile app seeding finished!\n";
echo "\n📊 Summary:\n";
echo " - 1 Hospital\n";
echo " - 5 Clinics (with GeoPoints, working hours) [pre-existing]\n";
echo " - 2 Doctors (as subcollections with ratings, schedules)\n";
echo " - 6 Users (2 doctors, 1 reception, 3 patients with roles)\n";
echo " - 5 Bookings (covering all states: pending → confirmed → arrived → completed)\n";
echo " - 2 Queue States (current day tracking)\n";
echo "\n🎉 Mobile app data structure ready!\n";
echo "\n📱 Test with mobile app:\n";
echo " - Users can register (saves to users/)\n";
echo " - Users can view clinics with geofencing\n";
echo " - Users can view doctors with ratings\n";
echo " - Users can create bookings (proper state machine)\n";
echo " - Bookings follow: pending → acceptedAwaitingPayment → confirmed → arrived → completed\n";
echo " - Token numbers only assigned when status = confirmed\n";
