#!/usr/bin/env php
<?php
/**
 * Firebase/Firestore REST API Seeder
 * Bypasses gRPC by using REST API directly
 */

// Load Firebase credentials
$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
if (!file_exists($credentialsPath)) {
    die("Error: Firebase credentials not found at {$credentialsPath}\n");
}

$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

echo "🔥 Starting Firebase REST API Seeder\n";
echo "📍 Project: {$projectId}\n\n";

// Get OAuth2 access token
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
    curl_close($ch);

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

function createDocument($projectId, $accessToken, $collection, $documentId, $data) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}?documentId={$documentId}";

    // Convert data to Firestore format
    $fields = [];
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $fields[$key] = ['stringValue' => $value];
        } elseif (is_int($value)) {
            $fields[$key] = ['integerValue' => (string)$value];
        } elseif (is_bool($value)) {
            $fields[$key] = ['booleanValue' => $value];
        } elseif (is_null($value)) {
            $fields[$key] = ['nullValue' => null];
        }
    }

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
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

// Get access token
echo "🔐 Getting access token...\n";
$accessToken = getAccessToken($credentials);

if (!$accessToken) {
    die("❌ Failed to get access token\n");
}

echo "✅ Access token obtained\n\n";

// Seed Clinics
echo "📍 Seeding Clinics...\n";
$clinics = [
    ['id' => 'gen_med_001', 'name' => 'General Medicine', 'name_en' => 'General Medicine', 'doctors_on_duty' => 2, 'patients_waiting' => 8, 'avg_wait' => '15 min', 'status' => 'Open'],
    ['id' => 'peds_001', 'name' => 'Pediatrics', 'name_en' => 'Pediatrics', 'doctors_on_duty' => 2, 'patients_waiting' => 5, 'avg_wait' => '12 min', 'status' => 'Open'],
    ['id' => 'cardio_001', 'name' => 'Cardiology', 'name_en' => 'Cardiology', 'doctors_on_duty' => 2, 'patients_waiting' => 10, 'avg_wait' => '22 min', 'status' => 'Open'],
    ['id' => 'derma_001', 'name' => 'Dermatology', 'name_en' => 'Dermatology', 'doctors_on_duty' => 1, 'patients_waiting' => 3, 'avg_wait' => '10 min', 'status' => 'Open'],
    ['id' => 'ortho_001', 'name' => 'Orthopedics', 'name_en' => 'Orthopedics', 'doctors_on_duty' => 1, 'patients_waiting' => 6, 'avg_wait' => '18 min', 'status' => 'Open'],
];

foreach ($clinics as $clinic) {
    $id = $clinic['id'];
    unset($clinic['id']);

    if (createDocument($projectId, $accessToken, 'clinics', $id, $clinic)) {
        echo "  ✓ Clinic: {$clinic['name_en']}\n";
    } else {
        echo "  ✗ Failed: {$clinic['name_en']}\n";
    }
}

