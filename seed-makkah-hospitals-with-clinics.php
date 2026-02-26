#!/usr/bin/env php
<?php
/**
 * Makkah Hospitals + Clinics Firebase Seeder
 *
 * Structure:
 *   hospitals/{hospital_id}         → Each Makkah facility as a hospital (status: waiting)
 *   clinics/{clinic_id}             → Department clinics under each hospital (hospital_id link)
 */

$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
if (!file_exists($credentialsPath)) {
    die("Error: Firebase credentials not found at {$credentialsPath}\n");
}

$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

$clinicsDataPath = __DIR__ . '/makkah_clinics_final.json';
if (!file_exists($clinicsDataPath)) {
    die("Error: Clinic data not found at {$clinicsDataPath}\n");
}

$clinicsData = json_decode(file_get_contents($clinicsDataPath), true);

echo "=== Makkah Hospitals + Clinics Seeder ===\n";
echo "Project: {$projectId}\n";
echo "Loaded: " . count($clinicsData) . " facilities\n\n";

// ── Auth Functions ──

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function getAccessToken($credentials) {
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
    $jwt = $signatureInput . '.' . base64url_encode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['access_token'] ?? null;
}

function firestoreCreate($projectId, $accessToken, $collection, $documentId, $fields) {
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
    return ['success' => $httpCode >= 200 && $httpCode < 300, 'code' => $httpCode];
}

function firestoreDelete($projectId, $accessToken, $path) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$path}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

// ── Helper Functions ──

function makeId($name, $index) {
    $clean = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    $clean = preg_replace('/_+/', '_', trim($clean, '_'));
    if (empty($clean) || strlen($clean) < 3) $clean = 'makkah';
    return 'mkh_' . substr(strtolower($clean), 0, 35) . '_' . str_pad($index, 4, '0', STR_PAD_LEFT);
}

function detectSpecialty($name, $types) {
    $text = mb_strtolower($name . ' ' . $types);
    $map = [
        ['/دنت|أسنان|اسنان|dental|dentist|orthodont|تقويم/', 'طب الأسنان', 'Dentistry'],
        ['/عيون|ophthalmol|eye|بصر|vision/', 'طب العيون', 'Ophthalmology'],
        ['/جلد|derma|skin|تجميل|cosmetic|ليزر/', 'الأمراض الجلدية', 'Dermatology'],
        ['/قلب|cardio/', 'أمراض القلب', 'Cardiology'],
        ['/عظام|orthop/', 'جراحة العظام', 'Orthopedics'],
        ['/أطفال|اطفال|pediatr/', 'طب الأطفال', 'Pediatrics'],
        ['/أنف|انف|حنجر|ent/', 'أنف وأذن وحنجرة', 'ENT'],
        ['/علاج طبيعي|physiother|تأهيل/', 'العلاج الطبيعي', 'Physiotherapy'],
        ['/نساء|ولاد|gynec|obstet/', 'نساء وولادة', 'OB/GYN'],
        ['/باطن|internal|سكر|غدد/', 'الطب الباطني', 'Internal Medicine'],
    ];
    foreach ($map as [$pattern, $ar, $en]) {
        if (preg_match($pattern, $text)) return ['ar' => $ar, 'en' => $en];
    }
    return ['ar' => 'طب عام', 'en' => 'General Medicine'];
}

function workingHoursFirestore() {
    $days = ['sunday','monday','tuesday','wednesday','thursday','saturday'];
    $mapFields = [];
    foreach ($days as $day) {
        $mapFields[$day] = ['mapValue' => ['fields' => [
            'open' => ['stringValue' => '08:00'],
            'close' => ['stringValue' => '16:00'],
            'enabled' => ['booleanValue' => true],
        ]]];
    }
    $mapFields['friday'] = ['mapValue' => ['fields' => [
        'open' => ['stringValue' => '00:00'],
        'close' => ['stringValue' => '00:00'],
        'enabled' => ['booleanValue' => false],
    ]]];
    return ['mapValue' => ['fields' => $mapFields]];
}

// Default clinic departments to create under each hospital
function getDefaultClinics() {
    return [
        ['key' => 'general', 'name' => 'الطب العام', 'name_en' => 'General Medicine', 'specialty' => 'General Medicine', 'capacity' => 50],
    ];
}

// ── Main ──

echo "Getting access token...\n";
$accessToken = getAccessToken($credentials);
if (!$accessToken) die("Failed to get access token\n");
echo "Token obtained.\n\n";

// Step 1: Delete old mkh_ clinics (from previous upload)
echo "--- Step 1: Cleaning old mkh_ clinic documents ---\n";
$deleteCount = 0;
foreach ($clinicsData as $index => $c) {
    $name = $c['name'] ?? '';
    if (empty($name)) continue;
    $oldDocId = makeId($name, $index + 1);
    firestoreDelete($projectId, $accessToken, "clinics/{$oldDocId}");
    $deleteCount++;
}
echo "Deleted {$deleteCount} old clinic documents.\n\n";

