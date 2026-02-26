#!/usr/bin/env php
<?php
/**
 * Makkah Clinics Firebase Seeder
 * Uploads 643 clinics from Google Maps to Firestore with status "waiting"
 */

// Load Firebase credentials
$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
if (!file_exists($credentialsPath)) {
    die("Error: Firebase credentials not found at {$credentialsPath}\n");
}

$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

echo "=== Makkah Clinics Firebase Seeder ===\n";
echo "Project: {$projectId}\n\n";

// Load clinic data
$clinicsDataPath = __DIR__ . '/makkah_clinics_final.json';
if (!file_exists($clinicsDataPath)) {
    die("Error: Clinic data not found at {$clinicsDataPath}\n");
}

$clinicsData = json_decode(file_get_contents($clinicsDataPath), true);
echo "Loaded " . count($clinicsData) . " clinics from JSON\n\n";

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
    $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claimSet = [
        'iss' => $credentials['client_email'],
        'scope' => 'https://www.googleapis.com/auth/datastore',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ];
    $payload = base64url_encode(json_encode($claimSet));
    $signatureInput = $header . '.' . $payload;
    openssl_sign($signatureInput, $signature, $credentials['private_key'], 'SHA256');
    $signature = base64url_encode($signature);
    return $signatureInput . '.' . $signature;
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function createDocument($projectId, $accessToken, $collection, $documentId, $fields) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}?documentId={$documentId}";

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

    return ['success' => $httpCode >= 200 && $httpCode < 300, 'code' => $httpCode, 'response' => $response];
}

