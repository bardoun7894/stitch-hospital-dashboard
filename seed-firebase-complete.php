<?php
#!/usr/bin/env php
/**
 * Complete Firebase/Firestore Seeder for Mobile App
 * Seeds ALL fields required by the Flutter mobile app
 */

// Load Firebase credentials
$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
if (!file_exists($credentialsPath)) {
    die("Error: Firebase credentials not found at {$credentialsPath}\n");
}

$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

echo "🔥 Starting Complete Firebase Seeder\n";
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
                    }
                }
                $fields[$key] = ['arrayValue' => ['values' => $arrayValues]];
            } else {
                // It's a map (nested object)
                $mapFields = [];
                foreach ($value as $subKey => $subValue) {
                    if (is_string($subValue)) {
                        $mapFields[$subKey] = ['stringValue' => $subValue];
                    } elseif (is_bool($subValue)) {
                        $mapFields[$subKey] = ['booleanValue' => $subValue];
                    } elseif (is_int($subValue)) {
                        $mapFields[$subKey] = ['integerValue' => (string)$subValue];
                    }
                }
                $fields[$key] = ['mapValue' => ['fields' => $mapFields]];
            }
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

// Hospital data
$hospitalId = 'hospital_001';

// Seed Clinics with FULL mobile app fields
echo "🏥 Seeding Clinics (with full mobile app fields)...\n";
$clinics = [
    [
        'id' => 'gen_med_001',
        'hospital_id' => $hospitalId,
        'name' => 'الطب العام',
        'name_en' => 'General Medicine',
        'specialty' => 'General Medicine',
        'address' => 'Building A, Floor 2, City General Hospital',
        'location' => ['latitude' => 24.7136, 'longitude' => 46.6753], // Riyadh
        'geofence_radius_meters' => 100,
        'admin_ids' => [],
        'working_hours' => [
            'sunday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'monday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'tuesday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'wednesday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'thursday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'friday' => ['open' => '00:00', 'close' => '00:00', 'enabled' => false],
            'saturday' => ['open' => '09:00', 'close' => '13:00', 'enabled' => true],
        ],
        'daily_capacity' => 50,
        'status' => 'active',
    ],
    [
        'id' => 'peds_001',
        'hospital_id' => $hospitalId,
        'name' => 'طب الأطفال',
        'name_en' => 'Pediatrics',
        'specialty' => 'Pediatrics',
        'address' => 'Building B, Floor 1, City General Hospital',
        'location' => ['latitude' => 24.7138, 'longitude' => 46.6755],
        'geofence_radius_meters' => 100,
        'admin_ids' => [],
        'working_hours' => [
            'sunday' => ['open' => '08:00', 'close' => '18:00', 'enabled' => true],
            'monday' => ['open' => '08:00', 'close' => '18:00', 'enabled' => true],
            'tuesday' => ['open' => '08:00', 'close' => '18:00', 'enabled' => true],
            'wednesday' => ['open' => '08:00', 'close' => '18:00', 'enabled' => true],
            'thursday' => ['open' => '08:00', 'close' => '18:00', 'enabled' => true],
            'friday' => ['open' => '00:00', 'close' => '00:00', 'enabled' => false],
            'saturday' => ['open' => '09:00', 'close' => '13:00', 'enabled' => true],
        ],
        'daily_capacity' => 60,
        'status' => 'active',
    ],
    [
        'id' => 'cardio_001',
        'hospital_id' => $hospitalId,
        'name' => 'أمراض القلب',
        'name_en' => 'Cardiology',
        'specialty' => 'Cardiology',
        'address' => 'Building C, Floor 3, City General Hospital',
        'location' => ['latitude' => 24.7140, 'longitude' => 46.6750],
        'geofence_radius_meters' => 100,
        'admin_ids' => [],
        'working_hours' => [
            'sunday' => ['open' => '08:00', 'close' => '17:00', 'enabled' => true],
            'monday' => ['open' => '08:00', 'close' => '17:00', 'enabled' => true],
            'tuesday' => ['open' => '08:00', 'close' => '17:00', 'enabled' => true],
            'wednesday' => ['open' => '08:00', 'close' => '17:00', 'enabled' => true],
            'thursday' => ['open' => '08:00', 'close' => '17:00', 'enabled' => true],
            'friday' => ['open' => '00:00', 'close' => '00:00', 'enabled' => false],
            'saturday' => ['open' => '09:00', 'close' => '13:00', 'enabled' => true],
        ],
        'daily_capacity' => 40,
        'status' => 'active',
    ],
    [
        'id' => 'derma_001',
        'hospital_id' => $hospitalId,
        'name' => 'الأمراض الجلدية',
        'name_en' => 'Dermatology',
        'specialty' => 'Dermatology',
        'address' => 'Building A, Floor 3, City General Hospital',
        'location' => ['latitude' => 24.7135, 'longitude' => 46.6752],
        'geofence_radius_meters' => 100,
        'admin_ids' => [],
        'working_hours' => [
            'sunday' => ['open' => '09:00', 'close' => '15:00', 'enabled' => true],
            'monday' => ['open' => '09:00', 'close' => '15:00', 'enabled' => true],
            'tuesday' => ['open' => '09:00', 'close' => '15:00', 'enabled' => true],
            'wednesday' => ['open' => '09:00', 'close' => '15:00', 'enabled' => true],
            'thursday' => ['open' => '09:00', 'close' => '15:00', 'enabled' => true],
            'friday' => ['open' => '00:00', 'close' => '00:00', 'enabled' => false],
            'saturday' => ['open' => '09:00', 'close' => '13:00', 'enabled' => true],
        ],
        'daily_capacity' => 30,
        'status' => 'active',
    ],
    [
        'id' => 'ortho_001',
        'hospital_id' => $hospitalId,
        'name' => 'جراحة العظام',
        'name_en' => 'Orthopedics',
        'specialty' => 'Orthopedics',
        'address' => 'Building B, Floor 2, City General Hospital',
        'location' => ['latitude' => 24.7137, 'longitude' => 46.6754],
        'geofence_radius_meters' => 100,
        'admin_ids' => [],
        'working_hours' => [
            'sunday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'monday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'tuesday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'wednesday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'thursday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
            'friday' => ['open' => '00:00', 'close' => '00:00', 'enabled' => false],
            'saturday' => ['open' => '09:00', 'close' => '13:00', 'enabled' => true],
        ],
        'daily_capacity' => 45,
        'status' => 'active',
    ],
];

