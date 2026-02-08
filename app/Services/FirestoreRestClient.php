<?php

namespace App\Services;

/**
 * Firestore REST API Client
 * Bypasses gRPC by using direct REST API calls
 */
class FirestoreRestClient
{
    protected $projectId;
    protected $accessToken;
    protected $credentials;

    public function __construct($credentialsPath, $projectId)
    {
        $this->projectId = $projectId;
        $this->credentials = json_decode(file_get_contents($credentialsPath), true);
        $this->accessToken = $this->getAccessToken();
    }

    protected function getAccessToken()
    {
        $jwt = $this->createJWT();

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

    protected function createJWT()
    {
        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));

        $claimSet = [
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ];

        $payload = base64_encode(json_encode($claimSet));
        $signatureInput = $header . '.' . $payload;

        openssl_sign($signatureInput, $signature, $this->credentials['private_key'], 'SHA256');
        $signature = base64_encode($signature);

        return $signatureInput . '.' . $signature;
    }

    public function collection($name)
    {
        return new FirestoreCollection($this, $name);
    }

    public function get($path)
    {
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/{$path}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        return null;
    }

    public function patch($path, array $fields, array $updateMask = [])
    {
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/{$path}";

        if (!empty($updateMask)) {
            $params = [];
            foreach ($updateMask as $field) {
                $params[] = 'updateMask.fieldPaths=' . urlencode($field);
            }
            $url .= '?' . implode('&', $params);
        }

        $body = json_encode(['fields' => $this->encodeFields($fields)]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200 ? json_decode($response, true) : null;
    }

    public function createDocument($collection, array $fields, ?string $documentId = null)
    {
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/{$collection}";
        if ($documentId) {
            $url .= '?documentId=' . urlencode($documentId);
        }

        $body = json_encode(['fields' => $this->encodeFields($fields)]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            return json_decode($response, true);
        }

        \Log::error("Firestore createDocument failed ($httpCode): $response");
        return null;
    }

    public function deleteDocument($path)
    {
        $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/{$path}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    protected function encodeFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = $this->encodeValue($value);
        }
        return $fields;
    }

    public function encodeValue($value): array
    {
        if ($value instanceof \Google\Cloud\Core\Timestamp) {
            return ['timestampValue' => $value->get()->format('Y-m-d\TH:i:s\Z')];
        } elseif ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
            return ['timestampValue' => $value->format('Y-m-d\TH:i:s\Z')];
        } elseif (is_string($value)) {
            return ['stringValue' => $value];
        } elseif (is_int($value)) {
            return ['integerValue' => (string) $value];
        } elseif (is_float($value)) {
            return ['doubleValue' => $value];
        } elseif (is_bool($value)) {
            return ['booleanValue' => $value];
        } elseif (is_null($value)) {
            return ['nullValue' => null];
        } elseif (is_array($value)) {
            // Empty array → Firestore arrayValue with empty values
            if (empty($value)) {
                return ['arrayValue' => ['values' => []]];
            }
            // Sequential array (list) vs associative (map)
            if (array_values($value) === $value) {
                $arrayValues = [];
                foreach ($value as $item) {
                    $arrayValues[] = $this->encodeValue($item);
                }
                return ['arrayValue' => ['values' => $arrayValues]];
            }
            return ['mapValue' => ['fields' => $this->encodeFields($value)]];
        }
        return ['nullValue' => null];
    }

    public function list($collection)
    {
        $allDocuments = [];
        $pageToken = null;

        do {
            $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/{$collection}?pageSize=100";
            if ($pageToken) {
                $url .= '&pageToken=' . urlencode($pageToken);
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->accessToken
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                break;
            }

            $data = json_decode($response, true);
            $documents = $data['documents'] ?? [];
            $allDocuments = array_merge($allDocuments, $documents);
            $pageToken = $data['nextPageToken'] ?? null;
        } while ($pageToken);

        return $allDocuments;
    }
}

class FirestoreCollection
{
    protected $client;
    protected $name;
    protected $whereFilters = [];
    protected $orderByField = null;
    protected $orderDirection = 'ASC';
    protected $limitCount = null;

    public function __construct($client, $name)
    {
        $this->client = $client;
        $this->name = $name;
    }

    public function where($field, $operator, $value)
    {
        $this->whereFilters[] = compact('field', 'operator', 'value');
        return $this;
    }

    public function orderBy($field, $direction = 'ASC')
    {
        $this->orderByField = $field;
        $this->orderDirection = strtoupper($direction);
        return $this;
    }

    public function limit($count)
    {
        $this->limitCount = $count;
        return $this;
    }

    public function documents()
    {
        $docs = $this->client->list($this->name);
        $result = [];

        foreach ($docs as $doc) {
            $docId = basename($doc['name'] ?? '');
            $docPath = $this->name . '/' . $docId;
            $document = new FirestoreDocument($doc, $this->client, $docPath);

            // Apply where filters
            if ($this->matchesFilters($document)) {
                $result[] = $document;
            }
        }

        // Apply ordering
        if ($this->orderByField) {
            usort($result, function($a, $b) {
                $aData = $a->data();
                $bData = $b->data();
                $aVal = $aData[$this->orderByField] ?? null;
                $bVal = $bData[$this->orderByField] ?? null;

                if ($this->orderDirection === 'DESC') {
                    return $bVal <=> $aVal;
                }
                return $aVal <=> $bVal;
            });
        }

        // Apply limit
        if ($this->limitCount) {
            $result = array_slice($result, 0, $this->limitCount);
        }

        return $result;
    }