function detectSpecialty($name, $types) {
    $name_lower = mb_strtolower($name);
    $types_lower = mb_strtolower($types);

    // Dental
    if (preg_match('/دنت|أسنان|اسنان|dental|dentist|orthodont|تقويم/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'طب الأسنان', 'en' => 'Dentistry', 'key' => 'dentistry'];
    }
    // Eye/Ophthalmology
    if (preg_match('/عيون|ophthalmol|eye|بصر|نظار|optical|vision/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'طب العيون', 'en' => 'Ophthalmology', 'key' => 'ophthalmology'];
    }
    // Dermatology
    if (preg_match('/جلد|derma|skin|تجميل|cosmetic|ليزر|laser/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'الأمراض الجلدية', 'en' => 'Dermatology', 'key' => 'dermatology'];
    }
    // Cardiology
    if (preg_match('/قلب|cardio|heart/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'أمراض القلب', 'en' => 'Cardiology', 'key' => 'cardiology'];
    }
    // Orthopedics
    if (preg_match('/عظام|orthop|bone|عمود/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'جراحة العظام', 'en' => 'Orthopedics', 'key' => 'orthopedics'];
    }
    // Pediatrics
    if (preg_match('/أطفال|اطفال|pediatr|child/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'طب الأطفال', 'en' => 'Pediatrics', 'key' => 'pediatrics'];
    }
    // ENT
    if (preg_match('/أنف|انف|حنجر|ent|ear|nose|throat/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'أنف وأذن وحنجرة', 'en' => 'ENT', 'key' => 'ent'];
    }
    // Physiotherapy
    if (preg_match('/علاج طبيعي|physiother|physical therapy|تأهيل/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'العلاج الطبيعي', 'en' => 'Physiotherapy', 'key' => 'physiotherapy'];
    }
    // OB/GYN
    if (preg_match('/نساء|ولاد|gynec|obstet/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'نساء وولادة', 'en' => 'OB/GYN', 'key' => 'obgyn'];
    }
    // Internal Medicine
    if (preg_match('/باطن|internal|سكر|غدد/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'الطب الباطني', 'en' => 'Internal Medicine', 'key' => 'internal_medicine'];
    }
    // Pharmacy
    if (preg_match('/صيدل|pharmacy|pharma/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'صيدلية', 'en' => 'Pharmacy', 'key' => 'pharmacy'];
    }
    // Lab
    if (preg_match('/مختبر|lab|تشخيص|diagnostic/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'مختبرات', 'en' => 'Laboratory', 'key' => 'laboratory'];
    }
    // Hospital
    if (preg_match('/مستشفى|hospital/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'مستشفى', 'en' => 'Hospital', 'key' => 'hospital'];
    }
    // General/Polyclinic
    if (preg_match('/مجمع|مستوصف|polyclinic|complex|general/', $name_lower . ' ' . $types_lower)) {
        return ['ar' => 'طب عام', 'en' => 'General Medicine', 'key' => 'general'];
    }
    // Default
    return ['ar' => 'عيادة', 'en' => 'Clinic', 'key' => 'clinic'];
}

function sanitizeDocId($name, $index) {
    // Create a clean document ID from name
    $clean = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    $clean = preg_replace('/_+/', '_', $clean);
    $clean = trim($clean, '_');
    if (empty($clean) || strlen($clean) < 3) {
        $clean = 'makkah_clinic';
    }
    return 'mkh_' . substr(strtolower($clean), 0, 40) . '_' . str_pad($index, 4, '0', STR_PAD_LEFT);
}

// Parse opening hours string to structured format
function parseOpeningHours($hoursStr) {
    $defaultHours = [
        'sunday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
        'monday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
        'tuesday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
        'wednesday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
        'thursday' => ['open' => '08:00', 'close' => '16:00', 'enabled' => true],
        'friday' => ['open' => '00:00', 'close' => '00:00', 'enabled' => false],
        'saturday' => ['open' => '09:00', 'close' => '13:00', 'enabled' => true],
    ];

    if (empty($hoursStr)) {
        return $defaultHours;
    }

    return $defaultHours;
}

// Convert working hours to Firestore map format
function workingHoursToFirestore($hours) {
    $mapFields = [];
    foreach ($hours as $day => $times) {
        $dayFields = [];
        $dayFields['open'] = ['stringValue' => $times['open']];
        $dayFields['close'] = ['stringValue' => $times['close']];
        $dayFields['enabled'] = ['booleanValue' => $times['enabled']];
        $mapFields[$day] = ['mapValue' => ['fields' => $dayFields]];
    }
    return ['mapValue' => ['fields' => $mapFields]];
}

// Get access token
echo "Getting access token...\n";
$accessToken = getAccessToken($credentials);
if (!$accessToken) {
    die("Failed to get access token. Check your credentials.\n");
}
echo "Access token obtained.\n\n";

// Upload clinics
echo "--- Uploading " . count($clinicsData) . " clinics to Firestore ---\n";
$successCount = 0;
$failCount = 0;
$skipCount = 0;

foreach ($clinicsData as $index => $clinic) {
    $name = $clinic['name'] ?? '';
    $placeId = $clinic['place_id'] ?? '';

    // Skip entries with no name
    if (empty($name)) {
        $skipCount++;
        continue;
    }

    // Create document ID
    $docId = sanitizeDocId($name, $index + 1);

    // Detect specialty
    $specialty = detectSpecialty($name, $clinic['types'] ?? '');

    // Parse working hours
    $workingHours = parseOpeningHours($clinic['opening_hours'] ?? '');

    // Get coordinates
    $lat = floatval($clinic['latitude'] ?? 21.4225);
    $lng = floatval($clinic['longitude'] ?? 39.8262);

    // Build Firestore fields
    $fields = [];

    // Core fields matching your existing schema
    $fields['name'] = ['stringValue' => $name];
    $fields['name_en'] = ['stringValue' => $name]; // Same for now since Maps returns mixed
    $fields['specialty'] = ['stringValue' => $specialty['en']];
    $fields['specialty_ar'] = ['stringValue' => $specialty['ar']];
    $fields['specialty_key'] = ['stringValue' => $specialty['key']];
    $fields['address'] = ['stringValue' => $clinic['address'] ?? ''];
    $fields['phone'] = ['stringValue' => $clinic['phone'] ?? ''];
    $fields['international_phone'] = ['stringValue' => $clinic['international_phone'] ?? ''];
    $fields['website'] = ['stringValue' => $clinic['website'] ?? ''];
    $fields['google_maps_url'] = ['stringValue' => $clinic['google_maps_url'] ?? ''];
    $fields['google_place_id'] = ['stringValue' => $placeId];
    $fields['rating'] = ['doubleValue' => floatval($clinic['rating'] ?? 0)];
    $fields['total_ratings'] = ['integerValue' => (string)intval($clinic['total_ratings'] ?? 0)];
    $fields['business_status'] = ['stringValue' => $clinic['business_status'] ?? ''];
    $fields['city'] = ['stringValue' => 'Makkah'];
    $fields['city_ar'] = ['stringValue' => 'مكة المكرمة'];
    $fields['region'] = ['stringValue' => 'Makkah Region'];
    $fields['country'] = ['stringValue' => 'Saudi Arabia'];

    // Location as GeoPoint
    $fields['location'] = [
        'geoPointValue' => [
            'latitude' => $lat,
            'longitude' => $lng
        ]
    ];

    $fields['geofence_radius_meters'] = ['integerValue' => '100'];
    $fields['admin_ids'] = ['arrayValue' => ['values' => []]];

    // Working hours
    $fields['working_hours'] = workingHoursToFirestore($workingHours);

    $fields['daily_capacity'] = ['integerValue' => '50'];

    // STATUS = "waiting" as requested
    $fields['status'] = ['stringValue' => 'waiting'];

    $fields['source'] = ['stringValue' => 'google_maps'];
    $fields['created_at'] = ['stringValue' => date('Y-m-d\TH:i:s\Z')];
    $fields['updated_at'] = ['stringValue' => date('Y-m-d\TH:i:s\Z')];

    // Upload to Firestore
    $num = $index + 1;
    echo "  [{$num}/" . count($clinicsData) . "] {$name}";
    $result = createDocument($projectId, $accessToken, 'clinics', $docId, $fields);

    if ($result['success']) {
        $successCount++;
        echo " -> OK\n";
    } else {
        $failCount++;
        echo " -> FAILED (HTTP {$result['code']})\n";
        // If token expired, refresh it
        if ($result['code'] == 401) {
            echo "  Token expired, refreshing...\n";
            $accessToken = getAccessToken($credentials);
            if ($accessToken) {
                // Retry
                $result = createDocument($projectId, $accessToken, 'clinics', $docId, $fields);
                if ($result['success']) {
                    $successCount++;
                    $failCount--;
                    echo "  Retry -> OK\n";
                }
            }
        }
    }

    // Small delay to avoid rate limiting
    if ($num % 50 === 0) {
        usleep(500000); // 0.5s every 50 records
    }
}

echo "\n============================================\n";
echo "  UPLOAD COMPLETE\n";
echo "============================================\n";
echo "  Uploaded: {$successCount}\n";
echo "  Failed:   {$failCount}\n";
echo "  Skipped:  {$skipCount}\n";
echo "  Status:   ALL set to 'waiting'\n";
echo "  Collection: clinics\n";
echo "============================================\n";