// Seed Doctors (as subcollections)
echo "\n👨‍⚕️ Seeding Doctors...\n";
$doctors = [
    // General Medicine doctors
    ['id' => 'doc_sarah_miller', 'clinic_id' => 'gen_med_001', 'name' => 'Dr. Sarah Miller', 'specialty' => 'General', 'clinic_name' => 'General Medicine', 'status' => 'On Duty'],
    ['id' => 'doc_john_smith', 'clinic_id' => 'gen_med_001', 'name' => 'Dr. John Smith', 'specialty' => 'General', 'clinic_name' => 'General Medicine', 'status' => 'On Duty'],
    // Pediatrics doctors
    ['id' => 'doc_emily_wong', 'clinic_id' => 'peds_001', 'name' => 'Dr. Emily Wong', 'specialty' => 'Pediatrics', 'clinic_name' => 'Pediatrics', 'status' => 'On Duty'],
    ['id' => 'doc_david_lee', 'clinic_id' => 'peds_001', 'name' => 'Dr. David Lee', 'specialty' => 'Pediatrics', 'clinic_name' => 'Pediatrics', 'status' => 'On Duty'],
    // Cardiology doctors
    ['id' => 'doc_james_chen', 'clinic_id' => 'cardio_001', 'name' => 'Dr. James Chen', 'specialty' => 'Cardiology', 'clinic_name' => 'Cardiology', 'status' => 'On Duty'],
    ['id' => 'doc_maria_garcia', 'clinic_id' => 'cardio_001', 'name' => 'Dr. Maria Garcia', 'specialty' => 'Cardiology', 'clinic_name' => 'Cardiology', 'status' => 'On Duty'],
    // Dermatology doctors
    ['id' => 'doc_linda_kim', 'clinic_id' => 'derma_001', 'name' => 'Dr. Linda Kim', 'specialty' => 'Dermatology', 'clinic_name' => 'Dermatology', 'status' => 'On Call'],
    ['id' => 'doc_robert_brown', 'clinic_id' => 'derma_001', 'name' => 'Dr. Robert Brown', 'specialty' => 'Dermatology', 'clinic_name' => 'Dermatology', 'status' => 'Off Duty'],
    // Orthopedics doctors
    ['id' => 'doc_michael_ross', 'clinic_id' => 'ortho_001', 'name' => 'Dr. Michael Ross', 'specialty' => 'Orthopedics', 'clinic_name' => 'Orthopedics', 'status' => 'On Duty'],
    ['id' => 'doc_anna_taylor', 'clinic_id' => 'ortho_001', 'name' => 'Dr. Anna Taylor', 'specialty' => 'Orthopedics', 'clinic_name' => 'Orthopedics', 'status' => 'On Call'],
];

foreach ($doctors as $doctor) {
    $id = $doctor['id'];
    $clinicId = $doctor['clinic_id'];
    unset($doctor['id'], $doctor['clinic_id']);

    $collection = "clinics/{$clinicId}/doctors";
    if (createDocument($projectId, $accessToken, $collection, $id, $doctor)) {
        echo "  ✓ Doctor: {$doctor['name']}\n";
    } else {
        echo "  ✗ Failed: {$doctor['name']}\n";
    }
}

// Seed Patients
echo "\n👥 Seeding Patients...\n";
$patients = [
    ['id' => 'patient_sarah_jenkins', 'name' => 'Sarah Jenkins', 'phone' => '555-0123', 'email' => 'sarah.jenkins@example.com', 'type' => 'patient'],
    ['id' => 'patient_john_doe', 'name' => 'John Doe', 'phone' => '555-0124', 'email' => 'john.doe@example.com', 'type' => 'patient'],
    ['id' => 'patient_emma_wilson', 'name' => 'Emma Wilson', 'phone' => '555-0125', 'email' => 'emma.wilson@example.com', 'type' => 'patient'],
    ['id' => 'patient_michael_brown', 'name' => 'Michael Brown', 'phone' => '555-0126', 'email' => 'michael.brown@example.com', 'type' => 'patient'],
    ['id' => 'patient_olivia_davis', 'name' => 'Olivia Davis', 'phone' => '555-0127', 'email' => 'olivia.davis@example.com', 'type' => 'patient'],
    ['id' => 'patient_william_moore', 'name' => 'William Moore', 'phone' => '555-0128', 'email' => 'william.moore@example.com', 'type' => 'patient'],
    ['id' => 'patient_sophia_taylor', 'name' => 'Sophia Taylor', 'phone' => '555-0129', 'email' => 'sophia.taylor@example.com', 'type' => 'patient'],
    ['id' => 'patient_james_anderson', 'name' => 'James Anderson', 'phone' => '555-0130', 'email' => 'james.anderson@example.com', 'type' => 'patient'],
    ['id' => 'patient_ava_thomas', 'name' => 'Ava Thomas', 'phone' => '555-0131', 'email' => 'ava.thomas@example.com', 'type' => 'patient'],
    ['id' => 'patient_robert_jackson', 'name' => 'Robert Jackson', 'phone' => '555-0132', 'email' => 'robert.jackson@example.com', 'type' => 'patient'],
    ['id' => 'patient_mia_white', 'name' => 'Mia White', 'phone' => '555-0133', 'email' => 'mia.white@example.com', 'type' => 'patient'],
    ['id' => 'patient_daniel_harris', 'name' => 'Daniel Harris', 'phone' => '555-0134', 'email' => 'daniel.harris@example.com', 'type' => 'patient'],
];

