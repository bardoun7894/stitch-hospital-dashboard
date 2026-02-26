#!/usr/bin/env php
<?php
/**
 * Cleanup Makkah Hospitals + Clinics from Firebase
 * Deletes non-active mkh_ documents from hospitals and clinics collections
 * Keeps hospitals/clinics with status "active"
 */

$credentialsPath = __DIR__ . '/storage/app/firebase-adminsdk.json';
if (!file_exists($credentialsPath)) {
    die("Error: Firebase credentials not found at {$credentialsPath}\n");
}

$credentials = json_decode(file_get_contents($credentialsPath), true);
$projectId = $credentials['project_id'];

echo "=== Makkah Data Cleanup ===\n";
echo "Project: {$projectId}\n\n";

// ── Auth ──

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

function firestoreList($projectId, $accessToken, $collection, $pageToken = null) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}?pageSize=300";
    if ($pageToken) {
        $url .= '&pageToken=' . urlencode($pageToken);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['documents' => [], 'nextPageToken' => null, 'error' => $httpCode];
    }

    $data = json_decode($response, true);
    return [
        'documents' => $data['documents'] ?? [],
        'nextPageToken' => $data['nextPageToken'] ?? null,
    ];
}

function firestoreDelete($projectId, $accessToken, $documentPath) {
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$documentPath}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

function getDocumentId($documentName) {
    $parts = explode('/', $documentName);
    return end($parts);
}

// ── Main ──

echo "Getting access token...\n";
$accessToken = getAccessToken($credentials);
if (!$accessToken) die("Failed to get access token\n");
echo "Token obtained.\n\n";

$collections = ['hospitals', 'clinics'];
$totalDeleted = 0;

// First pass: collect active hospital IDs so we keep their clinics too
$activeHospitalIds = [];

echo "--- Step 1: Scanning hospitals to find active ones ---\n";
$pageToken = null;
do {
    $result = firestoreList($projectId, $accessToken, 'hospitals', $pageToken);
    if (isset($result['error'])) {
        $accessToken = getAccessToken($credentials);
        if (!$accessToken) die("Failed to refresh access token\n");
        $result = firestoreList($projectId, $accessToken, 'hospitals', $pageToken);
    }

    foreach ($result['documents'] as $doc) {
        $docId = getDocumentId($doc['name'] ?? '');
        if (strpos($docId, 'mkh_') !== 0) continue;

        $status = $doc['fields']['status']['stringValue'] ?? '';
        if ($status === 'active') {
            $activeHospitalIds[$docId] = true;
        }
    }
    $pageToken = $result['nextPageToken'];
} while ($pageToken);

echo "  Found " . count($activeHospitalIds) . " active mkh_ hospitals (will keep)\n\n";

// Second pass: delete non-active hospitals and their clinics
foreach ($collections as $collection) {
    echo "--- Step 2: Cleaning {$collection} ---\n";
    $deleted = 0;
    $kept = 0;
    $skipped = 0;
    $pageToken = null;

    do {
        $result = firestoreList($projectId, $accessToken, $collection, $pageToken);

        if (isset($result['error'])) {
            echo "  ERROR listing {$collection}: HTTP {$result['error']}\n";
            $accessToken = getAccessToken($credentials);
            if (!$accessToken) die("Failed to refresh access token\n");
            $result = firestoreList($projectId, $accessToken, $collection, $pageToken);
        }

        foreach ($result['documents'] as $doc) {
            $docName = $doc['name'] ?? '';
            $docId = getDocumentId($docName);

            if (strpos($docId, 'mkh_') !== 0) {
                $skipped++; // Not an mkh_ doc, skip
                continue;
            }

            // For hospitals: keep if active
            if ($collection === 'hospitals' && isset($activeHospitalIds[$docId])) {
                $kept++;
                echo "  KEPT (active): {$docId}\n";
                continue;
            }

            // For clinics: keep if linked to an active hospital
            if ($collection === 'clinics') {
                $hospitalId = $doc['fields']['hospital_id']['stringValue'] ?? '';
                if (!empty($hospitalId) && isset($activeHospitalIds[$hospitalId])) {
                    $kept++;
                    echo "  KEPT (active hospital): {$docId}\n";
                    continue;
                }
            }

            // Delete non-active
            $path = "{$collection}/{$docId}";
            $success = firestoreDelete($projectId, $accessToken, $path);
            if ($success) {
                $deleted++;
                if ($deleted % 50 === 0) {
                    echo "  Deleted {$deleted} {$collection} so far...\n";
                }
            } else {
                echo "  FAILED to delete: {$docId}\n";
                $accessToken = getAccessToken($credentials);
                if ($accessToken) {
                    $success = firestoreDelete($projectId, $accessToken, $path);
                    if ($success) $deleted++;
                }
            }
        }

        $pageToken = $result['nextPageToken'];

        if ($deleted > 0 && $deleted % 100 === 0) {
            usleep(500000);
        }

    } while ($pageToken);

    echo "  {$collection}: Deleted {$deleted}, Kept (active) {$kept}, Non-mkh skipped {$skipped}\n\n";
    $totalDeleted += $deleted;
}

echo "============================================\n";
echo "  CLEANUP COMPLETE\n";
echo "============================================\n";
echo "  Total deleted: {$totalDeleted}\n";
echo "============================================\n";
