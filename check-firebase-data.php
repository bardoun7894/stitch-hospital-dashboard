#!/usr/bin/env php
<?php
/**
 * Check what's actually in Firestore using REST API
 */

// Load Firebase credentials
$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
if (!file_exists($credentialsPath)) {
    die("Error: Firebase credentials not found\n");
}

$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

echo "🔍 Checking Firestore Database\n";
echo "📍 Project: {$projectId}\n\n";

// Get OAuth2 access token (same as seeder)
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

function listDocuments($projectId, $accessToken, $collection) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    return null;
}

// Get access token
$accessToken = getAccessToken($credentials);
if (!$accessToken) {
    die("❌ Failed to get access token\n");
}

// Check each collection
$collections = ['clinics', 'users', 'bookings', 'alerts', 'settings', 'queue_states'];

foreach ($collections as $collection) {
    echo "📂 Collection: {$collection}\n";
    $data = listDocuments($projectId, $accessToken, $collection);

    if ($data && isset($data['documents'])) {
        echo "   ✅ Found " . count($data['documents']) . " documents\n";
        foreach ($data['documents'] as $doc) {
            $name = basename($doc['name']);
            echo "      - {$name}\n";
        }
    } else {
        echo "   ⚠️  No documents found or collection doesn't exist\n";
    }
    echo "\n";
}

// Check Firestore Rules
echo "🔒 To check Firestore Rules:\n";
echo "   Visit: https://console.firebase.google.com/project/{$projectId}/firestore/rules\n";
echo "\n";

echo "💡 If no data is showing, your Firestore Security Rules might be blocking writes.\n";
echo "   Try setting rules to allow all (for testing):\n";
echo "   rules_version = '2';\n";
echo "   service cloud.firestore {\n";
echo "     match /databases/{database}/documents {\n";
echo "       match /{document=**} {\n";
echo "         allow read, write: if true;\n";
echo "       }\n";
echo "     }\n";
echo "   }\n";
