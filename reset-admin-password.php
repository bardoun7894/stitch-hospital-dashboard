#!/usr/bin/env php
<?php
/**
 * Reset/Create Admin User Password in Firestore
 *
 * This script creates or updates a super_admin user in the Firestore 'users' collection
 * with a bcrypt-hashed password so they can login to the dashboard.
 *
 * Usage: php reset-admin-password.php [email] [password]
 * Default: admin@clinicqu.com / Admin@123456
 */

$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
if (!file_exists($credentialsPath)) {
    die("Error: Firebase credentials not found at {$credentialsPath}\n");
}

$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

// Default admin credentials
$adminEmail = $argv[1] ?? 'admin@clinicqu.com';
$adminPassword = $argv[2] ?? 'Admin@123456';

echo "=== Admin User Password Reset ===\n";
echo "Project: {$projectId}\n";
echo "Email: {$adminEmail}\n";
echo "Password: {$adminPassword}\n\n";

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

function firestoreQuery($projectId, $accessToken, $collection, $field, $value) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents:runQuery";
    $body = json_encode([
        'structuredQuery' => [
            'from' => [['collectionId' => $collection]],
            'where' => [
                'fieldFilter' => [
                    'field' => ['fieldPath' => $field],
                    'op' => 'EQUAL',
                    'value' => ['stringValue' => $value]
                ]
            ],
            'limit' => 1
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function firestorePatch($projectId, $accessToken, $documentPath, $fields) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$documentPath}";
    // Add updateMask to only update specific fields
    $fieldPaths = array_keys($fields);
    $masks = implode('&', array_map(fn($f) => "updateMask.fieldPaths={$f}", $fieldPaths));
    $url .= "?{$masks}";

    $body = json_encode(['fields' => $fields]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
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
    return ['success' => $httpCode >= 200 && $httpCode < 300, 'code' => $httpCode, 'response' => $response];
}

// ── Main ──

echo "Getting access token...\n";
$accessToken = getAccessToken($credentials);
if (!$accessToken) die("Failed to get access token\n");
echo "Token obtained.\n\n";

// Generate bcrypt hash
$passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
echo "Password hash: {$passwordHash}\n\n";

// Check if user already exists
echo "Checking if user exists with email: {$adminEmail}...\n";
$queryResult = firestoreQuery($projectId, $accessToken, 'users', 'email', $adminEmail);

$existingDocPath = null;
if (is_array($queryResult)) {
    foreach ($queryResult as $result) {
        if (isset($result['document'])) {
            $existingDocPath = $result['document']['name'];
            // Extract just the collection/docId path
            $parts = explode('/documents/', $existingDocPath);
            $existingDocPath = end($parts);
            echo "Found existing user at: {$existingDocPath}\n";

            // Show current fields
            $fields = $result['document']['fields'] ?? [];
            echo "  Current role: " . ($fields['role']['stringValue'] ?? 'not set') . "\n";
            echo "  Current name: " . ($fields['name']['stringValue'] ?? 'not set') . "\n";
            echo "  Has password_hash: " . (isset($fields['password_hash']) ? 'yes' : 'NO') . "\n\n";
            break;
        }
    }
}

$now = date('Y-m-d\TH:i:s\Z');

if ($existingDocPath) {
    // Update existing user's password
    echo "Updating password for existing user...\n";
    $result = firestorePatch($projectId, $accessToken, $existingDocPath, [
        'password_hash' => ['stringValue' => $passwordHash],
        'role' => ['stringValue' => 'super_admin'],
        'updated_at' => ['stringValue' => $now],
    ]);

    if ($result['success']) {
        echo "Password UPDATED successfully!\n";
    } else {
        echo "FAILED to update (HTTP {$result['code']})\n";
        echo "Response: {$result['response']}\n";
    }
} else {
    // Create new admin user
    echo "User not found. Creating new admin user...\n";
    $userId = 'admin_' . substr(md5($adminEmail), 0, 12);

    $userFields = [
        'email' => ['stringValue' => $adminEmail],
        'name' => ['stringValue' => 'Admin'],
        'name_en' => ['stringValue' => 'Admin'],
        'role' => ['stringValue' => 'super_admin'],
        'password_hash' => ['stringValue' => $passwordHash],
        'phone' => ['stringValue' => ''],
        'status' => ['stringValue' => 'active'],
        'created_at' => ['stringValue' => $now],
        'updated_at' => ['stringValue' => $now],
    ];

    $result = firestoreCreate($projectId, $accessToken, 'users', $userId, $userFields);

    if ($result['success']) {
        echo "Admin user CREATED successfully!\n";
        echo "  Document ID: {$userId}\n";
    } else {
        echo "FAILED to create (HTTP {$result['code']})\n";
        echo "Response: {$result['response']}\n";
    }
}

echo "\n============================================\n";
echo "  LOGIN CREDENTIALS\n";
echo "============================================\n";
echo "  Email:    {$adminEmail}\n";
echo "  Password: {$adminPassword}\n";
echo "  Role:     super_admin\n";
echo "============================================\n";
