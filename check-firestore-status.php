<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$creds = json_decode(file_get_contents(base_path(config('services.firebase.credentials'))), true);
$pid = config('services.firebase.project_id');

// Get token
$now = time();
$h = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
$c = base64_encode(json_encode([
    'iss' => $creds['client_email'],
    'scope' => 'https://www.googleapis.com/auth/datastore',
    'aud' => 'https://oauth2.googleapis.com/token',
    'exp' => $now + 3600,
    'iat' => $now,
]));
$si = "$h.$c";
openssl_sign($si, $s, $creds['private_key'], 'SHA256');
$jwt = "$si." . base64_encode($s);

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]),
]);
$token = json_decode(curl_exec($ch), true)['access_token'] ?? null;
echo "Token: " . ($token ? "OK" : "FAIL") . "\n\n";

// List collections
echo "=== ROOT COLLECTIONS ===\n";
$url = "https://firestore.googleapis.com/v1/projects/$pid/databases/(default)/documents:listCollectionIds";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", "Content-Type: application/json"],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP $code\n";
$data = json_decode($resp, true);

if ($code === 200 && !empty($data['collectionIds'])) {
    echo "Collections found: " . implode(', ', $data['collectionIds']) . "\n\n";

    // Read 2 docs from each collection
    foreach ($data['collectionIds'] as $col) {
        sleep(1); // throttle
        $url = "https://firestore.googleapis.com/v1/projects/$pid/databases/(default)/documents/$col?pageSize=2";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"],
        ]);
        $r = curl_exec($ch);
        $c2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $d = json_decode($r, true);
        $cnt = count($d['documents'] ?? []);
        $more = isset($d['nextPageToken']) ? ' (+more)' : '';
        echo "  $col: $cnt docs$more (HTTP $c2)\n";

        foreach (($d['documents'] ?? []) as $doc) {
            echo "    - " . basename($doc['name']) . "\n";
        }
    }
} elseif ($code === 429) {
    echo "QUOTA EXHAUSTED - Firestore daily read limit reached.\n";
    echo "This is on the FREE Spark plan.\n";
    echo "Quota resets at midnight Pacific Time (UTC-8).\n";
    echo "\nFull error: $resp\n";
} else {
    echo "$resp\n";
}