// Step 2: Create hospitals + clinics
echo "--- Step 2: Creating Hospitals + Clinics ---\n";
$hospitalCount = 0;
$clinicCount = 0;
$failCount = 0;
$now = date('Y-m-d\TH:i:s\Z');

foreach ($clinicsData as $index => $facility) {
    $name = $facility['name'] ?? '';
    if (empty($name)) continue;

    $num = $index + 1;
    $hospitalId = makeId($name, $num);
    $lat = floatval($facility['latitude'] ?? 21.4225);
    $lng = floatval($facility['longitude'] ?? 39.8262);
    $specialty = detectSpecialty($name, $facility['types'] ?? '');

    // ── Create Hospital Document ──
    $hospitalFields = [
        'name' => ['stringValue' => $name],
        'name_en' => ['stringValue' => $name],
        'address' => ['stringValue' => $facility['address'] ?? ''],
        'phone' => ['stringValue' => $facility['phone'] ?? ''],
        'international_phone' => ['stringValue' => $facility['international_phone'] ?? ''],
        'website' => ['stringValue' => $facility['website'] ?? ''],
        'google_maps_url' => ['stringValue' => $facility['google_maps_url'] ?? ''],
        'google_place_id' => ['stringValue' => $facility['place_id'] ?? ''],
        'rating' => ['doubleValue' => floatval($facility['rating'] ?? 0)],
        'total_ratings' => ['integerValue' => (string)intval($facility['total_ratings'] ?? 0)],
        'business_status' => ['stringValue' => $facility['business_status'] ?? ''],
        'city' => ['stringValue' => 'Makkah'],
        'city_ar' => ['stringValue' => 'مكة المكرمة'],
        'region' => ['stringValue' => 'Makkah Region'],
        'country' => ['stringValue' => 'Saudi Arabia'],
        'location' => ['geoPointValue' => ['latitude' => $lat, 'longitude' => $lng]],
        'type' => ['stringValue' => $specialty['en']],
        'type_ar' => ['stringValue' => $specialty['ar']],
        'status' => ['stringValue' => 'waiting'],
        'source' => ['stringValue' => 'google_maps'],
        'created_at' => ['stringValue' => $now],
        'updated_at' => ['stringValue' => $now],
    ];

    echo "  [{$num}/" . count($clinicsData) . "] Hospital: {$name}";
    $result = firestoreCreate($projectId, $accessToken, 'hospitals', $hospitalId, $hospitalFields);

    if (!$result['success']) {
        echo " -> FAILED (HTTP {$result['code']})\n";
        $failCount++;
        if ($result['code'] == 401) {
            $accessToken = getAccessToken($credentials);
            $result = firestoreCreate($projectId, $accessToken, 'hospitals', $hospitalId, $hospitalFields);
            if ($result['success']) { $failCount--; echo "  Retry -> OK\n"; }
        }
        continue;
    }
    $hospitalCount++;
    echo " -> OK";

    // ── Create Default Clinic(s) Under This Hospital ──
    $defaultClinics = getDefaultClinics();
    foreach ($defaultClinics as $ci => $dc) {
        $clinicId = $hospitalId . '_' . $dc['key'];
        $clinicFields = [
            'hospital_id' => ['stringValue' => $hospitalId],
            'name' => ['stringValue' => $dc['name']],
            'name_en' => ['stringValue' => $dc['name_en']],
            'specialty' => ['stringValue' => $dc['specialty']],
            'address' => ['stringValue' => $facility['address'] ?? ''],
            'location' => ['geoPointValue' => ['latitude' => $lat, 'longitude' => $lng]],
            'geofence_radius_meters' => ['integerValue' => '100'],
            'admin_ids' => ['arrayValue' => ['values' => []]],
            'working_hours' => workingHoursFirestore(),
            'daily_capacity' => ['integerValue' => (string)$dc['capacity']],
            'status' => ['stringValue' => 'waiting'],
            'created_at' => ['stringValue' => $now],
        ];

        $cr = firestoreCreate($projectId, $accessToken, 'clinics', $clinicId, $clinicFields);
        if ($cr['success']) {
            $clinicCount++;
        }
    }
    echo " + {$clinicCount} clinic(s)\n";

    // Rate limiting
    if ($num % 100 === 0) usleep(500000);
}

echo "\n============================================\n";
echo "  UPLOAD COMPLETE\n";
echo "============================================\n";
echo "  Hospitals created: {$hospitalCount}\n";
echo "  Clinics created:   {$clinicCount}\n";
echo "  Failed:            {$failCount}\n";
echo "  Status:            ALL set to 'waiting'\n";
echo "============================================\n";