foreach ($patients as $patient) {
    $id = $patient['id'];
    unset($patient['id']);

    if (createDocument($projectId, $accessToken, 'users', $id, $patient)) {
        echo "  ✓ Patient: {$patient['name']}\n";
    } else {
        echo "  ✗ Failed: {$patient['name']}\n";
    }
}

// Seed Bookings
echo "\n📅 Seeding Bookings...\n";
$today = date('Y-m-d');
$bookings = [
    // Waiting bookings (6)
    ['id' => 'book_001', 'clinic_id' => 'gen_med_001', 'clinic_name' => 'General Medicine', 'doctor_id' => 'doc_sarah_miller', 'doctor_name' => 'Dr. Sarah Miller', 'patient_id' => 'patient_sarah_jenkins', 'patient_name' => 'Sarah Jenkins', 'booking_date' => $today, 'booking_time' => '09:00', 'status' => 'waiting', 'wait_time' => 12, 'queue_number' => 'A001'],
    ['id' => 'book_002', 'clinic_id' => 'peds_001', 'clinic_name' => 'Pediatrics', 'doctor_id' => 'doc_emily_wong', 'doctor_name' => 'Dr. Emily Wong', 'patient_id' => 'patient_emma_wilson', 'patient_name' => 'Emma Wilson', 'booking_date' => $today, 'booking_time' => '09:30', 'status' => 'waiting', 'wait_time' => 8, 'queue_number' => 'P001'],
    ['id' => 'book_003', 'clinic_id' => 'cardio_001', 'clinic_name' => 'Cardiology', 'doctor_id' => 'doc_james_chen', 'doctor_name' => 'Dr. James Chen', 'patient_id' => 'patient_john_doe', 'patient_name' => 'John Doe', 'booking_date' => $today, 'booking_time' => '10:00', 'status' => 'waiting', 'wait_time' => 15, 'queue_number' => 'C001'],
    ['id' => 'book_004', 'clinic_id' => 'gen_med_001', 'clinic_name' => 'General Medicine', 'doctor_id' => 'doc_john_smith', 'doctor_name' => 'Dr. John Smith', 'patient_id' => 'patient_michael_brown', 'patient_name' => 'Michael Brown', 'booking_date' => $today, 'booking_time' => '10:30', 'status' => 'waiting', 'wait_time' => 10, 'queue_number' => 'A002'],
    ['id' => 'book_005', 'clinic_id' => 'ortho_001', 'clinic_name' => 'Orthopedics', 'doctor_id' => 'doc_michael_ross', 'doctor_name' => 'Dr. Michael Ross', 'patient_id' => 'patient_olivia_davis', 'patient_name' => 'Olivia Davis', 'booking_date' => $today, 'booking_time' => '11:00', 'status' => 'waiting', 'wait_time' => 18, 'queue_number' => 'O001'],
    ['id' => 'book_006', 'clinic_id' => 'derma_001', 'clinic_name' => 'Dermatology', 'doctor_id' => 'doc_linda_kim', 'doctor_name' => 'Dr. Linda Kim', 'patient_id' => 'patient_william_moore', 'patient_name' => 'William Moore', 'booking_date' => $today, 'booking_time' => '11:30', 'status' => 'waiting', 'wait_time' => 5, 'queue_number' => 'D001'],

    // In progress bookings (2)
    ['id' => 'book_007', 'clinic_id' => 'peds_001', 'clinic_name' => 'Pediatrics', 'doctor_id' => 'doc_david_lee', 'doctor_name' => 'Dr. David Lee', 'patient_id' => 'patient_sophia_taylor', 'patient_name' => 'Sophia Taylor', 'booking_date' => $today, 'booking_time' => '09:00', 'status' => 'in_progress', 'wait_time' => 0, 'queue_number' => 'P002'],
    ['id' => 'book_008', 'clinic_id' => 'cardio_001', 'clinic_name' => 'Cardiology', 'doctor_id' => 'doc_maria_garcia', 'doctor_name' => 'Dr. Maria Garcia', 'patient_id' => 'patient_james_anderson', 'patient_name' => 'James Anderson', 'booking_date' => $today, 'booking_time' => '09:30', 'status' => 'in_progress', 'wait_time' => 0, 'queue_number' => 'C002'],

    // Completed bookings (5)
    ['id' => 'book_009', 'clinic_id' => 'gen_med_001', 'clinic_name' => 'General Medicine', 'doctor_id' => 'doc_sarah_miller', 'doctor_name' => 'Dr. Sarah Miller', 'patient_id' => 'patient_ava_thomas', 'patient_name' => 'Ava Thomas', 'booking_date' => $today, 'booking_time' => '08:00', 'status' => 'completed', 'wait_time' => 10, 'queue_number' => 'A003'],
    ['id' => 'book_010', 'clinic_id' => 'gen_med_001', 'clinic_name' => 'General Medicine', 'doctor_id' => 'doc_john_smith', 'doctor_name' => 'Dr. John Smith', 'patient_id' => 'patient_robert_jackson', 'patient_name' => 'Robert Jackson', 'booking_date' => $today, 'booking_time' => '08:30', 'status' => 'completed', 'wait_time' => 12, 'queue_number' => 'A004'],
    ['id' => 'book_011', 'clinic_id' => 'peds_001', 'clinic_name' => 'Pediatrics', 'doctor_id' => 'doc_emily_wong', 'doctor_name' => 'Dr. Emily Wong', 'patient_id' => 'patient_mia_white', 'patient_name' => 'Mia White', 'booking_date' => $today, 'booking_time' => '08:00', 'status' => 'completed', 'wait_time' => 8, 'queue_number' => 'P003'],
    ['id' => 'book_012', 'clinic_id' => 'cardio_001', 'clinic_name' => 'Cardiology', 'doctor_id' => 'doc_james_chen', 'doctor_name' => 'Dr. James Chen', 'patient_id' => 'patient_daniel_harris', 'patient_name' => 'Daniel Harris', 'booking_date' => $today, 'booking_time' => '08:00', 'status' => 'completed', 'wait_time' => 20, 'queue_number' => 'C003'],
    ['id' => 'book_013', 'clinic_id' => 'ortho_001', 'clinic_name' => 'Orthopedics', 'doctor_id' => 'doc_michael_ross', 'doctor_name' => 'Dr. Michael Ross', 'patient_id' => 'patient_sarah_jenkins', 'patient_name' => 'Sarah Jenkins', 'booking_date' => $today, 'booking_time' => '08:30', 'status' => 'completed', 'wait_time' => 15, 'queue_number' => 'O002'],

    // No show booking (1)
    ['id' => 'book_014', 'clinic_id' => 'derma_001', 'clinic_name' => 'Dermatology', 'doctor_id' => 'doc_robert_brown', 'doctor_name' => 'Dr. Robert Brown', 'patient_id' => 'patient_john_doe', 'patient_name' => 'John Doe', 'booking_date' => $today, 'booking_time' => '09:00', 'status' => 'no_show', 'wait_time' => 0, 'queue_number' => 'D002'],

    // Cancelled booking (1)
    ['id' => 'book_015', 'clinic_id' => 'gen_med_001', 'clinic_name' => 'General Medicine', 'doctor_id' => 'doc_sarah_miller', 'doctor_name' => 'Dr. Sarah Miller', 'patient_id' => 'patient_emma_wilson', 'patient_name' => 'Emma Wilson', 'booking_date' => $today, 'booking_time' => '10:00', 'status' => 'cancelled', 'wait_time' => 0, 'queue_number' => 'A005'],
];

