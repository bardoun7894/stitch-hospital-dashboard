#!/usr/bin/env php
<?php
/**
 * Cleanup: Delete non-medical/non-clinic entries from Firestore
 * Keeps only hospitals, clinics, polyclinics, medical centers, and health centers
 */

$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

echo "=== Cleanup: Remove Non-Medical Entries ===\n";
echo "Project: {$projectId}\n\n";

// Load clinic data to identify which ones to remove
$clinicsData = json_decode(file_get_contents(__DIR__ . '/makkah_clinics_final.json'), true);

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
    $signature = base64url_encode($signature);
    $jwt = $signatureInput . '.' . $signature;

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

function deleteDocument($projectId, $accessToken, $documentPath) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$documentPath}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

function sanitizeDocId($name, $index) {
    $clean = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    $clean = preg_replace('/_+/', '_', $clean);
    $clean = trim($clean, '_');
    if (empty($clean) || strlen($clean) < 3) {
        $clean = 'makkah_clinic';
    }
    return 'mkh_' . substr(strtolower($clean), 0, 40) . '_' . str_pad($index, 4, '0', STR_PAD_LEFT);
}

function isMedicalFacility($name, $types) {
    $combined = mb_strtolower($name . ' ' . $types);

    // KEEP: These are clearly medical
    $keepPatterns = [
        // Hospitals
        'hospital', 'مستشفى', 'مستشفي',
        // Clinics
        'clinic', 'عيادة', 'عيادات', 'عياده',
        // Polyclinics / dispensaries
        'polyclinic', 'مستوصف', 'مجمع طبي', 'مجمع.*طبي', 'medical complex', 'medical center',
        // Health centers
        'health center', 'health centre', 'مركز صحي', 'صحية', 'primary care',
        // Doctors
        'doctor', 'dr\.', 'دكتور', 'دكتوره', 'دكتورة', 'استشاري', 'اخصائي', 'أخصائي',
        // Medical specialties
        'dental', 'أسنان', 'اسنان', 'dentist', 'orthodont', 'تقويم',
        'derma', 'جلد', 'جلدية', 'skin',
        'eye', 'عيون', 'ophthalmol', 'بصر',
        'cardio', 'قلب',
        'ortho', 'عظام',
        'pediatr', 'أطفال', 'اطفال',
        'gynec', 'نساء', 'ولاد', 'obstet',
        'ent', 'أنف', 'انف', 'حنجر',
        'physio', 'علاج طبيعي', 'تأهيل',
        'psycho', 'نفس', 'psychiatric',
        'urolog', 'مسالك',
        'surger', 'جراح',
        'oncol', 'أورام', 'اورام',
        'nephrol', 'كلى', 'غسيل',
        'internal', 'باطن',
        'radiol', 'أشعة', 'اشعة', 'رنين',
        // Medical facilities
        'dispensary', 'pharmacy', 'صيدلية', 'صيدليات',
        'lab', 'مختبر', 'تشخيص', 'diagnostic',
        'emergency', 'طوارئ', 'إسعاف', 'اسعاف',
        'ambulance', 'هلال أحمر', 'هلال الأحمر', 'red crescent',
        // Cupping / alternative
        'حجامة', 'cupping',
        // Medical keywords
        'طبي', 'طبية', 'طبيه', 'medical', 'healthcare', 'health care',
        'nursing', 'تمريض',
        'تطعيم', 'vaccine',
        'dialysis',
        'rehabilitation',
        'سمعيات', 'hearing',
        // Keep specific types
        'hospital', 'doctor', 'dentist', 'physiotherapist', 'health',
    ];

    foreach ($keepPatterns as $pattern) {
        if (mb_strpos($combined, $pattern) !== false) {
            return true;
        }
        // Try regex for patterns with .*
        if (strpos($pattern, '.*') !== false) {
            if (preg_match('/' . $pattern . '/u', $combined)) {
                return true;
            }
        }
    }

    return false;
}

// Identify entries to delete
$toDelete = [];
$toKeep = [];

foreach ($clinicsData as $index => $clinic) {
    $name = $clinic['name'] ?? '';
    $types = $clinic['types'] ?? '';

    if (empty($name)) continue;

    $docId = sanitizeDocId($name, $index + 1);

    if (isMedicalFacility($name, $types)) {
        $toKeep[] = ['name' => $name, 'docId' => $docId];
    } else {
        $toDelete[] = ['name' => $name, 'docId' => $docId];
    }
}

echo "Analysis:\n";
echo "  Total entries: " . count($clinicsData) . "\n";
echo "  Medical (KEEP): " . count($toKeep) . "\n";
echo "  Non-medical (DELETE): " . count($toDelete) . "\n\n";

// Show what will be deleted
echo "--- Entries to be DELETED ---\n";
foreach ($toDelete as $i => $item) {
    echo "  " . ($i + 1) . ". {$item['name']}\n";
}

echo "\n--- Deleting from Firestore ---\n";
$accessToken = getAccessToken($credentials);
if (!$accessToken) {
    die("Failed to get access token\n");
}

$deleteCount = 0;
$failCount = 0;

foreach ($toDelete as $i => $item) {
    $num = $i + 1;
    echo "  [{$num}/" . count($toDelete) . "] Deleting: {$item['name']}";
    $path = "clinics/{$item['docId']}";

    if (deleteDocument($projectId, $accessToken, $path)) {
        $deleteCount++;
        echo " -> DELETED\n";
    } else {
        $failCount++;
        echo " -> FAILED\n";
    }
}

echo "\n============================================\n";
echo "  CLEANUP COMPLETE\n";
echo "============================================\n";
echo "  Deleted: {$deleteCount}\n";
echo "  Failed:  {$failCount}\n";
echo "  Remaining in Firestore: " . count($toKeep) . " medical facilities\n";
echo "============================================\n";
