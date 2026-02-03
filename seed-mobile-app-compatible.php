#!/usr/bin/env php
<?php
/**
 * Mobile App Compatible Seeder
 * Seeds data with IDs that match what the mobile app expects
 */

$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
if (!file_exists($credentialsPath)) {
    die("Error: Firebase credentials not found\n");
}

$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

echo "🔥 Mobile App Compatible Seeder\n";
echo "📍 Project: {$projectId}\n\n";

// Auth functions (same as before)
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
            if (array_keys($value) === range(0, count($value) - 1)) {
                $arrayValues = [];
                foreach ($value as $item) {
                    if (is_string($item)) {
                        $arrayValues[] = ['stringValue' => $item];
                    }
                }
                $fields[$key] = ['arrayValue' => ['values' => $arrayValues]];
            } else {
                $fields[$key] = ['mapValue' => ['fields' => convertToFirestoreFields($value)]];
            }
        }
    }
    return $fields;
}

$accessToken = getAccessToken($credentials);
if (!$accessToken) {
    die("❌ Failed to get access token\n");
}
echo "✅ Access token obtained\n\n";

$now = date('Y-m-d\TH:i:s\Z');

// Seed clinics with mobile app IDs
echo "🏥 Seeding Clinics (mobile app compatible IDs)...\n";

$clinics = [
    [
        'id' => 'cardiology',
        'name' => 'أمراض القلب',
        'name_en' => 'Cardiology',
        'specialty' => 'Cardiology',
        'location' => ['latitude' => 24.7140, 'longitude' => 46.6750],
        'address' => 'Building C, Floor 3',
        'created_at' => $now
    ],
    [
        'id' => 'pediatrics',
        'name' => 'طب الأطفال',
        'name_en' => 'Pediatrics',
        'specialty' => 'Pediatrics',
        'location' => ['latitude' => 24.7138, 'longitude' => 46.6755],
        'address' => 'Building B, Floor 1',
        'created_at' => $now
    ],
];

foreach ($clinics as $clinic) {
    $id = $clinic['id'];
    unset($clinic['id']);
    $success = createDocument($projectId, $accessToken, 'clinics', $id, $clinic);
    echo $success ? "  ✓ {$clinic['name_en']}\n" : "  ✗ Failed: {$clinic['name_en']}\n";
}
echo "\n";

// Seed doctors as subcollections
echo "👨‍⚕️ Seeding Doctors (mobile app compatible IDs)...\n";

$doctors = [
    [
        'clinic' => 'cardiology',
        'id' => 'doc_001',
        'name' => 'د. جيمس تشين',
        'name_en' => 'Dr. James Chen',
        'specialty' => 'Cardiology',
        'rating' => 4.7,
        'review_count' => 89,
        'created_at' => $now
    ],
    [
        'clinic' => 'cardiology',
        'id' => 'doc_002',
        'name' => 'د. ليندا كيم',
        'name_en' => 'Dr. Linda Kim',
        'specialty' => 'Cardiology',
        'rating' => 4.9,
        'review_count' => 156,
        'created_at' => $now
    ],
    [
        'clinic' => 'pediatrics',
        'id' => 'doc_003',
        'name' => 'د. إميلي وونغ',
        'name_en' => 'Dr. Emily Wong',
        'specialty' => 'Pediatrics',
        'rating' => 4.8,
        'review_count' => 203,
        'created_at' => $now
    ],
];

foreach ($doctors as $doctor) {
    $clinicId = $doctor['clinic'];
    $doctorId = $doctor['id'];
    unset($doctor['clinic'], $doctor['id']);

    $path = "clinics/{$clinicId}/doctors";
    $success = createDocument($projectId, $accessToken, $path, $doctorId, $doctor);
    echo $success ? "  ✓ {$doctor['name_en']} in {$clinicId}\n" : "  ✗ Failed: {$doctor['name_en']}\n";
}

echo "\n✅ Mobile app compatible data seeded!\n";
echo "\n📱 Mobile app should now be able to:\n";
echo "   - View cardiology and pediatrics clinics\n";
echo "   - View doctors doc_001, doc_002, doc_003 with Arabic names\n";
echo "   - Create bookings that show correctly on dashboard\n";