foreach ($bookings as $booking) {
    $id = $booking['id'];
    unset($booking['id']);

    if (createDocument($projectId, $accessToken, 'bookings', $id, $booking)) {
        echo "  ✓ Booking: {$booking['patient_name']} ({$booking['status']})\n";
    } else {
        echo "  ✗ Failed: {$booking['patient_name']}\n";
    }
}

// Seed Alerts
echo "\n🔔 Seeding Alerts...\n";
$alerts = [
    ['id' => 'alert_001', 'message' => 'High patient volume in General Medicine', 'type' => 'info', 'clinic_id' => 'gen_med_001'],
    ['id' => 'alert_002', 'message' => 'Dr. Chen running 15 minutes behind schedule', 'type' => 'warning', 'clinic_id' => 'cardio_001'],
    ['id' => 'alert_003', 'message' => 'System maintenance scheduled for 2 AM', 'type' => 'info', 'clinic_id' => null],
];

foreach ($alerts as $alert) {
    $id = $alert['id'];
    unset($alert['id']);

    if (createDocument($projectId, $accessToken, 'alerts', $id, $alert)) {
        echo "  ✓ Alert: {$alert['message']}\n";
    } else {
        echo "  ✗ Failed: {$alert['message']}\n";
    }
}

// Seed Settings
echo "\n⚙️  Seeding Settings...\n";
$settings = [
    'hospital_name' => 'City General Hospital',
    'timezone' => 'Asia/Singapore',
    'queue_display_mode' => 'full',
    'auto_advance_queue' => true,
];