foreach ($clinics as $clinic) {
    $id = $clinic['id'];
    // Convert location to GeoPoint format for Firestore
    $lat = $clinic['location']['latitude'];
    $lng = $clinic['location']['longitude'];
    unset($clinic['id'], $clinic['location']);

    // Add created_at
    $clinic['created_at'] = date('Y-m-d\TH:i:s\Z');

    // Note: GeoPoint needs special handling
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/clinics?documentId={$id}";

    $fields = [];
    foreach ($clinic as $key => $value) {
        if ($key === 'location') continue;

        if (is_string($value)) {
            $fields[$key] = ['stringValue' => $value];
        } elseif (is_int($value)) {
            $fields[$key] = ['integerValue' => (string)$value];
        } elseif (is_bool($value)) {
            $fields[$key] = ['booleanValue' => $value];
        } elseif (is_array($value)) {
            if (isset($value['open'])) {
                continue; // Skip for now, will be added as map
            }
            if (array_keys($value) === range(0, count($value) - 1)) {
                $fields[$key] = ['arrayValue' => ['values' => []]];
            } else {
                $mapFields = [];
                foreach ($value as $subKey => $subValue) {
                    $subMapFields = [];
                    foreach ($subValue as $k => $v) {
                        if (is_string($v)) {
                            $subMapFields[$k] = ['stringValue' => $v];
                        } elseif (is_bool($v)) {
                            $subMapFields[$k] = ['booleanValue' => $v];
                        }
                    }
                    $mapFields[$subKey] = ['mapValue' => ['fields' => $subMapFields]];
                }
                $fields[$key] = ['mapValue' => ['fields' => $mapFields]];
            }
        }
    }

    // Add GeoPoint
    $fields['location'] = [
        'geoPointValue' => [
            'latitude' => $lat,
            'longitude' => $lng
        ]
    ];

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

    if ($httpCode >= 200 && $httpCode < 300) {
        echo "  ✓ Clinic: {$clinic['name_en']}\n";
    } else {
        echo "  ✗ Failed: {$clinic['name_en']}\n";
    }
}

echo "\n✅ Complete seeding finished!\n";
echo "\n📊 Summary:\n";
echo " - 5 Clinics (with GeoPoint, working hours, etc.)\n";
echo "\n🎉 Mobile app data structure ready!\n";
