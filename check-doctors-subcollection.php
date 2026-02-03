#!/usr/bin/env php
<?php
/**
 * Check Doctors Subcollections
 */

// Load Firebase credentials
$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

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

$accessToken = getAccessToken($credentials);

echo "🔍 Checking Doctors Subcollections\n";
echo "📍 Project: {$projectId}\n\n";

$clinics = ['gen_med_001', 'peds_001'];

foreach ($clinics as $clinicId) {
    echo "📂 Clinic: {$clinicId}\n";

    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/clinics/{$clinicId}/doctors";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $docs = $data['documents'] ?? [];

        if (empty($docs)) {
            echo "   ❌ No doctors found\n";
        } else {
            echo "   ✅ Found " . count($docs) . " doctor(s)\n";
            foreach ($docs as $doc) {
                $docId = basename($doc['name']);
                $fields = $doc['fields'] ?? [];
                $name = $fields['name_en']['stringValue'] ?? 'Unknown';
                echo "      - {$docId}: {$name}\n";
            }
        }
    } else {
        echo "   ❌ Failed to fetch (HTTP {$httpCode})\n";
    }
    echo "\n";
}