if (createDocument($projectId, $accessToken, 'settings', 'hospital_config', $settings)) {
    echo "  ✓ Settings: hospital_config\n";
} else {
    echo "  ✗ Failed: hospital_config\n";
}

// Seed Queue States
echo "\n📊 Seeding Queue States...\n";
$queueStates = [
    ['clinic_id' => 'gen_med_001', 'current_number' => 'A003', 'waiting_count' => 8, 'is_paused' => false],
    ['clinic_id' => 'peds_001', 'current_number' => 'P002', 'waiting_count' => 5, 'is_paused' => false],
    ['clinic_id' => 'cardio_001', 'current_number' => 'C002', 'waiting_count' => 10, 'is_paused' => false],
    ['clinic_id' => 'derma_001', 'current_number' => 'D001', 'waiting_count' => 3, 'is_paused' => false],
    ['clinic_id' => 'ortho_001', 'current_number' => 'O001', 'waiting_count' => 6, 'is_paused' => false],
];

foreach ($queueStates as $queueState) {
    $clinicId = $queueState['clinic_id'];

    if (createDocument($projectId, $accessToken, 'queue_states', $clinicId, $queueState)) {
        echo "  ✓ Queue State: {$clinicId}\n";
    } else {
        echo "  ✗ Failed: {$clinicId}\n";
    }
}

echo "\n✅ Firebase seeding completed successfully!\n";
echo "\n📊 Summary:\n";
echo " - 5 Clinics\n";
echo " - 10 Doctors (as subcollections)\n";
echo " - 12 Patients\n";
echo " - 15 Bookings (6 waiting, 2 in_progress, 5 completed, 1 no_show, 1 cancelled)\n";
echo " - 5 Queue States\n";
echo " - 3 Alerts\n";
echo " - 1 Settings Document\n";
echo "\n🎉 Your dashboard should now display real Firebase data!\n";