    protected function matchesFilters($document)
    {
        $data = $document->data();

        foreach ($this->whereFilters as $filter) {
            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];
            $fieldValue = $data[$field] ?? null;

            switch ($operator) {
                case '=':
                case '==':
                    if ($fieldValue != $value) return false;
                    break;
                case '!=':
                    if ($fieldValue == $value) return false;
                    break;
                case '>':
                    if ($fieldValue <= $value) return false;
                    break;
                case '>=':
                    if ($fieldValue < $value) return false;
                    break;
                case '<':
                    if ($fieldValue >= $value) return false;
                    break;
                case '<=':
                    if ($fieldValue > $value) return false;
                    break;
            }
        }

        return true;
    }

    public function document($id)
    {
        return new FirestoreDocumentReference($this->client, $this->name . '/' . $id);
    }

    public function add(array $data)
    {
        $result = $this->client->createDocument($this->name, $data);
        if ($result && isset($result['name'])) {
            $docId = basename($result['name']);
            return new FirestoreDocumentReference($this->client, $this->name . '/' . $docId);
        }
        return null;
    }
}

class FirestoreDocumentReference
{
    protected $client;
    protected $path;

    public function __construct($client, $path)
    {
        $this->client = $client;
        $this->path = $path;
    }

    public function snapshot()
    {
        $docData = $this->client->get($this->path);
        return new FirestoreDocument($docData, $this->client, $this->path);
    }

    public function collection($name)
    {
        return new FirestoreCollection($this->client, $this->path . '/' . $name);
    }

    public function update(array $updates)
    {
        $fields = [];
        $updateMask = [];

        foreach ($updates as $update) {
            if (isset($update['path']) && array_key_exists('value', $update)) {
                $path = $update['path'];
                $updateMask[] = $path;
                $fields[$path] = $update['value'];
            }
        }

        return $this->client->patch($this->path, $fields, $updateMask);
    }

    public function set(array $data, array $options = [])
    {
        $merge = $options['merge'] ?? false;

        if ($merge) {
            $updateMask = array_keys($data);
            return $this->client->patch($this->path, $data, $updateMask);
        }

        return $this->client->patch($this->path, $data);
    }

    public function delete()
    {
        return $this->client->deleteDocument($this->path);
    }

    public function id()
    {
        return basename($this->path);
    }
}

class FirestoreDocument
{
    protected $data;
    protected $rawData;
    protected $client;
    protected $path;

    public function __construct($rawData, $client = null, $path = null)
    {
        $this->rawData = $rawData;
        $this->client = $client;
        $this->path = $path;
        $this->data = $this->parseFields($rawData['fields'] ?? []);
    }

    protected function parseFields($fields)
    {
        $result = [];

        foreach ($fields as $key => $value) {
            if (isset($value['stringValue'])) {
                $result[$key] = $value['stringValue'];
            } elseif (isset($value['integerValue'])) {
                $result[$key] = (int)$value['integerValue'];
            } elseif (isset($value['doubleValue'])) {
                $result[$key] = (float)$value['doubleValue'];
            } elseif (isset($value['booleanValue'])) {
                $result[$key] = $value['booleanValue'];
            } elseif (isset($value['nullValue'])) {
                $result[$key] = null;
            } elseif (isset($value['timestampValue'])) {
                // Parse Firestore timestamp to ISO 8601 string
                $result[$key] = $value['timestampValue'];
            } elseif (isset($value['geoPointValue'])) {
                // Parse GeoPoint as associative array
                $result[$key] = [
                    'latitude' => $value['geoPointValue']['latitude'] ?? 0,
                    'longitude' => $value['geoPointValue']['longitude'] ?? 0
                ];
            } elseif (isset($value['arrayValue'])) {
                $result[$key] = $this->parseArray($value['arrayValue']);
            } elseif (isset($value['mapValue'])) {
                $result[$key] = $this->parseFields($value['mapValue']['fields'] ?? []);
            }
        }

        return $result;
    }

    protected function parseArray($arrayValue)
    {
        $result = [];
        $values = $arrayValue['values'] ?? [];

        foreach ($values as $value) {
            if (isset($value['stringValue'])) {
                $result[] = $value['stringValue'];
            } elseif (isset($value['integerValue'])) {
                $result[] = (int)$value['integerValue'];
            } elseif (isset($value['doubleValue'])) {
                $result[] = (float)$value['doubleValue'];
            } elseif (isset($value['booleanValue'])) {
                $result[] = $value['booleanValue'];
            } elseif (isset($value['timestampValue'])) {
                $result[] = $value['timestampValue'];
            } elseif (isset($value['geoPointValue'])) {
                $result[] = [
                    'latitude' => $value['geoPointValue']['latitude'] ?? 0,
                    'longitude' => $value['geoPointValue']['longitude'] ?? 0
                ];
            } elseif (isset($value['mapValue'])) {
                $result[] = $this->parseFields($value['mapValue']['fields'] ?? []);
            }
        }

        return $result;
    }

    public function exists()
    {
        return !empty($this->rawData);
    }

    public function data()
    {
        return $this->data;
    }

    public function id()
    {
        if (isset($this->rawData['name'])) {
            return basename($this->rawData['name']);
        }
        return null;
    }

    public function reference()
    {
        return $this;
    }

    public function collection($name)
    {
        if ($this->client && $this->path) {
            return new FirestoreCollection($this->client, $this->path . '/' . $name);
        }
        throw new \Exception('Cannot access subcollection without client and path');
    }

    public function snapshot()
    {
        return $this;
    }
}
