<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;
use Google\Cloud\Firestore\FirestoreClient;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FirebaseService
{
    protected $firestoreClient = null;
    protected $messaging = null;
    protected ?string $credentialsPath = null;
    protected bool $allowMockFallback = false;

    /**
     * Per-request cache for Firestore collection data.
     * Prevents duplicate fetches of the same collection within a single HTTP request.
     * Keys are collection names (e.g., 'bookings'), values are arrays of FirestoreDocument objects.
     */
    protected array $requestCache = [];

    public function __construct()
    {
        $this->allowMockFallback = (bool) config('services.firebase.allow_mock_fallback', false);

        if ($this->allowMockFallback) {
            \Log::warning('FirebaseService is running with FIREBASE_ALLOW_MOCK_FALLBACK enabled.');
        }

        // Check for service account file
        $credentialsPath = base_path(config('services.firebase.credentials', 'firebase.json'));

        if (file_exists($credentialsPath)) {
            // Initialize Firestore with custom REST client (bypasses gRPC)
            try {
                $this->firestoreClient = new FirestoreRestClient(
                    $credentialsPath,
                    config('services.firebase.project_id', 'clinicqu-1e93c')
                );
            } catch (\Exception $e) {
                \Log::error('Firestore Init Error: ' . $e->getMessage());
            }

            // Messaging initialized lazily when needed (see getMessaging())
            $this->credentialsPath = $credentialsPath;
        }
    }

    /**
     * Get the Firestore database instance.
     * Returns the underlying Firestore REST client for direct access.
     *
     * @return \App\Services\FirestoreRestClient|null
     */
    public function getFirestore()
    {
        return $this->firestoreClient;
    }

    /**
     * Get all bookings documents with per-request caching.
     * The first call fetches from Firestore; subsequent calls within the same
     * HTTP request return the cached result, eliminating duplicate REST API calls.
     *
     * @return array Array of FirestoreDocument objects from the bookings collection
     */
    public function getBookingsRaw(): array
    {
        return $this->getCollectionCached('bookings', 30);
    }

    /**
     * Generic collection fetcher with per-request + Redis caching.
     * 1) Checks in-memory requestCache (prevents duplicate Firestore calls within same HTTP request)
     * 2) Checks Redis cache (prevents Firestore calls across requests within TTL)
     * 3) Falls back to Firestore REST API
     *
     * @param string $collection Collection name
     * @param int $redisTtl Redis cache TTL in seconds (0 = skip Redis)
     * @return array Array of FirestoreDocument objects
     */
    protected function getCollectionCached(string $collection, int $redisTtl = 0): array
    {
        // 1. Per-request cache (in-memory)
        if (isset($this->requestCache[$collection])) {
            return $this->requestCache[$collection];
        }

        $firestore = $this->getFirestore();
        if (!$firestore) {
            return [];
        }

        // 2. Redis cache layer
        if ($redisTtl > 0) {
            $cacheKey = "firestore:{$collection}";
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $this->requestCache[$collection] = $cached;
                return $cached;
            }
        }

        // 3. Fetch from Firestore
        $docs = $firestore->collection($collection)->documents();
        $this->requestCache[$collection] = $docs;

        // Store in Redis if TTL specified
        if ($redisTtl > 0) {
            Cache::put("firestore:{$collection}", $docs, $redisTtl);
        }

        return $docs;
    }

    /**
     * Get cached clinics collection documents.
     */
    public function getClinicsRaw(): array
    {
        return $this->getCollectionCached('clinics', 300);
    }

    /**
     * Get cached doctors collection documents.
     */
    public function getDoctorsRaw(): array
    {
        return $this->getCollectionCached('doctors', 300);
    }

    /**
     * Get cached alerts collection documents.
     */
    public function getAlertsRaw(): array
    {
        return $this->getCollectionCached('alerts', 120);
    }

    /**
     * Get cached settings collection documents.
     */
    public function getSettingsRaw(): array
    {
        return $this->getCollectionCached('settings', 600);
    }

    /**
     * Invalidate booking-related caches after writes.
     * Call this after any booking create/update/delete operation.
     */
    public function invalidateBookingCaches(): void
    {
        unset($this->requestCache['bookings']);
        Cache::forget('firestore:bookings');
        Cache::forget('pending_bookings');
        Cache::forget('tv_queue_data');

        // Invalidate queue data caches (pattern-based)
        // We can't easily iterate Redis keys, so forget known keys
        Cache::forget('queue_data_all');

        // Invalidate dashboard caches (they contain booking data)
        // These use dynamic keys, but we can clear the booking-specific ones
        $this->forgetCacheByPrefix('bookings_queue_data_');
        $this->forgetCacheByPrefix('mobile_user_bookings_');
    }

    /**
     * Invalidate clinic-related caches after writes.
     */
    public function invalidateClinicCaches(): void
    {
        unset($this->requestCache['clinics']);
        Cache::forget('firestore:clinics');
        $this->forgetCacheByPrefix('mobile_clinics_');
        $this->forgetCacheByPrefix('mobile_clinic_');
        $this->forgetCacheByPrefix('mobile_hospital_clinics_');
    }

    /**
     * Invalidate doctor-related caches after writes.
     */
    public function invalidateDoctorCaches(): void
    {
        unset($this->requestCache['doctors']);
        Cache::forget('firestore:doctors');
        $this->forgetCacheByPrefix('mobile_clinic_doctors_');
    }

    /**
     * Best-effort cache prefix invalidation.
     * For Redis, uses SCAN to find and delete keys matching the prefix.
     * For other drivers, this is a no-op (keys expire via TTL).
     */
    protected function forgetCacheByPrefix(string $prefix): void
    {
        try {
            $store = Cache::getStore();
            if ($store instanceof \Illuminate\Cache\RedisStore) {
                $redis = $store->connection();
                $cachePrefix = Cache::getPrefix();
                $cursor = null;
                do {
                    $result = $redis->scan($cursor, ['match' => $cachePrefix . $prefix . '*', 'count' => 100]);
                    if ($result === false) break;
                    [$cursor, $keys] = $result;
                    if (!empty($keys)) {
                        $redis->del(...$keys);
                    }
                } while ($cursor);
            }
        } catch (\Exception $e) {
            // Silently fail — keys will expire via TTL
        }
    }

    protected function getMessaging()
    {
        if (!$this->messaging && $this->credentialsPath) {
            try {
                $factory = (new Factory)->withServiceAccount($this->credentialsPath);
                $this->messaging = $factory->createMessaging();
            } catch (\Exception $e) {
                \Log::warning('Firebase Messaging Init Skipped: ' . $e->getMessage());
            }
        }
        return $this->messaging;
    }

    protected function canUseMockFallback(string $context): bool
    {
        if ($this->allowMockFallback) {
            \Log::warning("Using mock fallback for {$context}");
            return true;
        }

        \Log::warning("Mock fallback blocked for {$context}. Returning safe defaults.");
        return false;
    }

    /**
     * Get localized field value based on current app locale.
     * Returns English field if locale is 'en', otherwise returns Arabic field.
     *
     * @param array $data Firestore document data
     * @param string $fieldName Base field name (e.g., 'name')
     * @param string|null $defaultValue Default value if both fields are missing
     * @return string Localized field value
     */
    protected function getLocalizedField(array $data, string $fieldName, ?string $defaultValue = null): string
    {
        $locale = app()->getLocale();

        if ($locale === 'en' && isset($data["{$fieldName}_en"])) {
            return $data["{$fieldName}_en"];
        }

        return $data[$fieldName] ?? $defaultValue ?? 'N/A';
    }


    /**
     * Get bookings with pagination support.
     * 
     * @param int $limit Number of bookings per page (default 50)
     * @param string|null $startAfter Document ID to start after (for pagination)
     * @param string|null $status Filter by status
     * @param string|null $clinicId Filter by clinic
     * @param string|null $date Filter by date (Y-m-d)
     * @return array ['data' => [...], 'next_cursor' => string|null]
     */
    public function getBookings(int $limit = 50, ?string $startAfter = null, ?string $status = null, ?string $clinicId = null, ?string $date = null): array
    {
        if (!$this->firestoreClient) {
            return $this->canUseMockFallback('getBookings (firestore unavailable)')
                ? ['data' => $this->getMockBookings(), 'next_cursor' => null]
                : ['data' => [], 'next_cursor' => null];
        }

        try {
            // Use per-request cached bookings to avoid duplicate Firestore calls
            $documents = $this->getBookingsRaw();

            // Apply filters client-side (same as FirestoreCollection does internally)
            $filtered = [];
            foreach ($documents as $document) {
                if (!$document->exists()) continue;
                $data = $document->data();

                if ($status && ($data['status'] ?? null) != $status) continue;
                if ($clinicId && ($data['clinic_id'] ?? null) != $clinicId) continue;

                $filtered[] = $document;
            }

            // Sort by created_at DESC
            usort($filtered, function ($a, $b) {
                $aVal = $a->data()['created_at'] ?? null;
                $bVal = $b->data()['created_at'] ?? null;
                return $bVal <=> $aVal;
            });

            // Handle pagination cursor
            if ($startAfter) {
                $found = false;
                $afterCursor = [];
                foreach ($filtered as $doc) {
                    if ($found) {
                        $afterCursor[] = $doc;
                    } elseif ($doc->id() === $startAfter) {
                        $found = true;
                    }
                }
                $filtered = $found ? $afterCursor : $filtered;
            }

            // Apply limit (+1 to detect next page)
            $documents = array_slice($filtered, 0, $limit + 1);

            $bookings = [];
            $count = 0;
            $lastDocId = null;
            
            foreach ($documents as $document) {
                if (!$document->exists()) continue;
                
                $count++;
                if ($count > $limit) {
                    // This is the extra document, don't include but use for cursor
                    break;
                }
                
                $data = $document->data();
                $lastDocId = $document->id();

                // Date filter (client-side since Firestore composite queries are limited)
                if ($date) {
                    $bookingDate = null;
                    if (isset($data['scheduled_date'])) {
                        $sd = $data['scheduled_date'];
                        if ($sd instanceof \Google\Cloud\Core\Timestamp) {
                            $bookingDate = $sd->get()->format('Y-m-d');
                        } elseif (is_string($sd)) {
                            $bookingDate = substr($sd, 0, 10);
                        }
                    }
                    if ($bookingDate !== $date) continue;
                }

                // Transform Firebase data to dashboard format
                $booking = [
                    'id' => $document->id(),
                    'status' => $data['status'] ?? 'pending',
                    'patient_id' => $data['patient_id'] ?? null,
                    'doctor_id' => $data['doctor_id'] ?? null,
                    'clinic_id' => $data['clinic_id'] ?? null,
                    'scheduled_date' => $data['scheduled_date'] ?? null,
                    'token_number' => $data['token_number'] ?? null,
                    'created_at' => $data['created_at'] ?? null,
                ];

                // Use denormalized names if available, otherwise fetch
                $booking['patient'] = $data['patient_name'] ?? null;
                if (!$booking['patient'] && $booking['patient_id']) {
                    $patient = $this->getPatientDetails($booking['patient_id']);
                    $booking['patient'] = $patient['name'] ?? 'Unknown Patient';
                }
                $booking['patient'] = $booking['patient'] ?? 'Unknown Patient';

                $booking['doctor_name'] = $data['doctor_name'] ?? null;
                if (!$booking['doctor_name'] && $booking['doctor_id'] && $booking['clinic_id']) {
                    $doctor = $this->getDoctorDetails($booking['clinic_id'], $booking['doctor_id']);
                    $booking['doctor_name'] = $this->getLocalizedField($doctor, 'name', 'Unknown Doctor');
                }
                $booking['doctor_name'] = $booking['doctor_name'] ?? 'Unknown Doctor';

                $booking['clinic'] = $data['clinic_name'] ?? null;
                if (!$booking['clinic'] && $booking['clinic_id']) {
                    $clinic = $this->getClinicById($booking['clinic_id']);
                    $booking['clinic'] = $this->getLocalizedField($clinic, 'name', 'Unknown Clinic');
                }
                $booking['clinic'] = $booking['clinic'] ?? 'Unknown Clinic';

                $bookings[] = $booking;
            }
            
            // Determine if there's a next page
            $nextCursor = ($count > $limit) ? $lastDocId : null;
            
            return [
                'data' => $bookings,
                'next_cursor' => $nextCursor,
            ];
        } catch (\Exception $e) {
            \Log::error('getBookings error: ' . $e->getMessage());
            return $this->canUseMockFallback('getBookings (exception)')
                ? ['data' => $this->getMockBookings(), 'next_cursor' => null]
                : ['data' => [], 'next_cursor' => null];
        }
    }

    protected function getMockBookings()
    {
        return [];
    }

    // ─── Hospital Methods ───

    public function getHospitals(): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $snapshot = $firestore->collection('hospitals')->documents();
            $hospitals = [];
            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();
                if (isset($data['location']) && is_object($data['location'])) {
                    $data['location'] = [
                        'lat' => $data['location']->latitude(),
                        'lng' => $data['location']->longitude(),
                    ];
                }
                $hospitals[] = $data;
            }
            return $hospitals;
        } catch (\Exception $e) {
            \Log::error('getHospitals error: ' . $e->getMessage());
            return [];
        }
    }

    public function getHospitalById(string $hospitalId): ?array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $doc = $firestore->collection('hospitals')->document($hospitalId)->snapshot();
            if (!$doc->exists()) return null;

            $data = $doc->data();
            $data['id'] = $doc->id();
            if (isset($data['location']) && is_object($data['location'])) {
                $data['location'] = [
                    'lat' => $data['location']->latitude(),
                    'lng' => $data['location']->longitude(),
                ];
            }
            return $data;
        } catch (\Exception $e) {
            \Log::error('getHospitalById error: ' . $e->getMessage());
            return null;
        }
    }

    public function createHospital(array $data): ?string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $fields = [
                'name' => $data['name'] ?? '',
                'name_en' => $data['name_en'] ?? $data['name'] ?? '',
                'address' => $data['address'] ?? '',
                'phone' => $data['phone'] ?? '',
                'status' => $data['status'] ?? 'pending',
                'submitted_by' => $data['submitted_by'] ?? null,
                'submitted_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'contact_person' => $data['contact_person'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'created_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
                'updated_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
            ];

            if (!empty($data['latitude']) && !empty($data['longitude'])) {
                $fields['location'] = [
                    'latitude' => (float)$data['latitude'],
                    'longitude' => (float)$data['longitude'],
                ];
            }

            $result = $firestore->createDocument('hospitals', $fields);
            $this->invalidateHospitalCaches();
            if ($result && isset($result['name'])) {
                return basename($result['name']);
            }
            return null;
        } catch (\Exception $e) {
            \Log::error('Error creating hospital: ' . $e->getMessage());
            return null;
        }
    }

    public function updateHospital(string $hospitalId, array $data): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $updates = [];
            $allowedFields = ['name', 'name_en', 'address', 'phone', 'status', 'logo_url'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = ['path' => $field, 'value' => $data[$field]];
                }
            }

            if (!empty($data['latitude']) && !empty($data['longitude'])) {
                $updates[] = ['path' => 'location', 'value' => [
                    'latitude' => (float)$data['latitude'],
                    'longitude' => (float)$data['longitude'],
                ]];
            }

            $updates[] = ['path' => 'updated_at', 'value' => new \DateTime()];

            $firestore->collection('hospitals')->document($hospitalId)->update($updates);
            return true;
        } catch (\Exception $e) {
            \Log::error('Error updating hospital: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteHospital(string $hospitalId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $result = $firestore->deleteDocument("hospitals/{$hospitalId}");
            $this->invalidateHospitalCaches();
            return $result;
        } catch (\Exception $e) {
            \Log::error('Error deleting hospital: ' . $e->getMessage());
            return false;
        }
    }

    public function approveHospital(string $hospitalId, string $reviewerId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $updates = [
                ['path' => 'status', 'value' => 'active'],
                ['path' => 'reviewed_by', 'value' => $reviewerId],
                ['path' => 'reviewed_at', 'value' => (new \DateTime())->format('Y-m-d\TH:i:s\Z')],
                ['path' => 'rejection_reason', 'value' => null],
                ['path' => 'updated_at', 'value' => new \DateTime()],
            ];
            $firestore->collection('hospitals')->document($hospitalId)->update($updates);
            $this->invalidateHospitalCaches();
            return true;
        } catch (\Exception $e) {
            \Log::error('Error approving hospital: ' . $e->getMessage());
            return false;
        }
    }

    public function rejectHospital(string $hospitalId, string $reviewerId, string $reason): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $updates = [
                ['path' => 'status', 'value' => 'rejected'],
                ['path' => 'reviewed_by', 'value' => $reviewerId],
                ['path' => 'reviewed_at', 'value' => (new \DateTime())->format('Y-m-d\TH:i:s\Z')],
                ['path' => 'rejection_reason', 'value' => $reason],
                ['path' => 'updated_at', 'value' => new \DateTime()],
            ];
            $firestore->collection('hospitals')->document($hospitalId)->update($updates);
            $this->invalidateHospitalCaches();
            return true;
        } catch (\Exception $e) {
            \Log::error('Error rejecting hospital: ' . $e->getMessage());
            return false;
        }
    }

    public function resubmitHospital(string $hospitalId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $updates = [
                ['path' => 'status', 'value' => 'pending'],
                ['path' => 'reviewed_by', 'value' => null],
                ['path' => 'reviewed_at', 'value' => null],
                ['path' => 'rejection_reason', 'value' => null],
                ['path' => 'submitted_at', 'value' => (new \DateTime())->format('Y-m-d\TH:i:s\Z')],
                ['path' => 'updated_at', 'value' => new \DateTime()],
            ];
            $firestore->collection('hospitals')->document($hospitalId)->update($updates);
            $this->invalidateHospitalCaches();
            return true;
        } catch (\Exception $e) {
            \Log::error('Error resubmitting hospital: ' . $e->getMessage());
            return false;
        }
    }

    public function getPendingHospitalsCount(): int
    {
        $hospitals = $this->getHospitals();
        return count(array_filter($hospitals, fn($h) => ($h['status'] ?? '') === 'pending'));
    }

    public function invalidateHospitalCaches(): void
    {
        unset($this->requestCache['hospitals']);
        Cache::forget('firestore:hospitals');
        $this->forgetCacheByPrefix('hospital_index_');
        Cache::forget('mobile_hospitals_active');
        $this->forgetCacheByPrefix('mobile_hospital_');
        $this->forgetCacheByPrefix('mobile_search_');
    }

    public function getClinicsForHospital(string $hospitalId): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $snapshot = $firestore->collection('clinics')
                ->where('hospital_id', '=', $hospitalId)
                ->documents();

            $clinics = [];
            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();
                if (isset($data['location']) && is_object($data['location'])) {
                    $data['location'] = [
                        'lat' => $data['location']->latitude(),
                        'lng' => $data['location']->longitude(),
                    ];
                }
                $clinics[] = $data;
            }
            return $clinics;
        } catch (\Exception $e) {
            \Log::error('getClinicsForHospital error: ' . $e->getMessage());
            return [];
        }
    }

    // ─── Clinic Methods ───

    public function getClinics()
    {
        $firestore = $this->getFirestore();
        if ($firestore) {
            try {
                // Use cached collections to avoid triple collection scan
                $snapshot = $this->getClinicsRaw();
                $today = date('Y-m-d');

                // Pre-fetch today's bookings grouped by clinic (uses per-request + Redis cache)
                $bookingsByClinic = [];
                $allBookings = $this->getBookingsRaw();
                foreach ($allBookings as $bDoc) {
                    if (!$bDoc->exists()) continue;
                    $bData = $bDoc->data();
                    $bookingDate = null;
                    if (isset($bData['scheduled_date'])) {
                        $sd = $bData['scheduled_date'];
                        if ($sd instanceof \Google\Cloud\Core\Timestamp) {
                            $bookingDate = $sd->get()->format('Y-m-d');
                        } elseif (is_string($sd)) {
                            $bookingDate = substr($sd, 0, 10);
                        }
                    }
                    if ($bookingDate === $today) {
                        $cid = $bData['clinic_id'] ?? 'unknown';
                        $bookingsByClinic[$cid][] = $bData;
                    }
                }

                // Pre-fetch doctors count per clinic (uses per-request + Redis cache)
                $doctorsByClinic = [];
                $allDoctors = $this->getDoctorsRaw();
                foreach ($allDoctors as $dDoc) {
                    if (!$dDoc->exists()) continue;
                    $dData = $dDoc->data();
                    $cid = $dData['clinic_id'] ?? 'unknown';
                    $status = $dData['status'] ?? 'off';
                    if ($status === 'available' || $status === 'active') {
                        $doctorsByClinic[$cid] = ($doctorsByClinic[$cid] ?? 0) + 1;
                    }
                }

                $clinics = [];
                foreach ($snapshot as $doc) {
                    if ($doc->exists()) {
                        $data = $doc->data();
                        $clinicId = $doc->id();
                        $clinicBookings = $bookingsByClinic[$clinicId] ?? [];

                        // Count patients waiting (pending, confirmed, arrived, acceptedAwaitingPayment)
                        $waitingStatuses = ['pending', 'confirmed', 'arrived', 'acceptedAwaitingPayment'];
                        $patientsWaiting = 0;
                        foreach ($clinicBookings as $b) {
                            if (in_array($b['status'] ?? '', $waitingStatuses)) {
                                $patientsWaiting++;
                            }
                        }

                        $doctorsOnDuty = $doctorsByClinic[$clinicId] ?? 0;

                        // Estimate avg wait: ~10 min per waiting patient per doctor (rough)
                        $avgWait = ($doctorsOnDuty > 0 && $patientsWaiting > 0)
                            ? round($patientsWaiting * 10 / $doctorsOnDuty) . 'm'
                            : '0m';

                        // Determine status
                        $isPaused = $data['is_paused'] ?? false;
                        $status = $isPaused ? __('messages.paused') : ($patientsWaiting > 10 ? __('messages.high_load') : __('messages.running'));

                        // Generate alert if waiting is high
                        $alert = null;
                        if ($patientsWaiting > 15) {
                            $alert = __('messages.high_load');
                        }

                        $clinics[] = [
                            'id' => $clinicId,
                            'name' => $this->getLocalizedField($data, 'name', 'Clinic'),
                            'icon' => $data['icon'] ?? 'medical_services',
                            'icon_color' => $data['icon_color'] ?? 'blue',
                            'hospital_id' => $data['hospital_id'] ?? null,
                            'clinic_status' => $data['status'] ?? 'active',
                            'doctors_on_duty' => $doctorsOnDuty,
                            'patients_waiting' => $patientsWaiting,
                            'avg_wait' => $avgWait,
                            'status' => $status,
                            'alerts' => $alert,
                        ];
                    }
                }
                if (!empty($clinics)) return $clinics;
            } catch (\Exception $e) {
                \Log::error('Error fetching clinics from Firestore: ' . $e->getMessage());
            }
        }

        // No Firestore data available
        return [];
    }

    public function getDoctors($clinicId = null)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            $this->canUseMockFallback('getDoctors (firestore unavailable)');
            return $this->getMockDoctors();
        }

        try {
            $doctors = [];
            $query = $firestore->collection('doctors');

            if ($clinicId) {
                $query = $query->where('clinic_id', '=', $clinicId);
            }

            $snapshot = $query->documents();

            foreach ($snapshot as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $doctors[] = [
                        'id' => $doc->id(),
                        'name' => $this->getLocalizedField($data, 'name', ''),
                        'specialty' => $data['specialty'] ?? '',
                        'clinic' => $data['clinic_id'] ?? '',
                        'clinic_id' => $data['clinic_id'] ?? '',
                        'hospital_id' => $data['hospital_id'] ?? '',
                        'user_id' => $data['user_id'] ?? '',
                        'phone' => $data['phone'] ?? '',
                        'status' => $data['status'] ?? 'off',
                    ];
                }
            }

            if (!empty($doctors)) {
                return $doctors;
            }

            $this->canUseMockFallback('getDoctors (no data)');
            return $this->getMockDoctors();
        } catch (\Exception $e) {
            \Log::error('Firestore getDoctors error: ' . $e->getMessage());
            return $this->getMockDoctors();
        }
    }

    /**
     * Get all doctors belonging to clinics of a specific hospital.
     *
     * @param string $hospitalId
     * @return array
     */
    public function getDoctorsForHospital(string $hospitalId): array
    {
        $clinics = $this->getClinicsForHospital($hospitalId);
        $clinicIds = array_map(fn($c) => $c['id'] ?? '', $clinics);

        $allDoctors = $this->getDoctors();
        return array_values(array_filter($allDoctors, function ($doctor) use ($clinicIds) {
            return in_array($doctor['clinic'] ?? $doctor['clinic_id'] ?? '', $clinicIds);
        }));
    }

    protected function getMockDoctors()
    {
        return [];
    }

    public function getPatients()
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return $this->canUseMockFallback('getPatients (firestore unavailable)')
                ? $this->getMockPatients()
                : [];
        }

        try {
            $usersRef = $firestore->collection('users');
            $query = $usersRef->where('role', '=', 'patient');
            $snapshot = $query->documents();
            
            $patients = [];
            foreach ($snapshot as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $patients[] = [
                        'id' => $doc->id(),
                        'name' => $data['name'] ?? 'Unknown',
                        'phone' => $data['phone'] ?? '-',
                        'national_id' => $data['national_id'] ?? '-',
                        'email' => $data['email'] ?? '-',
                        'created_at' => isset($data['created_at']) ? date('Y-m-d', strtotime($data['created_at'])) : '-',
                        // 'admitted' etc would require joining bookings, skipping for basic registry
                    ];
                }
            }
            return $patients;
        } catch (\Exception $e) {
            \Log::error('Error fetching patients: ' . $e->getMessage());
            return [];
        }
    }

    public function searchPatients($searchQuery)
    {
        // Simple search implementation
        $allPatients = $this->getPatients();
        if (empty($searchQuery)) return $allPatients;

        $searchQuery = strtolower($searchQuery);
        return array_filter($allPatients, function($p) use ($searchQuery) {
            return str_contains(strtolower($p['name']), $searchQuery) ||
                   str_contains($p['phone'], $searchQuery) ||
                   str_contains(strtolower((string) ($p['email'] ?? '')), $searchQuery) ||
                   str_contains((string) ($p['national_id'] ?? ''), $searchQuery);
        });
    }

    /**
     * Get patients for a specific doctor by finding all bookings assigned to them.
     * Returns unique patients across all historical bookings.
     */
    public function getPatientsForDoctor(string $doctorId): array
    {
        $allBookings = $this->getBookingsRaw();
        $patientIds = [];

        foreach ($allBookings as $bDoc) {
            if (!$bDoc->exists()) continue;
            $bData = $bDoc->data();
            if (($bData['doctor_id'] ?? '') === $doctorId && !empty($bData['patient_id'])) {
                $patientIds[$bData['patient_id']] = true;
            }
        }

        return $this->fetchPatientsByIds(array_keys($patientIds));
    }

    /**
     * Get patients for a specific clinic by finding all bookings for that clinic.
     */
    public function getPatientsForClinic(string $clinicId): array
    {
        $allBookings = $this->getBookingsRaw();
        $patientIds = [];

        foreach ($allBookings as $bDoc) {
            if (!$bDoc->exists()) continue;
            $bData = $bDoc->data();
            if (($bData['clinic_id'] ?? '') === $clinicId && !empty($bData['patient_id'])) {
                $patientIds[$bData['patient_id']] = true;
            }
        }

        return $this->fetchPatientsByIds(array_keys($patientIds));
    }

    /**
     * Get patients for a hospital by finding all clinics in that hospital,
     * then all bookings for those clinics.
     */
    public function getPatientsForHospital(string $hospitalId): array
    {
        // Get all clinics belonging to this hospital
        $clinicsRaw = $this->getClinicsRaw();
        $clinicIds = [];
        foreach ($clinicsRaw as $cDoc) {
            if (!$cDoc->exists()) continue;
            $cData = $cDoc->data();
            if (($cData['hospital_id'] ?? '') === $hospitalId) {
                $clinicIds[$cDoc->id()] = true;
            }
        }

        if (empty($clinicIds)) {
            return [];
        }

        // Find all bookings for those clinics
        $allBookings = $this->getBookingsRaw();
        $patientIds = [];
        foreach ($allBookings as $bDoc) {
            if (!$bDoc->exists()) continue;
            $bData = $bDoc->data();
            if (isset($clinicIds[$bData['clinic_id'] ?? '']) && !empty($bData['patient_id'])) {
                $patientIds[$bData['patient_id']] = true;
            }
        }

        return $this->fetchPatientsByIds(array_keys($patientIds));
    }

    /**
     * Fetch patient details for a list of IDs and return in standard list format.
     */
    protected function fetchPatientsByIds(array $patientIds): array
    {
        $patients = [];
        foreach ($patientIds as $patientId) {
            $detail = $this->getPatientDetails($patientId);
            if ($detail) {
                $patients[] = [
                    'id' => $detail['id'],
                    'name' => $detail['name'] ?? 'Unknown',
                    'phone' => $detail['phone'] ?? '-',
                    'national_id' => $detail['national_id'] ?? '-',
                    'email' => $detail['email'] ?? '-',
                    'created_at' => isset($detail['created_at']) && $detail['created_at'] !== '-'
                        ? (strlen($detail['created_at']) > 10 ? substr($detail['created_at'], 0, 10) : $detail['created_at'])
                        : '-',
                ];
            }
        }
        return $patients;
    }

    /**
     * Compute patient statistics from an already-fetched patients array.
     */
    public function getPatientStats(array $patients): array
    {
        $total = count($patients);
        $currentMonth = date('Y-m');
        $newThisMonth = 0;

        foreach ($patients as $p) {
            $createdAt = $p['created_at'] ?? '-';
            if ($createdAt !== '-' && str_starts_with($createdAt, $currentMonth)) {
                $newThisMonth++;
            }
        }

        return [
            'total' => $total,
            'new_this_month' => $newThisMonth,
            'pending_insurance' => 0,
        ];
    }

    /**
     * Search patients scoped to a specific role.
     * First fetches role-scoped patients, then applies text search filter.
     */
    public function searchPatientsScoped(string $query, string $role, ?string $doctorId, ?string $clinicId, ?string $hospitalId): array
    {
        // Get role-scoped patients
        switch ($role) {
            case 'doctor':
                $patients = $doctorId ? $this->getPatientsForDoctor($doctorId) : [];
                break;
            case 'reception':
            case 'clinic_admin':
                $patients = $clinicId ? $this->getPatientsForClinic($clinicId) : [];
                break;
            case 'hospital_manager':
                $patients = $hospitalId ? $this->getPatientsForHospital($hospitalId) : [];
                break;
            default:
                $patients = $this->getPatients();
                break;
        }

        if (empty($query)) {
            return $patients;
        }

        // Apply text search filter
        $query = strtolower($query);
        return array_values(array_filter($patients, function ($p) use ($query) {
            return str_contains(strtolower($p['name']), $query) ||
                   str_contains($p['phone'], $query) ||
                   str_contains(strtolower((string) ($p['email'] ?? '')), $query) ||
                   str_contains((string) ($p['national_id'] ?? ''), $query);
        }));
    }

    public function createPatient($data)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            // Use phone as ID if unique, or auto-id. 
            // Auth usually uses UID from Firebase Auth. 
            // For manual creation by admin, we might generate a UUID or let Firestore do it?
            // But if we want them to login later, they need an Auth account.
            // For MVP admin entry, we just create a Firestore doc. They can't login unless Auth account created.
            // We'll let Firestore generate ID.
            
            $docRef = $firestore->collection('users')->add([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'role' => 'patient',
                'locale' => 'ar',
                'created_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                'updated_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                // Family members?
            ]);
            
            return $docRef->id();
        } catch (\Exception $e) {
            \Log::error('Error creating patient: ' . $e->getMessage());
            return null;
        }
    }

    protected function getMockPatients() {
        return [];
    }

    public function getAlerts()
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            $this->canUseMockFallback('getAlerts (firestore unavailable)');
            return $this->getMockAlerts();
        }

        try {
            $snapshot = $firestore
                ->collection('alerts')
                ->orderBy('created_at', 'DESC')
                ->limit(10)
                ->documents();

            $alerts = [];
            foreach ($snapshot as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $alerts[] = [
                        'id' => $doc->id(),
                        'type' => $data['type'] ?? 'info',
                        'title' => $data['message'] ?? '',
                        'message' => $data['message'] ?? '',
                        'description' => $data['message'] ?? '',
                        'clinic_id' => $data['clinic_id'] ?? null,
                        'created_at' => $data['created_at'] ?? null,
                        'time' => $this->formatTimeAgo($data['created_at'] ?? null),
                        'action' => null,
                    ];
                }
            }

            if (!empty($alerts)) {
                return $alerts;
            }

            $this->canUseMockFallback('getAlerts (no data)');
            return $this->getMockAlerts();
        } catch (\Exception $e) {
            \Log::error('Firestore getAlerts error: ' . $e->getMessage());
            return $this->getMockAlerts();
        }
    }

    protected function getMockAlerts()
    {
        // Generate alerts from real booking data if possible
        $firestore = $this->getFirestore();
        if ($firestore) {
            try {
                $alerts = [];
                $today = date('Y-m-d');

                // Use per-request cached bookings to avoid duplicate Firestore calls
                $allBookings = $this->getBookingsRaw();
                $pendingCount = 0;
                $awaitingPaymentCount = 0;

                foreach ($allBookings as $doc) {
                    if (!$doc->exists()) continue;
                    $data = $doc->data();

                    $bookingDate = null;
                    if (isset($data['scheduled_date'])) {
                        $sd = $data['scheduled_date'];
                        if ($sd instanceof \Google\Cloud\Core\Timestamp) {
                            $bookingDate = $sd->get()->format('Y-m-d');
                        } elseif (is_string($sd)) {
                            $bookingDate = substr($sd, 0, 10);
                        }
                    }

                    if ($bookingDate === $today) {
                        $status = $data['status'] ?? '';
                        if ($status === 'pending') $pendingCount++;
                        if ($status === 'acceptedAwaitingPayment') $awaitingPaymentCount++;
                    }
                }

                if ($pendingCount > 0) {
                    $alerts[] = [
                        'type' => 'warning',
                        'title' => __('messages.pending_bookings_alert', ['count' => $pendingCount], $pendingCount . ' حجوزات معلقة'),
                        'description' => $pendingCount . ' ' . __('messages.bookings_need_review', [], 'حجوزات بحاجة للمراجعة'),
                        'time' => __('messages.now', [], 'الآن'),
                        'action' => __('messages.review', [], 'مراجعة'),
                    ];
                }

                if ($awaitingPaymentCount > 0) {
                    $alerts[] = [
                        'type' => 'info',
                        'title' => __('messages.awaiting_payment_alert', ['count' => $awaitingPaymentCount], $awaitingPaymentCount . ' بانتظار الدفع'),
                        'description' => $awaitingPaymentCount . ' ' . __('messages.bookings_awaiting_payment', [], 'حجوزات بانتظار تأكيد الدفع'),
                        'time' => __('messages.now', [], 'الآن'),
                        'action' => __('messages.view', [], 'عرض'),
                    ];
                }

                if (empty($alerts)) {
                    $alerts[] = [
                        'type' => 'info',
                        'title' => __('messages.all_clear', [], 'لا توجد تنبيهات'),
                        'description' => __('messages.no_pending_alerts', [], 'جميع الحجوزات تعمل بشكل طبيعي'),
                        'time' => __('messages.now', [], 'الآن'),
                        'action' => null,
                    ];
                }

                return $alerts;
            } catch (\Exception $e) {
                \Log::error('getMockAlerts from bookings error: ' . $e->getMessage());
            }
        }

        return [
            [
                'type' => 'info',
                'title' => __('messages.all_clear', [], 'لا توجد تنبيهات'),
                'description' => __('messages.no_pending_alerts', [], 'جميع الحجوزات تعمل بشكل طبيعي'),
                'time' => __('messages.now', [], 'الآن'),
                'action' => null,
            ],
        ];
    }

    protected function formatTimeAgo($timestamp)
    {
        if (!$timestamp) {
            return 'Just now';
        }

        try {
            $time = $timestamp instanceof \Google\Cloud\Core\Timestamp
                ? $timestamp->get()->getTimestamp()
                : strtotime($timestamp);

            $diff = time() - $time;

            if ($diff < 60) {
                return 'Just now';
            } elseif ($diff < 3600) {
                $mins = floor($diff / 60);
                return $mins . 'm ago';
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return $hours . 'h ago';
            } else {
                $days = floor($diff / 86400);
                return $days . 'd ago';
            }
        } catch (\Exception $e) {
            return 'Just now';
        }
    }

    public function getSettings()
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            $this->canUseMockFallback('getSettings (firestore unavailable)');
            return $this->getMockSettings();
        }

        try {
            $doc = $firestore
                ->collection('settings')
                ->document('hospital_config')
                ->snapshot();

            if ($doc->exists()) {
                $data = $doc->data();
                return [
                    'hospital_name' => $data['hospital_name'] ?? 'Hospital',
                    'timezone' => $data['timezone'] ?? 'UTC',
                    'queue_display_mode' => $data['queue_display_mode'] ?? 'full',
                    'auto_advance_queue' => $data['auto_advance_queue'] ?? true,
                    'admin_email' => $data['admin_email'] ?? 'admin@hospital.com',
                    'phone' => $data['phone'] ?? '+1 (555) 000-0000',
                    'theme' => $data['theme'] ?? 'light',
                ];
            }

            return $this->canUseMockFallback('getSettings (document missing)')
                ? $this->getMockSettings()
                : $this->getMockSettings();
        } catch (\Exception $e) {
            \Log::error('Firestore getSettings error: ' . $e->getMessage());
            return $this->canUseMockFallback('getSettings (exception)')
                ? $this->getMockSettings()
                : $this->getMockSettings();
        }
    }

    protected function getMockSettings()
    {
        return [
            'hospital_name' => '',
            'timezone' => 'Asia/Riyadh',
            'queue_display_mode' => 'full',
            'auto_advance_queue' => true,
            'admin_email' => '',
            'phone' => '',
            'theme' => 'light',
        ];
    }

    public function getCurrentUser($userId = null)
    {
        $firestore = $this->getFirestore();
        if (!$firestore || !$userId) {
            return $this->canUseMockFallback('getCurrentUser (missing firestore/user)')
                ? $this->getMockCurrentUser()
                : [];
        }

        try {
            $doc = $firestore
                ->collection('users')
                ->document($userId)
                ->snapshot();

            if ($doc->exists()) {
                $data = $doc->data();
                return [
                    'id' => $doc->id(),
                    'name' => $data['name'] ?? '',
                    'role' => $data['role'] ?? 'user',
                    'clinic' => $data['clinic_name'] ?? 'All Clinics',
                    'avatar' => $data['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($data['name'] ?? 'User'),
                ];
            }

            return $this->canUseMockFallback('getCurrentUser (document missing)')
                ? $this->getMockCurrentUser()
                : [];
        } catch (\Exception $e) {
            \Log::error('Firestore getCurrentUser error: ' . $e->getMessage());
            return $this->canUseMockFallback('getCurrentUser (exception)')
                ? $this->getMockCurrentUser()
                : [];
        }
    }

    protected function getMockCurrentUser()
    {
        return [
            'name' => __('messages.unknown_user'),
            'role' => 'admin',
            'clinic' => '',
            'avatar' => 'https://ui-avatars.com/api/?name=U&background=4f46e5&color=fff',
        ];
    }

    public function getDashboardStats()
    {
        $firestore = $this->getFirestore();
        $defaultStats = [
            'total' => '0',
            'waiting' => '0',
            'avg_wait' => '0m',
            'no_show' => '0%',
            'total_trend' => '0%',
            'total_trend_type' => 'neutral',
            'waiting_trend' => '0%',
            'waiting_trend_type' => 'neutral',
        ];

        if (!$firestore) {
            return $defaultStats;
        }

        try {
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            // Use per-request cached bookings to avoid duplicate Firestore calls
            $allBookings = $this->getBookingsRaw();

            $total = 0;
            $waiting = 0;
            $noShowCount = 0;
            $yesterdayTotal = 0;
            $yesterdayWaiting = 0;

            foreach ($allBookings as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();

                    $bookingDate = null;
                    if (isset($data['scheduled_date'])) {
                        $sd = $data['scheduled_date'];
                        if ($sd instanceof \Google\Cloud\Core\Timestamp) {
                            $bookingDate = $sd->get()->format('Y-m-d');
                        } elseif (is_string($sd)) {
                            $bookingDate = substr($sd, 0, 10);
                        }
                    }

                    $status = $data['status'] ?? '';
                    $waitingStatuses = ['pending', 'confirmed', 'arrived', 'acceptedAwaitingPayment'];

                    if ($bookingDate === $today) {
                        $total++;
                        if (in_array($status, $waitingStatuses)) {
                            $waiting++;
                        }
                        if ($status === 'noShow') {
                            $noShowCount++;
                        }
                    } elseif ($bookingDate === $yesterday) {
                        $yesterdayTotal++;
                        if (in_array($status, $waitingStatuses)) {
                            $yesterdayWaiting++;
                        }
                    }
                }
            }

            // Compute trends
            $totalTrend = '0%';
            $totalTrendType = 'neutral';
            if ($yesterdayTotal > 0) {
                $diff = round((($total - $yesterdayTotal) / $yesterdayTotal) * 100);
                $totalTrend = ($diff >= 0 ? '+' : '') . $diff . '%';
                $totalTrendType = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'neutral');
            } elseif ($total > 0) {
                $totalTrend = '+100%';
                $totalTrendType = 'up';
            }

            $waitingTrend = '0%';
            $waitingTrendType = 'neutral';
            if ($yesterdayWaiting > 0) {
                $diff = round((($waiting - $yesterdayWaiting) / $yesterdayWaiting) * 100);
                $waitingTrend = ($diff >= 0 ? '+' : '') . $diff . '%';
                $waitingTrendType = $diff > 0 ? 'bad-up' : ($diff < 0 ? 'down' : 'neutral');
            }

            $noShowRate = $total > 0
                ? number_format(($noShowCount / $total) * 100, 1) . '%'
                : '0%';

            // Estimate avg wait based on waiting patients (rough: 10min each)
            $avgWait = $waiting > 0 ? ($waiting * 10) . 'm' : '0m';

            return [
                'total' => number_format($total),
                'waiting' => (string) $waiting,
                'avg_wait' => $avgWait,
                'no_show' => $noShowRate,
                'total_trend' => $totalTrend,
                'total_trend_type' => $totalTrendType,
                'waiting_trend' => $waitingTrend,
                'waiting_trend_type' => $waitingTrendType,
            ];
        } catch (\Exception $e) {
            \Log::error('Firestore getDashboardStats error: ' . $e->getMessage());
            return $defaultStats;
        }
    }

    /**
     * Update doctor's working hours and slot duration.
     * 
     * @param string $doctorId
     * @param array $scheduleData ['working_hours' => [...], 'slot_duration' => int]
     * @return void
     */
    public function updateDoctorSchedule(string $doctorId, array $scheduleData)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return;

        $updates = [
            ['path' => 'schedule', 'value' => $scheduleData['working_hours']],
            ['path' => 'working_hours', 'value' => $scheduleData['working_hours']],
            ['path' => 'updated_at', 'value' => new \DateTime()],
        ];

        if (isset($scheduleData['slot_duration'])) {
            $updates[] = ['path' => 'slot_duration', 'value' => $scheduleData['slot_duration']];
        }

        if (isset($scheduleData['duty_days'])) {
            $updates[] = ['path' => 'duty_days', 'value' => $scheduleData['duty_days']];
        }

        $firestore->collection('doctors')
            ->document($doctorId)
            ->update($updates);
    }

    /**
     * Update only duty_days for a doctor.
     */
    public function updateDutyDays(string $doctorId, array $dutyDays)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return;

        $firestore->collection('doctors')
            ->document($doctorId)
            ->update([
                ['path' => 'duty_days', 'value' => $dutyDays],
                ['path' => 'updated_at', 'value' => new \DateTime()],
            ]);
    }

    /**
     * Add unavailability period for a doctor.
     *
     * @param string $doctorId
     * @param array $data ['start' => DateTime, 'end' => DateTime, 'reason' => string]
     * @return string|null The ID of the created document
     */
    public function addDoctorUnavailability(string $doctorId, array $data)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            \Log::error('addDoctorUnavailability failed: Firestore unavailable');
            return null;
        }

        $docRef = $firestore->collection('doctors')
            ->document($doctorId)
            ->collection('unavailability')
            ->newDocument();

        $docRef->set([
            'start' => $data['start'],
            'end' => $data['end'],
            'reason' => $data['reason'] ?? '',
            'created_at' => new \DateTime(),
        ]);

        return $docRef->id();
    }

    /**
     * Remove unavailability period.
     * 
     * @param string $doctorId
     * @param string $unavailabilityId
     * @return void
     */
    public function removeDoctorUnavailability(string $doctorId, string $unavailabilityId)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return;

        $firestore->collection('doctors')
            ->document($doctorId)
            ->collection('unavailability')
            ->document($unavailabilityId)
            ->delete();
    }

    /**
     * Get doctor's unavailability periods.
     * 
     * @param string $doctorId
     * @return array
     */
    public function getDoctorUnavailability(string $doctorId)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        $snapshot = $firestore->collection('doctors')
            ->document($doctorId)
            ->collection('unavailability')
            ->orderBy('start', 'asc')
            ->documents();

        $periods = [];
        foreach ($snapshot as $doc) {
            if ($doc->exists()) {
                $data = $doc->data();
                $data['id'] = $doc->id();
                $periods[] = $data;
            }
        }
        
        return $periods;
    }

    public function getPatientDetails($id)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return $this->canUseMockFallback('getPatientDetails (firestore unavailable)')
                ? $this->getMockPatientDetails($id)
                : null;
        }

        try {
            $doc = $firestore->collection('users')->document($id)->snapshot();
            if (!$doc->exists()) return null;
            
            $data = $doc->data();
            $data['id'] = $doc->id();
            
            // Default structure to prevent view crashes
            $defaults = [
                'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($data['name'] ?? 'User'),
                'dob' => '-',
                'age' => '-',
                'gender' => '-',
                'blood_type' => '-',
                'emergency_contact' => ['name' => '-', 'relation' => '-', 'phone' => '-'],
                'vitals' => [
                    'bp' => ['value' => '-', 'unit' => 'mmHg'],
                    'hr' => ['value' => '-', 'unit' => 'bpm'],
                    'weight' => ['value' => '-', 'unit' => 'kg'],
                    'temp' => ['value' => '-', 'unit' => '°C'],
                ],
                'lab_results' => [],
                'history' => [],
                'allergies' => [],
                'conditions' => [],
                'documents' => []
            ];

            return array_merge($defaults, $data);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getMockPatientDetails($id) {
        return null;
    }
    public function getHospitalDashboardData()
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            \Log::error('getHospitalDashboardData failed: Firestore unavailable');
            return [
                'stats' => [
                    'total_patients' => 0,
                    'active_clinics' => 0,
                    'doctors_active' => 0,
                    'avg_wait_hospital' => '0m',
                ],
                'clinics' => [],
            ];
        }

        try {
            // Fetch all clinics
            $clinicsRef = $firestore->collection('clinics');
            $snapshot = $clinicsRef->documents();
            
            $clinics = [];
            $totalPatients = 0;
            $activeClinics = 0;
            $totalWaitMinutes = 0;
            $clinicsCount = 0;
            
            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                
                $data = $doc->data();
                $clinicId = $doc->id();
                
                // Fetch aggregate queue stats for this clinic (today)
                // This might be expensive to query strictly, so we might read a pre-aggregated 'stats' doc
                // or just query query_states for now.
                // For MVP, we can iterate doctors subcollection? 
                // Alternatively, we look for a 'status' field on the clinic doc itself if we maintained it.
                
                // Simplified: Assume clinic doc has some metadata or we mock the live stats part 
                // until we implement a background aggregator.
                // But we CAN query queue_states for this clinic.
                
                $queueStatesRef = $firestore->collection('queue_states')->where('clinic_id', '=', $clinicId);
                $queues = $queueStatesRef->documents();
                
                $clinicPatients = 0;
                $doctorsActive = 0;
                $waitMinutes = 0; // mocked or calculated
                
                foreach ($queues as $q) {
                    $qData = $q->data();
                    // check if date is today
                    if (str_contains($q->id(), date('Y-m-d'))) {
                         $lastIssued = $qData['last_issued'] ?? 0;
                         $nowServing = $qData['now_serving'] ?? 0;
                         $waiting = max(0, $lastIssued - $nowServing);
                         $clinicPatients += $waiting;
                         $doctorsActive++;
                    }
                }
                
                $clinics[] = [
                    'id' => $clinicId,
                    'name' => $data['name'] ?? 'Clinic',
                    'icon' => $data['icon'] ?? 'medical_services',
                    'icon_color' => $data['icon_color'] ?? 'blue',
                    'doctors_on_duty' => $doctorsActive, // from active queues
                    'patients_waiting' => $clinicPatients,
                    'avg_wait' => ($clinicPatients * 15) . 'm', // rough estimate
                    'status' => 'Running', // derive from queues
                    'alerts' => null,
                ];
                
                $totalPatients += $clinicPatients;
                $activeClinics++;
            }
            
            return [
                'stats' => [
                    'total_patients' => $totalPatients,
                    'active_clinics' => $activeClinics,
                    'doctors_active' => $activeClinics * 2, // approximation if not fully queried
                    'avg_wait_hospital' => '20m'
                ],
                'clinics' => $clinics
            ];

        } catch (\Exception $e) {
            return [
                 'stats' => ['total_patients'=>0, 'active_clinics'=>0, 'doctors_active'=>0, 'avg_wait_hospital'=>'0m'],
                 'clinics' => []
            ];
        }
    }
    // getAvailableSlots is defined below (near end of class)

    public function getPendingBookings(): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $docs = $firestore->collection('bookings')
                ->where('status', '==', 'pending')
                ->limit(50)
                ->documents();

            $bookings = [];
            $patientCache = [];
            $doctorCache = [];

            foreach ($docs as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();

                // Resolve patient name
                $patientName = $data['patient_name'] ?? '';
                if (empty($patientName) && isset($data['patient_id'])) {
                    $pid = $data['patient_id'];
                    if (!isset($patientCache[$pid])) {
                        $patientCache[$pid] = $this->getPatientDetails($pid)['name'] ?? 'Patient';
                    }
                    $patientName = $patientCache[$pid];
                }

                // Resolve doctor name
                $doctorName = $data['doctor_name'] ?? '';
                if (empty($doctorName) && isset($data['doctor_id'])) {
                    $did = $data['doctor_id'];
                    if (!isset($doctorCache[$did])) {
                        $detail = $this->getDoctorDetails($data['clinic_id'] ?? null, $did);
                        $doctorCache[$did] = $this->getLocalizedField($detail, 'name', 'Doctor');
                    }
                    $doctorName = $doctorCache[$did];
                }

                $bookings[] = [
                    'id' => $doc->id(),
                    'patient_name' => $patientName ?: 'Patient',
                    'doctor_name' => $doctorName ?: 'Doctor',
                    'clinic_name' => $data['clinic_name'] ?? '',
                    'scheduled_date' => $data['scheduled_date'] ?? null,
                    'created_at' => $data['created_at'] ?? null,
                    'status' => 'pending',
                ];
            }

            return $bookings;
        } catch (\Exception $e) {
            \Log::error('getPendingBookings error: ' . $e->getMessage());
            return [];
        }
    }

    public function getQueueData(?string $clinicId = null)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return $this->canUseMockFallback('getQueueData (firestore unavailable)')
                ? $this->getMockQueueData()
                : [
                    'stats' => [
                        'bookings_today' => 0,
                        'bookings_trend' => '0',
                        'arrived' => 0,
                        'arrived_trend' => '0',
                        'avg_wait' => '0 min',
                        'wait_trend' => '0%',
                    ],
                    'status_counts' => [
                        'pending' => 0,
                        'accepted' => 0,
                        'confirmed' => 0,
                        'arrived' => 0,
                        'completed' => 0,
                        'cancelled' => 0,
                        'noShow' => 0,
                        'other' => 0,
                    ],
                    'current_serving' => [
                        'id' => null,
                        'token' => '00',
                        'patient' => 'Queue unavailable',
                        'type' => '-',
                        'room' => '1',
                    ],
                    'next_up' => [],
                    'bookings' => [],
                    'skipped' => [],
                    'queue_state' => [
                        'clinic_id' => null,
                        'doctor_id' => null,
                        'date' => date('Y-m-d'),
                        'now_serving' => 0,
                        'last_issued' => 0,
                        'is_paused' => false,
                    ],
                ];
        }

        try {
            // Use cached bookings to avoid duplicate Firestore call
            $allBookingDocs = $this->getBookingsRaw();

            // Filter by clinic client-side from cached data
            $snapshot = [];
            foreach ($allBookingDocs as $doc) {
                if (!$doc->exists()) continue;
                if ($clinicId) {
                    $data = $doc->data();
                    if (($data['clinic_id'] ?? '') !== $clinicId) continue;
                }
                $snapshot[] = $doc;
            }

            // Get Queue State for Now Serving
            // Instead of O(C×D) nested loop scanning all clinics/doctors,
            // derive the active clinic/doctor from bookings data and fetch only that queue state
            $nowServing = 0;
            $lastIssued = 0;
            $activeClinicId = null;
            $activeDoctorId = null;
            $queueIsPaused = false;
            try {
                $dateKey = date('Y-m-d');

                // Find unique clinic/doctor pairs from today's bookings that have tokens
                $queuePairs = [];
                foreach ($snapshot as $doc) {
                    $data = $doc->data();
                    $cid = $data['clinic_id'] ?? '';
                    $did = $data['doctor_id'] ?? '';
                    $token = $data['token_number'] ?? 0;
                    if ($cid && $did && $token > 0) {
                        $key = "{$cid}_{$did}";
                        if (!isset($queuePairs[$key])) {
                            $queuePairs[$key] = ['clinic_id' => $cid, 'doctor_id' => $did];
                        }
                    }
                }

                // Fetch queue state only for active clinic/doctor pairs (typically 1-3, not C×D)
                foreach ($queuePairs as $pair) {
                    $queueDoc = $firestore->collection('clinics')
                        ->document($pair['clinic_id'])
                        ->collection('doctors')
                        ->document($pair['doctor_id'])
                        ->collection('dates')
                        ->document($dateKey)
                        ->snapshot();
                    if ($queueDoc->exists()) {
                        $qData = $queueDoc->data();
                        $serving = $qData['now_serving'] ?? 0;
                        if ($serving > $nowServing || !$activeClinicId) {
                            $nowServing = $serving;
                            $lastIssued = $qData['last_issued'] ?? 0;
                            $queueIsPaused = $qData['is_paused'] ?? false;
                            $activeClinicId = $pair['clinic_id'];
                            $activeDoctorId = $pair['doctor_id'];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Queue state scan failed: ' . $e->getMessage());
            }

            $bookings = [];
            $statusCounts = [
                'pending' => 0,
                'accepted' => 0,
                'confirmed' => 0,
                'arrived' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'noShow' => 0,
                'other' => 0,
            ];

            $nextUp = [];
            $skipped = [];
            $currentServing = [
                'id' => null,
                'token' => str_pad($nowServing, 2, '0', STR_PAD_LEFT),
                'patient' => __('messages.waiting_for_patient') ?? 'Waiting for patient...',
                'type' => '-',
                'room' => '1'
            ];

            $patientCache = [];
            $doctorCache = [];

            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();

                // Parse scheduled date (REST API returns strings, not Timestamp objects)
                $scheduledDate = null;
                $bookingTime = null;
                if (isset($data['scheduled_date'])) {
                    if ($data['scheduled_date'] instanceof \Google\Cloud\Core\Timestamp) {
                        $scheduledDate = $data['scheduled_date']->get()->format('Y-m-d H:i:s');
                    } else {
                        $scheduledDate = $data['scheduled_date'];
                    }
                    $bookingTime = strtotime($scheduledDate);
                }

                $status = $data['status'] ?? 'pending';

                // Map raw status to display status
                $formattedStatus = ucfirst($status);
                $rawStatus = $status;
                if ($status === 'acceptedAwaitingPayment') { $formattedStatus = 'Accepted'; $rawStatus = 'accepted'; }
                if ($status === 'cancelledByClinic' || $status === 'cancelledByUser' || $status === 'cancelled') { $formattedStatus = 'Cancelled'; $rawStatus = 'cancelled'; }
                if ($status === 'noShow' || $status === 'no_show') { $formattedStatus = 'No Show'; $rawStatus = 'noShow'; }
                if ($status === 'waiting' || $status === 'in_progress') { $formattedStatus = ucfirst(str_replace('_', ' ', $status)); }
                if ($data['is_reinserted'] ?? false) { $formattedStatus = 'Re-inserted'; }

                // Count statuses
                $countKey = match($rawStatus) {
                    'pending' => 'pending',
                    'accepted', 'acceptedAwaitingPayment' => 'accepted',
                    'confirmed' => 'confirmed',
                    'arrived' => 'arrived',
                    'completed' => 'completed',
                    'cancelled', 'cancelledByClinic', 'cancelledByUser' => 'cancelled',
                    'noShow', 'no_show' => 'noShow',
                    default => 'other',
                };
                $statusCounts[$countKey]++;

                $token = $data['token_number'] ?? 0;

                // Resolve patient name
                $patientName = $data['patient_name'] ?? null;
                if (!$patientName && isset($data['patient_id'])) {
                    $pid = $data['patient_id'];
                    if (!isset($patientCache[$pid])) {
                        try {
                            $patientCache[$pid] = $this->getPatientDetails($pid)['name'] ?? 'Patient';
                        } catch (\Exception $e) {
                            $patientCache[$pid] = 'Patient';
                        }
                    }
                    $patientName = $patientCache[$pid];
                }
                $patientName = $patientName ?: 'Guest';

                // Resolve doctor name
                $doctorName = $data['doctor_name'] ?? null;
                if (!$doctorName && isset($data['doctor_id'])) {
                    $did = $data['doctor_id'];
                    if (!isset($doctorCache[$did])) {
                        try {
                            $detail = $this->getDoctorDetails($data['clinic_id'] ?? '', $did);
                            $doctorCache[$did] = $this->getLocalizedField($detail, 'name', 'Doctor');
                        } catch (\Exception $e) {
                            $doctorCache[$did] = 'Doctor';
                        }
                    }
                    $doctorName = $doctorCache[$did];
                }

                $booking = [
                    'id' => $doc->id(),
                    'token' => $token > 0 ? '#' . str_pad($token, 2, '0', STR_PAD_LEFT) : '-',
                    'token_num' => $token,
                    'patient' => $patientName,
                    'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($patientName) . '&background=random',
                    'type' => $doctorName ?? ($data['type'] ?? 'General'),
                    'status' => $formattedStatus,
                    'raw_status' => $status,
                    'color' => $this->getStatusColor($status),
                    'time' => $bookingTime ? date('M d, h:i A', $bookingTime) : '-',
                    'is_arrived' => ($status === 'arrived' || isset($data['arrived_at'])),
                    'is_reinserted' => $data['is_reinserted'] ?? false,
                    'is_followup' => $data['is_followup'] ?? false,
                    'payment_note' => $data['payment_note'] ?? null,
                    'payment_status' => $data['payment_status'] ?? null,
                    'clinic_id' => $data['clinic_id'] ?? '',
                    'doctor_id' => $data['doctor_id'] ?? '',
                ];

                $bookings[] = $booking;

                // Identify Current Serving
                if ($token == $nowServing && $nowServing > 0 && in_array($status, ['confirmed', 'arrived'])) {
                    $currentServing = array_merge($booking, ['room' => '1']);
                }

                // Identify Next Up (confirmed/arrived tokens > now_serving)
                if (in_array($status, ['confirmed', 'arrived'])) {
                    if ($token > $nowServing && $nowServing > 0) {
                        $booking['wait'] = '~' . (($token - $nowServing) * 5) . ' min';
                        $nextUp[] = $booking;
                    } elseif (($data['is_reinserted'] ?? false) && $token <= $nowServing) {
                        $booking['wait'] = 'Priority';
                        array_unshift($nextUp, $booking);
                    }
                }

                // Skipped List
                if (in_array($status, ['noShow', 'no_show'])) {
                    $skipped[] = $booking;
                }
            }

            // Sort bookings: pending first, then by token desc, then by status
            usort($bookings, function($a, $b) {
                $order = ['Pending' => 0, 'Accepted' => 1, 'Confirmed' => 2, 'Arrived' => 3, 'Re-inserted' => 2, 'In progress' => 4, 'Waiting' => 5, 'Completed' => 6, 'No Show' => 7, 'Cancelled' => 8];
                $aOrder = $order[$a['status']] ?? 9;
                $bOrder = $order[$b['status']] ?? 9;
                if ($aOrder !== $bOrder) return $aOrder <=> $bOrder;
                return $b['token_num'] <=> $a['token_num'];
            });

            // Sort and limit Next Up
            usort($nextUp, function($a, $b) {
                $aRe = $a['is_reinserted'] ?? false;
                $bRe = $b['is_reinserted'] ?? false;
                if ($aRe && !$bRe) return -1;
                if (!$aRe && $bRe) return 1;
                return $a['token_num'] <=> $b['token_num'];
            });
            $nextUp = array_slice($nextUp, 0, 3);

            $totalBookings = count($bookings);

            return [
                'stats' => [
                    'bookings_today' => $totalBookings,
                    'bookings_trend' => '+' . $totalBookings,
                    'arrived' => $statusCounts['arrived'],
                    'arrived_trend' => '+' . $statusCounts['arrived'],
                    'avg_wait' => ($lastIssued - $nowServing) > 0 ? (($lastIssued - $nowServing) * 5) . ' min' : '0 min',
                    'wait_trend' => '0%',
                ],
                'status_counts' => $statusCounts,
                'current_serving' => $currentServing,
                'next_up' => $nextUp,
                'bookings' => $bookings,
                'skipped' => $skipped,
                'queue_state' => [
                    'clinic_id' => $activeClinicId,
                    'doctor_id' => $activeDoctorId,
                    'date' => date('Y-m-d'),
                    'now_serving' => $nowServing,
                    'last_issued' => $lastIssued,
                    'is_paused' => $queueIsPaused,
                ],
            ];

        } catch (\Exception $e) {
            \Log::error('getQueueData error: ' . $e->getMessage());
            return $this->canUseMockFallback('getQueueData (exception)')
                ? $this->getMockQueueData()
                : [
                    'stats' => [
                        'bookings_today' => 0,
                        'bookings_trend' => '0',
                        'arrived' => 0,
                        'arrived_trend' => '0',
                        'avg_wait' => '0 min',
                        'wait_trend' => '0%',
                    ],
                    'status_counts' => [
                        'pending' => 0,
                        'accepted' => 0,
                        'confirmed' => 0,
                        'arrived' => 0,
                        'completed' => 0,
                        'cancelled' => 0,
                        'noShow' => 0,
                        'other' => 0,
                    ],
                    'current_serving' => [
                        'id' => null,
                        'token' => '00',
                        'patient' => 'Queue unavailable',
                        'type' => '-',
                        'room' => '1',
                    ],
                    'next_up' => [],
                    'bookings' => [],
                    'skipped' => [],
                    'queue_state' => [
                        'clinic_id' => null,
                        'doctor_id' => null,
                        'date' => date('Y-m-d'),
                        'now_serving' => 0,
                        'last_issued' => 0,
                        'is_paused' => false,
                    ],
                ];
        }
    }

    public function recallPatient(string $bookingId)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return;
        
        $firestore->collection('bookings')->document($bookingId)->update([
            ['path' => 'last_recalled_at', 'value' => new \DateTime()]
        ]);
    }

    public function reinsertPatient(string $bookingId)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return;

        // Change status back to confirmed and flag as re-inserted
        $firestore->collection('bookings')->document($bookingId)->update([
            ['path' => 'status', 'value' => 'confirmed'],
            ['path' => 'is_reinserted', 'value' => true], 
            ['path' => 'updated_at', 'value' => new \DateTime()]
        ]);
    }

    /**
     * Get clinic details by ID
     */
    public function getClinic($id)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $doc = $firestore->collection('clinics')->document($id)->snapshot();
            if (!$doc->exists()) return null;
            
            $data = $doc->data();
            $data['id'] = $doc->id();
            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update clinic settings
     */
    public function updateClinic($id, array $data)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $firestore->collection('clinics')->document($id)->set($data, ['merge' => true]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getStatusColor($status) {

        return match($status) {
            'pending' => 'yellow',
            'acceptedAwaitingPayment' => 'blue',
            'confirmed' => 'green',
            'arrived' => 'green',
            'completed' => 'gray',
            'cancelled' => 'red',
            'cancelledByClinic' => 'red',
            'cancelledByUser' => 'red',
            'noShow' => 'red',
            default => 'gray'
        };
    }

    public function getMockQueueData()
    {
        return [
            'stats' => [
                'bookings_today' => 0,
                'bookings_trend' => '0',
                'arrived' => 0,
                'arrived_trend' => '0',
                'avg_wait' => '0 min',
                'wait_trend' => '0%'
            ],
            'status_counts' => [
                'pending' => 0,
                'accepted' => 0,
                'confirmed' => 0,
                'arrived' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'noShow' => 0,
                'other' => 0,
            ],
            'current_serving' => [
                'id' => null,
                'token' => '00',
                'patient' => 'Waiting for patient...',
                'type' => '-',
                'room' => '1'
            ],
            'next_up' => [],
            'bookings' => [],
            'skipped' => [],
            'queue_state' => [
                'clinic_id' => null,
                'doctor_id' => null,
                'date' => date('Y-m-d'),
                'now_serving' => 0,
                'last_issued' => 0,
                'is_paused' => false,
            ],
        ];
    }

    /**
     * Send queue proximity notifications to patients.
     * Notifies patients when their turn is approaching (remaining <= 2).
     * 
     * @param string $clinicId
     * @param string $doctorId
     * @param string $date (Y-m-d format)
     * @return array Number of notifications sent
     */
    public function sendQueueProximityNotifications(string $clinicId, string $doctorId, string $date): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return ['sent' => 0, 'error' => 'Firestore not available'];
        }

        try {
            // Get current queue state
            $queueRef = $firestore->collection('clinics')
                ->document($clinicId)
                ->collection('doctors')
                ->document($doctorId)
                ->collection('dates')
                ->document($date);
            
            $queueDoc = $queueRef->snapshot();
            
            if (!$queueDoc->exists()) {
                return ['sent' => 0, 'error' => 'Queue not found'];
            }

            $queueData = $queueDoc->data();
            $nowServing = $queueData['now_serving'] ?? 0;
            $lastIssued = $queueData['last_issued'] ?? 0;

            // Get all confirmed bookings for this queue
            $bookingsRef = $firestore->collection('bookings');
            $query = $bookingsRef
                ->where('clinic_id', '=', $clinicId)
                ->where('doctor_id', '=', $doctorId)
                ->where('status', '=', 'confirmed');
            
            $documents = $query->documents();
            
            $notificationsSent = 0;
            
            foreach ($documents as $doc) {
                if (!$doc->exists()) continue;
                
                $bookingData = $doc->data();
                $tokenNumber = $bookingData['token_number'] ?? 0;
                $userId = $bookingData['patient_id'] ?? $bookingData['user_id'] ?? null;
                
                if (!$userId || $tokenNumber <= 0) continue;
                
                // Calculate remaining
                $remaining = $tokenNumber - $nowServing;
                
                // Only notify if turn is approaching (remaining <= 2) or it's their turn
                if ($remaining <= 2 && $remaining >= 0) {
                    // Get user's FCM token
                    $userDoc = $firestore->collection('users')->document($userId)->snapshot();
                    
                    if ($userDoc->exists()) {
                        $userData = $userDoc->data();
                        $fcmToken = $userData['fcm_token'] ?? null;
                        
                        if ($fcmToken) {
                            $this->sendFCMNotification(
                                $fcmToken,
                                $remaining === 0 ? 'دورك الآن! 🎉' : 'اقترب دورك! ⏰',
                                $remaining === 0
                                    ? 'الرجاء التوجه لغرفة الفحص'
                                    : "متبقي {$remaining} أرقام قبل دورك",
                                [
                                    'type' => 'queue_proximity',
                                    'user_id' => $userId,
                                    'booking_id' => $doc->id(),
                                    'token_number' => (string)$tokenNumber,
                                    'now_serving' => (string)$nowServing,
                                    'remaining' => (string)$remaining,
                                ]
                            );
                            
                            $notificationsSent++;
                        }

                        // Also try SMS if phone exists and it's their turn
                        if ($remaining === 0 && isset($userData['phone'])) {
                            $this->sendSMS(
                                $userData['phone'],
                                "دورك الآن في العيادة! الرجاء التوجه لغرفة الفحص. رقمك: {$tokenNumber}"
                            );
                        }
                    }
                }
            }
            
            return [
                'sent' => $notificationsSent,
                'now_serving' => $nowServing,
                'last_issued' => $lastIssued,
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error sending queue proximity notifications: ' . $e->getMessage());
            return ['sent' => 0, 'error' => $e->getMessage()];
        }
    }

    // ─── Notification Methods ───

    /**
     * Get user's notifications from Firestore
     */
    public function getUserNotifications(string $userId, int $limit = 50): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $snapshot = $firestore->collection('notifications')
                ->where('user_id', '=', $userId)
                ->orderBy('created_at', 'DESC')
                ->limit($limit)
                ->documents();

            $notifications = [];
            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();

                // Format created_at for JSON
                if (isset($data['created_at'])) {
                    if ($data['created_at'] instanceof \Google\Cloud\Core\Timestamp) {
                        $data['created_at'] = $data['created_at']->get()->format('c');
                    }
                }

                $notifications[] = $data;
            }
            return $notifications;
        } catch (\Exception $e) {
            \Log::error('getUserNotifications error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark a single notification as read
     */
    public function markNotificationRead(string $userId, string $notificationId): void
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return;

        try {
            $firestore->collection('notifications')
                ->document($notificationId)
                ->update([
                    ['path' => 'is_read', 'value' => true],
                ]);
        } catch (\Exception $e) {
            \Log::error('markNotificationRead error: ' . $e->getMessage());
        }
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllNotificationsRead(string $userId): int
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return 0;

        try {
            $snapshot = $firestore->collection('notifications')
                ->where('user_id', '=', $userId)
                ->where('is_read', '=', false)
                ->documents();

            $count = 0;
            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                $doc->reference()->update([
                    ['path' => 'is_read', 'value' => true],
                ]);
                $count++;
            }
            return $count;
        } catch (\Exception $e) {
            \Log::error('markAllNotificationsRead error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Save FCM token for a user
     */
    public function saveFcmToken(string $userId, string $token): void
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return;

        try {
            // Save to user_fcm_tokens collection
            $firestore->collection('user_fcm_tokens')
                ->document($userId)
                ->set([
                    'token' => $token,
                    'updated_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                ], ['merge' => true]);

            // Also update user document
            $firestore->collection('users')
                ->document($userId)
                ->update([
                    ['path' => 'fcm_token', 'value' => $token],
                ]);
        } catch (\Exception $e) {
            \Log::error('saveFcmToken error: ' . $e->getMessage());
        }
    }

    /**
     * Store a notification in Firestore notifications collection
     */
    public function storeNotification(string $userId, string $title, string $body, string $type = 'general', array $data = []): ?string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $docRef = $firestore->collection('notifications')->add([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'is_read' => false,
                'created_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                'data' => $data,
            ]);
            return $docRef->id();
        } catch (\Exception $e) {
            \Log::error('storeNotification error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send FCM notification to a device and store in Firestore.
     *
     * @param string $fcmToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendFCMNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        try {
            // Store notification in Firestore for in-app display
            $userId = $data['user_id'] ?? null;
            $type = $data['type'] ?? 'general';

            // Map internal types to notification model types
            $typeMap = [
                'queue_proximity' => 'queueTurn',
                'payment_success' => 'payment',
                'payment_failed' => 'payment',
                'booking_confirmed' => 'booking',
                'booking_cancelled' => 'booking',
                'reminder' => 'reminder',
            ];
            $notificationType = $typeMap[$type] ?? 'general';

            if ($userId) {
                $this->storeNotification($userId, $title, $body, $notificationType, $data);
            }

            // Send push notification via FCM
            $messaging = $this->getMessaging();
            if (!$messaging) {
                \Log::warning("FCM: Messaging not initialized, notification stored in Firestore only");
                return $userId ? true : false;
            }

            $message = CloudMessage::new()
                ->withToken($fcmToken)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $messaging->send($message);

            \Log::info("FCM Notification Sent: {$title}", [
                'token' => $fcmToken,
                'data' => $data,
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Error sending FCM notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send SMS notification to a phone number.
     * 
     * @param string $phone
     * @param string $message
     * @return bool
     */
    public function sendSMS(string $phone, string $message): bool
    {
        try {
            $provider = strtolower((string) config('services.sms.provider', 'log'));

            if ($provider === 'twilio') {
                $sid = (string) config('services.sms.twilio.sid');
                $token = (string) config('services.sms.twilio.auth_token');
                $from = (string) (config('services.sms.twilio.from') ?: config('services.sms.from'));

                if ($sid === '' || $token === '' || $from === '') {
                    \Log::error('SMS provider twilio is selected but credentials are missing.');
                    return false;
                }

                $response = Http::asForm()
                    ->withBasicAuth($sid, $token)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'From' => $from,
                        'To' => $phone,
                        'Body' => $message,
                    ]);

                if ($response->successful()) {
                    return true;
                }

                \Log::error('Twilio SMS send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            if ($provider === 'unifonic') {
                $appSid = (string) config('services.sms.unifonic.app_sid');
                $sender = (string) (config('services.sms.unifonic.sender') ?: config('services.sms.from'));

                if ($appSid === '' || $sender === '') {
                    \Log::error('SMS provider unifonic is selected but credentials are missing.');
                    return false;
                }

                $response = Http::asForm()->post('https://api.unifonic.com/rest/Messages/Send', [
                    'AppSid' => $appSid,
                    'Recipient' => $phone,
                    'Body' => $message,
                    'SenderID' => $sender,
                ]);

                if ($response->successful()) {
                    return true;
                }

                \Log::error('Unifonic SMS send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            \Log::warning('SMS_PROVIDER is log/unknown; SMS not sent to gateway', [
                'provider' => $provider,
                'phone' => $phone,
                'message' => $message,
            ]);

            return $provider === 'log';
        } catch (\Exception $e) {
            \Log::error('Error sending SMS: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get doctor details from subcollection
     */
    /**
     * Get doctor by ID (convenience wrapper for mobile API)
     */
    public function getDoctorById(string $doctorId): ?array
    {
        return $this->getDoctorDetails(null, $doctorId);
    }

    public function getDoctorDetails($clinicId, $doctorId)
    {
        if (!$this->firestoreClient) {
            return ['name' => 'Unknown Doctor', 'name_en' => 'Unknown Doctor'];
        }

        try {
            // Doctors are in root 'doctors' collection (not subcollection)
            $doc = $this->firestoreClient
                ->collection('doctors')
                ->document($doctorId)
                ->snapshot();

            if ($doc->exists()) {
                $data = $doc->data();
                $data['id'] = $doc->id();
                return $data;
            }

            return ['name' => 'Unknown Doctor', 'name_en' => 'Unknown Doctor'];
        } catch (\Exception $e) {
            \Log::error('getDoctorDetails error: ' . $e->getMessage());
            return ['name' => 'Unknown Doctor', 'name_en' => 'Unknown Doctor'];
        }
    }

    /**
     * Find a doctor record by user_id (for doctor login).
     * Searches the doctors collection for a document where user_id matches.
     *
     * @param string $userId The user ID from the users collection
     * @return array|null The doctor record with 'id', or null if not found
     */
    public function getDoctorByUserId(string $userId): ?array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $doctors = $this->getCollectionCached('doctors', 60);
            foreach ($doctors as $doc) {
                $data = $doc->data();
                if (($data['user_id'] ?? null) === $userId) {
                    $data['id'] = $doc->id();
                    return $data;
                }
            }
            return null;
        } catch (\Exception $e) {
            \Log::error('getDoctorByUserId error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get bookings for a specific doctor, optionally filtered by date.
     * Each booking is enriched with patient medical details.
     *
     * @param string $doctorId
     * @param string|null $date Y-m-d format
     * @return array
     */
    public function getBookingsForDoctor(string $doctorId, ?string $date = null): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $allBookings = $this->getBookingsRaw();
            $today = $date ?? date('Y-m-d');
            $filtered = [];

            foreach ($allBookings as $doc) {
                $data = $doc->data();
                if (($data['doctor_id'] ?? '') !== $doctorId) continue;

                // Filter by date
                $scheduledDate = $data['scheduled_date'] ?? null;
                if ($scheduledDate) {
                    if ($scheduledDate instanceof \Google\Cloud\Core\Timestamp) {
                        $bookingDate = $scheduledDate->get()->format('Y-m-d');
                    } else {
                        $bookingDate = date('Y-m-d', strtotime($scheduledDate));
                    }
                    if ($bookingDate !== $today) continue;
                }

                $data['id'] = $doc->id();

                // Enrich with patient details
                $patientId = $data['patient_id'] ?? null;
                if ($patientId) {
                    $patient = $this->getPatientDetails($patientId);
                    $data['patient_weight'] = $patient['weight'] ?? null;
                    $data['patient_height'] = $patient['height'] ?? null;
                    $data['patient_blood_pressure'] = $patient['blood_pressure'] ?? null;
                    $data['patient_allergies'] = $patient['allergies'] ?? null;
                }

                $filtered[] = $data;
            }

            // Sort by token_number
            usort($filtered, function ($a, $b) {
                return ($a['token_number'] ?? 999) <=> ($b['token_number'] ?? 999);
            });

            return $filtered;
        } catch (\Exception $e) {
            \Log::error('getBookingsForDoctor error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get queue state for a specific doctor on a given date.
     *
     * @param string $clinicId
     * @param string $doctorId
     * @param string $date Y-m-d format
     * @return array Queue state: now_serving, last_issued, is_paused, remaining
     */
    public function getDoctorQueueState(string $clinicId, string $doctorId, string $date): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return ['now_serving' => 0, 'last_issued' => 0, 'is_paused' => false, 'remaining' => 0];

        try {
            $queueDoc = $firestore->collection('clinics')
                ->document($clinicId)
                ->collection('doctors')
                ->document($doctorId)
                ->collection('dates')
                ->document($date)
                ->snapshot();

            if ($queueDoc->exists()) {
                $data = $queueDoc->data();
                $nowServing = $data['now_serving'] ?? 0;
                $lastIssued = $data['last_issued'] ?? 0;
                return [
                    'now_serving' => $nowServing,
                    'last_issued' => $lastIssued,
                    'is_paused' => $data['is_paused'] ?? false,
                    'remaining' => max(0, $lastIssued - $nowServing),
                ];
            }

            return ['now_serving' => 0, 'last_issued' => 0, 'is_paused' => false, 'remaining' => 0];
        } catch (\Exception $e) {
            \Log::error('getDoctorQueueState error: ' . $e->getMessage());
            return ['now_serving' => 0, 'last_issued' => 0, 'is_paused' => false, 'remaining' => 0];
        }
    }

    /**
     * Get clinic by ID
     */
    public function getClinicById($clinicId)
    {
        if (!$this->firestoreClient) {
            return ['name' => 'Unknown Clinic', 'name_en' => 'Unknown Clinic'];
        }

        try {
            $doc = $this->firestoreClient
                ->collection('clinics')
                ->document($clinicId)
                ->snapshot();

            if ($doc->exists()) {
                $data = $doc->data();
                $data['id'] = $doc->id();
                return $data;
            }

            return ['name' => 'Unknown Clinic', 'name_en' => 'Unknown Clinic'];
        } catch (\Exception $e) {
            \Log::error('getClinicById error: ' . $e->getMessage());
            return ['name' => 'Unknown Clinic', 'name_en' => 'Unknown Clinic'];
        }
    }

    /**
     * Get all clinics with full Firestore fields for mobile API
     */
    public function getClinicsFull(): array
    {
        if (!$this->firestoreClient) {
            return [];
        }

        try {
            $snapshot = $this->firestoreClient->collection('clinics')->documents();
            $clinics = [];
            foreach ($snapshot as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $data['id'] = $doc->id();
                    // Convert GeoPoint to lat/lng array
                    if (isset($data['location']) && is_object($data['location'])) {
                        $data['location'] = [
                            'latitude' => $data['location']->latitude(),
                            'longitude' => $data['location']->longitude(),
                        ];
                    }
                    $clinics[] = $data;
                }
            }
            return $clinics;
        } catch (\Exception $e) {
            \Log::error('getClinicsFull error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get clinics filtered by specialty
     */
    public function getClinicsBySpecialty(string $specialty): array
    {
        if (!$this->firestoreClient) {
            return [];
        }

        try {
            $snapshot = $this->firestoreClient->collection('clinics')->documents();
            $clinics = [];
            foreach ($snapshot as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $docSpecialty = $data['specialty'] ?? '';
                    if (strtolower($docSpecialty) === strtolower($specialty)) {
                        $data['id'] = $doc->id();
                        if (isset($data['location']) && is_object($data['location'])) {
                            $data['location'] = [
                                'latitude' => $data['location']->latitude(),
                                'longitude' => $data['location']->longitude(),
                            ];
                        }
                        $clinics[] = $data;
                    }
                }
            }
            return $clinics;
        } catch (\Exception $e) {
            \Log::error('getClinicsBySpecialty error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get doctors filtered by specialty
     */
    public function getDoctorsBySpecialty(string $specialty): array
    {
        if (!$this->firestoreClient) {
            return [];
        }

        try {
            $snapshot = $this->firestoreClient->collection('doctors')->documents();
            $doctors = [];
            foreach ($snapshot as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $docSpecialty = $data['specialty'] ?? '';
                    if (strtolower($docSpecialty) === strtolower($specialty)) {
                        $data['id'] = $doc->id();
                        $doctors[] = $data;
                    }
                }
            }
            return $doctors;
        } catch (\Exception $e) {
            \Log::error('getDoctorsBySpecialty error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search across clinics, doctors, and hospitals by query string
     */
    public function searchAll(string $query): array
    {
        $results = ['clinics' => [], 'doctors' => [], 'hospitals' => []];
        
        if (!$this->firestoreClient || empty(trim($query))) {
            return $results;
        }

        $lowerQuery = mb_strtolower($query);

        try {
            // Search clinics (use cached collection to avoid fresh Firestore scan)
            $clinicsSnapshot = $this->getClinicsRaw();
            foreach ($clinicsSnapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();

                $name = mb_strtolower($data['name'] ?? '');
                $nameEn = mb_strtolower($data['name_en'] ?? '');
                $specialty = mb_strtolower($data['specialty'] ?? '');
                $address = mb_strtolower($data['address'] ?? '');

                if (str_contains($name, $lowerQuery) || str_contains($nameEn, $lowerQuery) ||
                    str_contains($specialty, $lowerQuery) || str_contains($address, $lowerQuery)) {
                    if (isset($data['location']) && is_object($data['location'])) {
                        $data['location'] = [
                            'latitude' => $data['location']->latitude(),
                            'longitude' => $data['location']->longitude(),
                        ];
                    }
                    $results['clinics'][] = $data;
                }
            }

            // Search doctors (use cached collection)
            $doctorsSnapshot = $this->getDoctorsRaw();
            foreach ($doctorsSnapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();

                $name = mb_strtolower($data['name'] ?? '');
                $nameEn = mb_strtolower($data['name_en'] ?? '');
                $specialty = mb_strtolower($data['specialty'] ?? '');

                if (str_contains($name, $lowerQuery) || str_contains($nameEn, $lowerQuery) ||
                    str_contains($specialty, $lowerQuery)) {
                    $results['doctors'][] = $data;
                }
            }

            // Search hospitals (use cached collection)
            $hospitalsSnapshot = $this->getCollectionCached('hospitals', 300);
            foreach ($hospitalsSnapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();
                
                $name = mb_strtolower($data['name'] ?? '');
                $nameEn = mb_strtolower($data['name_en'] ?? '');
                $address = mb_strtolower($data['address'] ?? '');
                
                if (str_contains($name, $lowerQuery) || str_contains($nameEn, $lowerQuery) || 
                    str_contains($address, $lowerQuery)) {
                    if (isset($data['location']) && is_object($data['location'])) {
                        $data['location'] = [
                            'latitude' => $data['location']->latitude(),
                            'longitude' => $data['location']->longitude(),
                        ];
                    }
                    $results['hospitals'][] = $data;
                }
            }

            return $results;
        } catch (\Exception $e) {
            \Log::error('searchAll error: ' . $e->getMessage());
            return $results;
        }
    }

    /**
     * Get user's bookings
     */
    public function getUserBookings($userId, $locale = 'ar')
    {
        if (!$this->firestoreClient) {
            return [];
        }

        try {
            $snapshot = $this->firestoreClient
                ->collection('bookings')
                ->where('patient_id', '==', $userId)
                ->documents();

            $bookings = [];
            foreach ($snapshot as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $data['id'] = $doc->id();

                    // Add localized clinic and doctor names
                    if (isset($data['clinic_id'])) {
                        $clinic = $this->getClinicById($data['clinic_id']);
                        $data['clinic_name'] = $this->getLocalizedField($clinic, 'name', 'Unknown Clinic');
                    }

                    if (isset($data['doctor_id']) && isset($data['clinic_id'])) {
                        $doctor = $this->getDoctorDetails($data['clinic_id'], $data['doctor_id']);
                        $data['doctor_name'] = $this->getLocalizedField($doctor, 'name', 'Unknown Doctor');
                    }

                    $bookings[] = $data;
                }
            }

            return $bookings;
        } catch (\Exception $e) {
            \Log::error('getUserBookings error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get booking details
     */
    public function getBookingDetails($bookingId, $locale = 'ar')
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $doc = $this->firestoreClient
                ->collection('bookings')
                ->document($bookingId)
                ->snapshot();

            if (!$doc->exists()) {
                throw new \Exception('Booking not found');
            }

            $data = $doc->data();
            $data['id'] = $doc->id();

            // Add localized clinic and doctor names
            if (isset($data['clinic_id'])) {
                $clinic = $this->getClinicById($data['clinic_id']);
                $data['clinic_name'] = $this->getLocalizedField($clinic, 'name', 'Unknown Clinic');
            }

            if (isset($data['doctor_id']) && isset($data['clinic_id'])) {
                $doctor = $this->getDoctorDetails($data['clinic_id'], $data['doctor_id']);
                $data['doctor_name'] = $this->getLocalizedField($doctor, 'name', 'Unknown Doctor');
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('getBookingDetails error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new booking
     */
    public function createBooking(array $data)
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $docRef = $this->firestoreClient
                ->collection('bookings')
                ->add($data);

            $this->invalidateBookingCaches();
            return $docRef->id();
        } catch (\Exception $e) {
            \Log::error('createBooking error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a booking from mobile app with all fields
     */
    public function createMobileBooking(array $data)
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $scheduledDate = new \DateTime($data['scheduled_date']);
            $now = new \DateTime();

            $isFollowup = $data['is_followup'] ?? false;
            $paymentNote = $isFollowup
                ? null
                : 'الدفع عند الوصول - لن يتم عرض رقم الدور الا بعد تأكيد الدفع';

            $bookingData = [
                    'patient_id' => $data['patient_id'],
                    'clinic_id' => $data['clinic_id'],
                    'doctor_id' => $data['doctor_id'],
                    'scheduled_date' => $scheduledDate,
                    'notes' => $data['notes'] ?? '',
                    'family_member_ids' => $data['family_member_ids'] ?? [],
                    'includes_self' => $data['includes_self'] ?? true,
                    'doctor_name' => $data['doctor_name'] ?? '',
                    'patient_name' => $data['patient_name'] ?? '',
                    'status' => $data['status'] ?? 'pending',
                    'payment_status' => $data['payment_status'] ?? 'unpaid',
                    'payment_note' => $paymentNote,
                    'token_number' => null,
                    'is_followup' => $isFollowup,
                    'treatment_plan_id' => $data['treatment_plan_id'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

            // Add coupon data if present
            if (!empty($data['coupon_code'])) {
                $bookingData['coupon_code'] = $data['coupon_code'];
                $bookingData['discount_amount'] = (float)($data['discount_amount'] ?? 0);
            }

            $docRef = $this->firestoreClient
                ->collection('bookings')
                ->add($bookingData);

            $this->invalidateBookingCaches();
            return $docRef->id();
        } catch (\Exception $e) {
            \Log::error('createMobileBooking error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking($bookingId, $reason = '')
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $this->firestoreClient
                ->collection('bookings')
                ->document($bookingId)
                ->update([
                    ['path' => 'status', 'value' => 'cancelledByUser'],
                    ['path' => 'cancellation_reason', 'value' => $reason],
                    ['path' => 'updated_at', 'value' => new \DateTime()],
                ]);
            $this->invalidateBookingCaches();
        } catch (\Exception $e) {
            \Log::error('cancelBooking error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reschedule a booking to a new date
     */
    public function rescheduleBooking($bookingId, $newDate)
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $scheduledDate = new \Google\Cloud\Core\Timestamp(new \DateTime($newDate));
            $this->firestoreClient
                ->collection('bookings')
                ->document($bookingId)
                ->update([
                    ['path' => 'scheduled_date', 'value' => $scheduledDate],
                    ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
                ]);
            $this->invalidateBookingCaches();
        } catch (\Exception $e) {
            \Log::error('rescheduleBooking error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark booking as arrived
     */
    public function markArrived($bookingId)
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $this->firestoreClient
                ->collection('bookings')
                ->document($bookingId)
                ->update([
                    ['path' => 'status', 'value' => 'arrived'],
                    ['path' => 'arrived_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
                    ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
                ]);
            $this->invalidateBookingCaches();
        } catch (\Exception $e) {
            \Log::error('markArrived error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Confirm cash payment for a booking and assign token number.
     * Used by reception staff to record in-person cash payments.
     *
     * @param string $bookingId Booking document ID
     * @param string $confirmedBy User ID/name of the staff member confirming payment
     * @return int The assigned token number
     * @throws \Exception If booking not found, wrong status, or Firestore error
     */
    public function confirmCashPayment(string $bookingId, string $confirmedBy): int
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $firestore = $this->firestoreClient;
            $bookingRef = $firestore->collection('bookings')->document($bookingId);
            $booking = $bookingRef->snapshot();

            if (!$booking->exists()) {
                throw new \Exception(__('messages.booking_not_found'));
            }

            $bookingData = $booking->data();
            $currentStatus = $bookingData['status'] ?? '';

            if ($currentStatus !== 'acceptedAwaitingPayment') {
                throw new \Exception(__('messages.cannot_confirm_payment') . ' - ' . __('messages.status') . ': ' . $currentStatus);
            }

            // Get queue state and assign next token
            $clinicId = $bookingData['clinic_id'];
            $doctorId = $bookingData['doctor_id'];
            $scheduledDate = $bookingData['scheduled_date']->toDateTime();
            $dateKey = $scheduledDate->format('Y-m-d');

            $queueRef = $firestore->collection('clinics')
                ->document($clinicId)
                ->collection('doctors')
                ->document($doctorId)
                ->collection('dates')
                ->document($dateKey);

            $queueDoc = $queueRef->snapshot();
            $lastIssued = $queueDoc->exists() ? ($queueDoc->data()['last_issued'] ?? 0) : 0;
            $newTokenNumber = $lastIssued + 1;

            // Update queue state
            $isPaused = $queueDoc->exists() ? ($queueDoc->data()['is_paused'] ?? false) : false;
            $queueRef->set([
                'last_issued' => $newTokenNumber,
                'now_serving' => $queueDoc->exists() ? ($queueDoc->data()['now_serving'] ?? 0) : 0,
                'is_paused' => $isPaused,
                'status' => $isPaused ? 'paused' : 'running',
                'updated_at' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            ], ['merge' => true]);

            // Update booking: confirmed + cash payment + token
            $bookingRef->update([
                ['path' => 'status', 'value' => 'confirmed'],
                ['path' => 'payment_status', 'value' => 'cash'],
                ['path' => 'payment_confirmed_by', 'value' => $confirmedBy],
                ['path' => 'token_number', 'value' => $newTokenNumber],
                ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
            ]);

            $this->invalidateBookingCaches();

            return $newTokenNumber;
        } catch (\Exception $e) {
            \Log::error('confirmCashPayment error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get queue state for clinic/doctor/date
     */
    public function getQueueState($clinicId, $doctorId, $date)
    {
        if (!$this->firestoreClient) {
            return ['now_serving' => 0, 'last_issued' => 0, 'status' => 'running'];
        }

        try {
            $doc = $this->firestoreClient
                ->collection('clinics')
                ->document($clinicId)
                ->collection('doctors')
                ->document($doctorId)
                ->collection('dates')
                ->document($date)
                ->snapshot();

            if ($doc->exists()) {
                return $doc->data();
            }

            return ['now_serving' => 0, 'last_issued' => 0, 'status' => 'running'];
        } catch (\Exception $e) {
            \Log::error('getQueueState error: ' . $e->getMessage());
            return ['now_serving' => 0, 'last_issued' => 0, 'status' => 'running'];
        }
    }

    /**
     * Get user profile from Firestore
     */
    public function getUserProfile($userId)
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $doc = $this->firestoreClient
                ->collection('users')
                ->document($userId)
                ->snapshot();

            if (!$doc->exists()) {
                throw new \Exception('User profile not found');
            }

            return $doc->data();
        } catch (\Exception $e) {
            \Log::error('getUserProfile error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create user profile if missing, otherwise update existing profile.
     *
     * @param string $userId
     * @param array $data
     * @return array
     */
    public function upsertUserProfile(string $userId, array $data): array
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        $allowedFields = ['name', 'phone', 'email', 'photo_url', 'locale'];
        $profileData = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $profileData[$field] = $data[$field];
            }
        }

        try {
            $docRef = $this->firestoreClient
                ->collection('users')
                ->document($userId);

            $snapshot = $docRef->snapshot();

            if ($snapshot->exists()) {
                $updates = [];
                foreach ($profileData as $key => $value) {
                    $updates[] = ['path' => $key, 'value' => $value];
                }
                $updates[] = ['path' => 'updated_at', 'value' => new \DateTime()];

                $docRef->update($updates);
                $updated = $docRef->snapshot();
                return $updated->data();
            }

            $newProfile = array_merge([
                'uid' => $userId,
                'role' => 'patient',
                'locale' => 'ar',
                'family_members' => [],
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            ], $profileData);

            $created = $this->firestoreClient->createDocument('users', $newProfile, $userId);
            if (!$created) {
                throw new \Exception('Failed to create user profile');
            }

            $createdSnapshot = $docRef->snapshot();
            return $createdSnapshot->data();
        } catch (\Exception $e) {
            \Log::error('upsertUserProfile error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update user profile
     */
    public function updateUserProfile($userId, array $data)
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $updates = [];
            foreach ($data as $key => $value) {
                $updates[] = ['path' => $key, 'value' => $value];
            }
            $updates[] = ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())];

            $this->firestoreClient
                ->collection('users')
                ->document($userId)
                ->update($updates);
        } catch (\Exception $e) {
            \Log::error('updateUserProfile error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add family member
     * 
     * @param string $userId User ID
     * @param array $memberData Member data
     * @return string The new member's ID
     */
    public function addFamilyMember($userId, array $memberData): string
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            try {
                $profile = $this->getUserProfile($userId);
            } catch (\Exception $e) {
                // Auto-create profile if it doesn't exist
                \Log::info("Auto-creating profile for user {$userId} during addFamilyMember");
                $profile = $this->upsertUserProfile($userId, ['name' => 'User']);
            }
            $familyMembers = $profile['family_members'] ?? [];

            // Generate unique ID for member
            $memberId = uniqid('member_');
            $memberData['id'] = $memberId;
            $memberData['created_at'] = new \Google\Cloud\Core\Timestamp(new \DateTime());

            $familyMembers[] = $memberData;

            $this->firestoreClient
                ->collection('users')
                ->document($userId)
                ->update([
                    ['path' => 'family_members', 'value' => $familyMembers],
                ]);

            return $memberId;
        } catch (\Exception $e) {
            \Log::error('addFamilyMember error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete family member
     */
    public function deleteFamilyMember($userId, $memberId)
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            try {
                $profile = $this->getUserProfile($userId);
            } catch (\Exception $e) {
                throw new \Exception('Cannot delete family member: user profile not found');
            }
            $familyMembers = $profile['family_members'] ?? [];

            // Remove member with matching ID
            $familyMembers = array_filter($familyMembers, function ($member) use ($memberId) {
                return ($member['id'] ?? '') !== $memberId;
            });

            // Reset array keys
            $familyMembers = array_values($familyMembers);

            $this->firestoreClient
                ->collection('users')
                ->document($userId)
                ->update([
                    ['path' => 'family_members', 'value' => $familyMembers],
                ]);
        } catch (\Exception $e) {
            \Log::error('deleteFamilyMember error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user by email from Firestore users collection.
     * Used for dashboard authentication.
     * 
     * @param string $email
     * @return array|null User data with id, or null if not found
     */
    public function getUserByEmail(string $email): ?array
    {
        if (!$this->firestoreClient) {
            return null;
        }

        try {
            $snapshot = $this->firestoreClient
                ->collection('users')
                ->where('email', '=', $email)
                ->limit(1)
                ->documents();

            foreach ($snapshot as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $data['id'] = $doc->id();
                    return $data;
                }
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('getUserByEmail error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get available time slots for doctor
     */
    public function getAvailableSlots($clinicId, $doctorId, $date)
    {
        if (!$this->firestoreClient) {
            return [];
        }

        try {
            // Check if the clinic is closed for a holiday on this date
            if ($this->isClinicHoliday($clinicId, $date)) {
                return [];
            }

            // Get doctor's schedule
            $doctor = $this->getDoctorDetails($clinicId, $doctorId);
            $schedule = $doctor['schedule'] ?? [];
            $slotDuration = (int)($doctor['slot_duration'] ?? 30);

            // Get day of week
            $dayOfWeek = strtolower(date('l', strtotime($date)));

            if (!isset($schedule[$dayOfWeek])) {
                return [];
            }

            $daySchedule = $schedule[$dayOfWeek];

            // Build time ranges supporting both new dual-session format (am/pm) and old single-session format (enabled/open/close)
            $timeRanges = [];
            if (isset($daySchedule['am_active']) || isset($daySchedule['pm_active'])) {
                // New AM/PM dual-session format
                if (!empty($daySchedule['am_active'])) {
                    $timeRanges[] = [
                        'label' => 'morning',
                        'start' => $daySchedule['am_start'] ?? '08:00',
                        'end' => $daySchedule['am_end'] ?? '12:00',
                    ];
                }
                if (!empty($daySchedule['pm_active'])) {
                    $timeRanges[] = [
                        'label' => 'evening',
                        'start' => $daySchedule['pm_start'] ?? '16:00',
                        'end' => $daySchedule['pm_end'] ?? '21:00',
                    ];
                }
            } elseif (!empty($daySchedule['enabled'])) {
                // Legacy single-session format (backward compatibility)
                $timeRanges[] = [
                    'label' => 'all_day',
                    'start' => $daySchedule['open'] ?? $daySchedule['start'] ?? '09:00',
                    'end' => $daySchedule['close'] ?? $daySchedule['end'] ?? '17:00',
                ];
            }

            if (empty($timeRanges)) {
                return [];
            }

            // Get existing bookings for that day
            $bookingsSnapshot = $this->firestoreClient
                ->collection('bookings')
                ->where('clinic_id', '==', $clinicId)
                ->where('doctor_id', '==', $doctorId)
                ->documents();

            $bookedSlots = [];
            foreach ($bookingsSnapshot as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $scheduledDate = $data['scheduled_date'] ?? null;
                    if (!$scheduledDate) continue;

                    // REST client returns timestamps as ISO 8601 strings
                    $timestamp = is_string($scheduledDate) ? strtotime($scheduledDate) : $scheduledDate;
                    if (!$timestamp) continue;

                    $bookingDate = date('Y-m-d', $timestamp);
                    if ($bookingDate === $date && !in_array($data['status'] ?? '', ['cancelledByClinic', 'cancelledByUser'])) {
                        $bookingTime = date('H:i', $timestamp);
                        $bookedSlots[] = $bookingTime;
                    }
                }
            }

            // Generate slots for each active session using the doctor's configured slot duration
            $slots = [];
            foreach ($timeRanges as $range) {
                $currentTime = strtotime($range['start']);
                $endTimeStamp = strtotime($range['end']);

                while ($currentTime < $endTimeStamp) {
                    $timeSlot = date('H:i', $currentTime);
                    $slots[] = [
                        'time' => $timeSlot,
                        'available' => !in_array($timeSlot, $bookedSlots),
                        'session' => $range['label'],
                    ];
                    $currentTime = strtotime("+{$slotDuration} minutes", $currentTime);
                }
            }

            return $slots;
        } catch (\Exception $e) {
            \Log::error('getAvailableSlots error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if the current time falls within a doctor's working hours for today.
     *
     * @param string $clinicId
     * @param string $doctorId
     * @return array ['within_hours' => bool, 'message' => string, 'sessions' => array]
     */
    public function isWithinWorkingHours(string $clinicId, string $doctorId): array
    {
        try {
            $doctor = $this->getDoctorDetails($clinicId, $doctorId);
            $schedule = $doctor['schedule'] ?? [];

            $dayOfWeek = strtolower(date('l'));
            $now = date('H:i');

            if (!isset($schedule[$dayOfWeek])) {
                return $this->buildOutsideHoursResult([]);
            }

            $daySchedule = $schedule[$dayOfWeek];
            $sessions = [];
            $withinHours = false;

            if (isset($daySchedule['am_active']) || isset($daySchedule['pm_active'])) {
                // New dual-session format
                if (!empty($daySchedule['am_active'])) {
                    $amStart = $daySchedule['am_start'] ?? '08:00';
                    $amEnd = $daySchedule['am_end'] ?? '12:00';
                    $sessions[] = ['label' => 'morning', 'start' => $amStart, 'end' => $amEnd];
                    if ($now >= $amStart && $now < $amEnd) {
                        $withinHours = true;
                    }
                }
                if (!empty($daySchedule['pm_active'])) {
                    $pmStart = $daySchedule['pm_start'] ?? '16:00';
                    $pmEnd = $daySchedule['pm_end'] ?? '21:00';
                    $sessions[] = ['label' => 'evening', 'start' => $pmStart, 'end' => $pmEnd];
                    if ($now >= $pmStart && $now < $pmEnd) {
                        $withinHours = true;
                    }
                }
            } elseif (!empty($daySchedule['enabled'])) {
                // Legacy single-session format
                $start = $daySchedule['open'] ?? '08:00';
                $end = $daySchedule['close'] ?? '17:00';
                $sessions[] = ['label' => 'all_day', 'start' => $start, 'end' => $end];
                if ($now >= $start && $now < $end) {
                    $withinHours = true;
                }
            }

            if ($withinHours) {
                return ['within_hours' => true, 'message' => '', 'sessions' => $sessions];
            }

            return $this->buildOutsideHoursResult($sessions);
        } catch (\Exception $e) {
            \Log::error('isWithinWorkingHours error: ' . $e->getMessage());
            // Fail-open: allow booking to proceed on error
            return ['within_hours' => true, 'message' => '', 'sessions' => []];
        }
    }

    /**
     * Build the Arabic message returned when current time is outside working hours.
     */
    private function buildOutsideHoursResult(array $sessions): array
    {
        $message = "طلبات الحجز تتم خلال فترة دوام العيادات الفعلي";

        foreach ($sessions as $session) {
            if ($session['label'] === 'morning') {
                $message .= "\nصباحاً: {$session['start']} - {$session['end']}";
            } elseif ($session['label'] === 'evening') {
                $message .= "\nمساءً: {$session['start']} - {$session['end']}";
            } else {
                $message .= "\n{$session['start']} - {$session['end']}";
            }
        }

        $message .= "\nللحالات الطارئة توجه لقسم الطوارئ متمنين لكم دوام الصحة والعافية";

        return ['within_hours' => false, 'message' => $message, 'sessions' => $sessions];
    }

    // ─── User Management Methods ───

    public function getStaffUsers(?string $clinicId = null, ?string $hospitalId = null): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $docs = $firestore->list('users');
            $users = [];

            foreach ($docs as $doc) {
                $docId = basename($doc['name'] ?? '');
                $parsed = (new \App\Services\FirestoreDocument($doc))->data();
                $role = $parsed['role'] ?? 'patient';

                // Skip patients — only staff users
                if ($role === 'patient') continue;

                // Filter by clinic_id if specified
                if ($clinicId && ($parsed['clinic_id'] ?? null) !== $clinicId) continue;

                // Filter by hospital_id if specified
                if ($hospitalId && ($parsed['hospital_id'] ?? null) !== $hospitalId) continue;

                $users[] = [
                    'id' => $docId,
                    'name' => $parsed['name'] ?? '',
                    'email' => $parsed['email'] ?? '',
                    'role' => $role,
                    'clinic_id' => $parsed['clinic_id'] ?? null,
                    'hospital_id' => $parsed['hospital_id'] ?? null,
                    'phone' => $parsed['phone'] ?? '',
                    'is_active' => $parsed['is_active'] ?? true,
                    'last_login' => $parsed['last_login'] ?? null,
                ];
            }

            usort($users, fn($a, $b) => ($a['name'] ?? '') <=> ($b['name'] ?? ''));
            return $users;
        } catch (\Exception $e) {
            \Log::error('getStaffUsers error: ' . $e->getMessage());
            return [];
        }
    }

    public function createStaffUser(array $data): ?string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $fields = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'clinic_id' => $data['clinic_id'] ?? null,
                'hospital_id' => $data['hospital_id'] ?? null,
                'phone' => $data['phone'] ?? '',
                'is_active' => true,
                'created_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
            ];

            if (!empty($data['password'])) {
                $fields['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $result = $firestore->createDocument('users', $fields);
            if ($result && isset($result['name'])) {
                return basename($result['name']);
            }
            return null;
        } catch (\Exception $e) {
            \Log::error('createStaffUser error: ' . $e->getMessage());
            return null;
        }
    }

    public function updateStaffUser(string $userId, array $data): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $updates = [];
            $mask = [];

            foreach (['name', 'email', 'role', 'clinic_id', 'hospital_id', 'phone', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                    $mask[] = $field;
                }
            }

            if (!empty($data['password'])) {
                $updates['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                $mask[] = 'password_hash';
            }

            $result = $firestore->patch("users/{$userId}", $updates, $mask);
            return $result !== null;
        } catch (\Exception $e) {
            \Log::error('updateStaffUser error: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteStaffUser(string $userId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            return $firestore->deleteDocument("users/{$userId}");
        } catch (\Exception $e) {
            \Log::error('deleteStaffUser error: ' . $e->getMessage());
            return false;
        }
    }

    // ─── Doctor CRUD Methods ───

    public function createDoctor(array $data): ?string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $docData = [
                'name' => $data['name'] ?? '',
                'name_en' => $data['name_en'] ?? $data['name'] ?? '',
                'specialty' => $data['specialty'] ?? '',
                'clinic_id' => $data['clinic_id'] ?? '',
                'hospital_id' => $data['hospital_id'] ?? '',
                'phone' => $data['phone'] ?? '',
                'consultation_fee' => (float)($data['consultation_fee'] ?? 0),
                'status' => $data['status'] ?? 'available',
                'avatar_url' => $data['avatar_url'] ?? '',
                'photo_url' => $data['photo_url'] ?? '',
                'bio' => $data['bio'] ?? '',
                'bio_en' => $data['bio_en'] ?? '',
                'education' => $data['education'] ?? '',
                'certifications' => $data['certifications'] ?? [],
                'years_experience' => (int)($data['years_experience'] ?? 0),
                'languages' => $data['languages'] ?? [],
                'rating' => 0.0,
                'review_count' => 0,
                'user_id' => $data['user_id'] ?? '',
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            ];

            if (!empty($data['working_hours'])) {
                $docData['schedule'] = $data['working_hours'];
            }

            $result = $firestore->collection('doctors')->add($docData);
            return $result ? $result->id() : null;
        } catch (\Exception $e) {
            \Log::error('Error creating doctor: ' . $e->getMessage());
            return null;
        }
    }

    public function updateDoctor(string $doctorId, array $data): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $updates = [];

            $allowedFields = ['name', 'name_en', 'specialty', 'clinic_id', 'hospital_id', 'phone', 'consultation_fee', 'status', 'avatar_url', 'photo_url', 'bio', 'bio_en', 'education', 'certifications', 'years_experience', 'languages', 'user_id'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $value = $data[$field];
                    if ($field === 'consultation_fee') {
                        $value = (float) $value;
                    }
                    if ($field === 'years_experience') {
                        $value = (int) $value;
                    }
                    $updates[] = ['path' => $field, 'value' => $value];
                }
            }

            if (!empty($data['working_hours'])) {
                $updates[] = ['path' => 'schedule', 'value' => $data['working_hours']];
            }

            $updates[] = ['path' => 'updated_at', 'value' => new \DateTime()];

            $firestore->collection('doctors')->document($doctorId)->update($updates);
            return true;
        } catch (\Exception $e) {
            \Log::error('Error updating doctor: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteDoctor(string $doctorId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            return $firestore->collection('doctors')->document($doctorId)->delete();
        } catch (\Exception $e) {
            \Log::error('Error deleting doctor: ' . $e->getMessage());
            return false;
        }
    }

    // ─── Clinic CRUD Methods ───

    public function createClinic(array $data): ?string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $fields = [
                'name' => $data['name'] ?? '',
                'name_en' => $data['name_en'] ?? $data['name'] ?? '',
                'hospital_id' => $data['hospital_id'] ?? null,
                'specialty' => $data['specialty'] ?? '',
                'icon' => $data['icon'] ?? 'medical_services',
                'icon_color' => $data['icon_color'] ?? 'blue',
                'address' => $data['address'] ?? '',
                'status' => $data['status'] ?? 'active',
                'geofence_radius' => (int)($data['geofence_radius'] ?? 100),
                'daily_capacity' => (int)($data['daily_capacity'] ?? 50),
                'follow_up_window_days' => (int)($data['follow_up_window_days'] ?? 30),
                'working_hours' => $data['working_hours'] ?? ['start' => '09:00', 'end' => '17:00'],
                'accepted_insurance' => $data['accepted_insurance'] ?? [],
                'created_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
                'updated_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
            ];

            if (!empty($data['latitude']) && !empty($data['longitude'])) {
                $fields['location'] = [
                    'latitude' => (float)$data['latitude'],
                    'longitude' => (float)$data['longitude'],
                ];
            }

            $result = $firestore->createDocument('clinics', $fields);
            if ($result && isset($result['name'])) {
                return basename($result['name']);
            }
            return null;
        } catch (\Exception $e) {
            \Log::error('Error creating clinic: ' . $e->getMessage());
            return null;
        }
    }

    public function deleteClinic(string $clinicId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            return $firestore->deleteDocument("clinics/{$clinicId}");
        } catch (\Exception $e) {
            \Log::error('Error deleting clinic: ' . $e->getMessage());
            return false;
        }
    }

    // ─── Treatment Plan Methods ───

    public function getTreatmentPlans(?string $doctorId = null, string $status = 'active'): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $query = $firestore->collection('treatment_plans')
                ->where('status', '=', $status);

            if ($doctorId) {
                $query = $query->where('doctor_id', '=', $doctorId);
            }

            $snapshot = $query->documents();
            $plans = [];

            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();

                if (isset($data['created_at']) && $data['created_at'] instanceof \Google\Cloud\Core\Timestamp) {
                    $data['created_at'] = $data['created_at']->get()->format('Y-m-d H:i');
                }
                if (isset($data['updated_at']) && $data['updated_at'] instanceof \Google\Cloud\Core\Timestamp) {
                    $data['updated_at'] = $data['updated_at']->get()->format('Y-m-d H:i');
                }

                $plans[] = $data;
            }

            return $plans;
        } catch (\Exception $e) {
            \Log::error('getTreatmentPlans error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get treatment plans for a specific clinic.
     */
    public function getTreatmentPlansForClinic(string $clinicId, string $status = 'active'): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $query = $firestore->collection('treatment_plans')
                ->where('status', '=', $status)
                ->where('clinic_id', '=', $clinicId);

            $snapshot = $query->documents();
            $plans = [];

            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();

                if (isset($data['created_at']) && $data['created_at'] instanceof \Google\Cloud\Core\Timestamp) {
                    $data['created_at'] = $data['created_at']->get()->format('Y-m-d H:i');
                }
                if (isset($data['updated_at']) && $data['updated_at'] instanceof \Google\Cloud\Core\Timestamp) {
                    $data['updated_at'] = $data['updated_at']->get()->format('Y-m-d H:i');
                }

                $plans[] = $data;
            }

            return $plans;
        } catch (\Exception $e) {
            \Log::error('getTreatmentPlansForClinic error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get treatment plans for a hospital (all clinics belonging to it).
     */
    public function getTreatmentPlansForHospital(string $hospitalId, string $status = 'active'): array
    {
        $clinicsRaw = $this->getClinicsRaw();
        $clinicIds = [];
        foreach ($clinicsRaw as $cDoc) {
            if (!$cDoc->exists()) continue;
            $cData = $cDoc->data();
            if (($cData['hospital_id'] ?? '') === $hospitalId) {
                $clinicIds[$cDoc->id()] = true;
            }
        }

        if (empty($clinicIds)) {
            return [];
        }

        $allPlans = $this->getTreatmentPlans(null, $status);
        return array_values(array_filter($allPlans, function ($p) use ($clinicIds) {
            return isset($clinicIds[$p['clinic_id'] ?? '']);
        }));
    }

    /**
     * Get today's booked patients for a specific doctor.
     * Returns unique patients with booking info from today's bookings.
     */
    public function getTodaysPatientsForDoctor(string $doctorId): array
    {
        $allBookings = $this->getBookingsRaw();
        $today = date('Y-m-d');
        $patients = [];
        $seenIds = [];

        foreach ($allBookings as $bDoc) {
            if (!$bDoc->exists()) continue;
            $bData = $bDoc->data();

            if (($bData['doctor_id'] ?? '') !== $doctorId) continue;
            if (empty($bData['patient_id'])) continue;

            // Check date
            $scheduledDate = $bData['scheduled_date'] ?? null;
            $bookingDate = null;
            if ($scheduledDate instanceof \Google\Cloud\Core\Timestamp) {
                $bookingDate = $scheduledDate->get()->format('Y-m-d');
            } elseif (is_string($scheduledDate)) {
                $bookingDate = substr($scheduledDate, 0, 10);
            }
            if ($bookingDate !== $today) continue;

            $patientId = $bData['patient_id'];
            if (isset($seenIds[$patientId])) continue;
            $seenIds[$patientId] = true;

            $patients[] = [
                'id' => $patientId,
                'name' => $bData['patient_name'] ?? 'Unknown',
                'phone' => $bData['patient_phone'] ?? '-',
            ];
        }

        return $patients;
    }

    public function getActiveTreatmentPlan(string $doctorId, string $patientId): ?array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $docId = "{$doctorId}_{$patientId}";
            $doc = $firestore->collection('treatment_plans')->document($docId)->snapshot();

            if (!$doc->exists()) return null;

            $data = $doc->data();
            if (($data['status'] ?? '') !== 'active') return null;

            $data['id'] = $doc->id();
            return $data;
        } catch (\Exception $e) {
            \Log::error('getActiveTreatmentPlan error: ' . $e->getMessage());
            return null;
        }
    }

    public function hasActiveTreatmentPlan(string $doctorId, string $patientId): bool
    {
        return $this->getActiveTreatmentPlan($doctorId, $patientId) !== null;
    }

    /**
     * Check if a patient is eligible for a follow-up booking.
     * Validates: active treatment plan exists, within the clinic's follow_up_window_days,
     * and booking is at the same clinic as the treatment plan.
     *
     * @param string $doctorId
     * @param string $patientId
     * @param string $clinicId The clinic where the patient is trying to book
     * @return array ['eligible' => bool, 'reason' => string|null, 'plan' => array|null]
     */
    public function isFollowupEligible(string $doctorId, string $patientId, string $clinicId): array
    {
        try {
            // 1. Check if an active treatment plan exists
            $plan = $this->getActiveTreatmentPlan($doctorId, $patientId);

            if (!$plan) {
                return [
                    'eligible' => false,
                    'reason' => 'no_active_treatment_plan',
                    'plan' => null,
                ];
            }

            // 2. Check clinic match — follow-up must be at the same clinic
            $planClinicId = $plan['clinic_id'] ?? null;
            if ($planClinicId && $planClinicId !== $clinicId) {
                return [
                    'eligible' => false,
                    'reason' => 'different_clinic',
                    'plan' => $plan,
                ];
            }

            // 3. Check follow-up window
            $clinicData = $this->getClinic($clinicId);
            $windowDays = (int) ($clinicData['follow_up_window_days'] ?? 30);

            $planCreatedAt = $plan['created_at'] ?? null;
            if ($planCreatedAt) {
                if ($planCreatedAt instanceof \Google\Cloud\Core\Timestamp) {
                    $planDate = $planCreatedAt->get();
                } elseif (is_string($planCreatedAt)) {
                    $planDate = new \DateTime($planCreatedAt);
                } else {
                    $planDate = null;
                }

                if ($planDate) {
                    $now = new \DateTime();
                    $diffDays = (int) $now->diff($planDate)->days;

                    if ($diffDays > $windowDays) {
                        return [
                            'eligible' => false,
                            'reason' => 'outside_followup_window',
                            'plan' => $plan,
                        ];
                    }
                }
            }

            // All checks passed
            return [
                'eligible' => true,
                'reason' => null,
                'plan' => $plan,
            ];
        } catch (\Exception $e) {
            \Log::error('isFollowupEligible error: ' . $e->getMessage());
            return [
                'eligible' => false,
                'reason' => 'error',
                'plan' => null,
            ];
        }
    }

    public function createTreatmentPlan(array $data): string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $docId = "{$data['doctor_id']}_{$data['patient_id']}";
            $now = new \Google\Cloud\Core\Timestamp(new \DateTime());

            $firestore->collection('treatment_plans')->document($docId)->set([
                'doctor_id' => $data['doctor_id'],
                'patient_id' => $data['patient_id'],
                'clinic_id' => $data['clinic_id'],
                'diagnosis' => $data['diagnosis'] ?? '',
                'notes' => $data['notes'] ?? '',
                'status' => 'active',
                'doctor_name' => $data['doctor_name'] ?? '',
                'patient_name' => $data['patient_name'] ?? '',
                'patient_phone' => $data['patient_phone'] ?? '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $docId;
        } catch (\Exception $e) {
            \Log::error('createTreatmentPlan error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function completeTreatmentPlan(string $planId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $firestore->collection('treatment_plans')->document($planId)->update([
                ['path' => 'status', 'value' => 'completed'],
                ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
            ]);
            return true;
        } catch (\Exception $e) {
            \Log::error('completeTreatmentPlan error: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteTreatmentPlan(string $planId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $firestore->collection('treatment_plans')->document($planId)->delete();
            return true;
        } catch (\Exception $e) {
            \Log::error('deleteTreatmentPlan error: ' . $e->getMessage());
            return false;
        }
    }

    public function updatePatient(string $patientId, array $data): bool
    {
        try {
            $firestore = $this->getFirestore();
            if (!$firestore) return false;

            $updates = [];

            $allowedFields = ['name', 'phone', 'email', 'national_id', 'gender', 'dob', 'blood_type', 'emergency_contact', 'address'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = ['path' => $field, 'value' => $data[$field]];
                }
            }

            $updates[] = ['path' => 'updated_at', 'value' => new \DateTime()];

            $firestore->collection('users')->document($patientId)->update($updates);
            return true;
        } catch (\Exception $e) {
            \Log::error('Error updating patient: ' . $e->getMessage());
            return false;
        }
    }

    // ─── Medication / Prescription Methods ───

    /**
     * Create a new prescription document in Firestore.
     */
    public function createPrescription(array $data): string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $now = (new \DateTime())->format('Y-m-d\TH:i:s\Z');

            // Calculate end date based on max medication duration
            $maxDuration = max(array_column($data['medications'], 'duration_days'));
            $endDate = (new \DateTime())->modify("+{$maxDuration} days")->format('Y-m-d\TH:i:s\Z');

            $fields = [
                'patient_id' => $data['patient_id'],
                'doctor_id' => $data['doctor_id'],
                'clinic_id' => $data['clinic_id'],
                'patient_name' => $data['patient_name'] ?? '',
                'patient_phone' => $data['patient_phone'] ?? '',
                'doctor_name' => $data['doctor_name'] ?? '',
                'medications' => array_map(function ($med) {
                    return [
                        'name' => $med['name'],
                        'duration_days' => (int) $med['duration_days'],
                        'interval_hours' => (float) $med['interval_hours'],
                        'dose_amount' => $med['dose_amount'],
                        'dose_unit' => $med['dose_unit'],
                        'first_dose_time' => $med['first_dose_time'],
                        'dose_schedule' => $med['dose_schedule'] ?? [],
                    ];
                }, $data['medications']),
                'notes' => $data['notes'] ?? '',
                'status' => 'active',
                'reminders_enabled' => true,
                'start_date' => $now,
                'end_date' => $endDate,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $result = $firestore->createDocument('prescriptions', $fields);
            if ($result && isset($result['name'])) {
                return basename($result['name']);
            }

            throw new \Exception('Failed to create prescription document');
        } catch (\Exception $e) {
            \Log::error('createPrescription error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get prescriptions with optional filters.
     */
    public function getPrescriptions(?string $doctorId = null, ?string $patientId = null, string $status = 'active'): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $query = $firestore->collection('prescriptions')
                ->where('status', '=', $status);

            if ($doctorId) {
                $query = $query->where('doctor_id', '=', $doctorId);
            }

            if ($patientId) {
                $query = $query->where('patient_id', '=', $patientId);
            }

            $docs = $query->documents();
            $prescriptions = [];

            foreach ($docs as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();

                // Count medications
                $data['medications_count'] = count($data['medications'] ?? []);

                $prescriptions[] = $data;
            }

            return $prescriptions;
        } catch (\Exception $e) {
            \Log::error('getPrescriptions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get prescriptions for a specific clinic.
     * Filters active prescriptions by clinic_id.
     */
    public function getPrescriptionsForClinic(string $clinicId, string $status = 'active'): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $query = $firestore->collection('prescriptions')
                ->where('status', '=', $status)
                ->where('clinic_id', '=', $clinicId);

            $docs = $query->documents();
            $prescriptions = [];

            foreach ($docs as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();
                $data['medications_count'] = count($data['medications'] ?? []);
                $prescriptions[] = $data;
            }

            return $prescriptions;
        } catch (\Exception $e) {
            \Log::error('getPrescriptionsForClinic error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get prescriptions for a hospital (all clinics belonging to it).
     */
    public function getPrescriptionsForHospital(string $hospitalId, string $status = 'active'): array
    {
        // Get all clinic IDs for this hospital
        $clinicsRaw = $this->getClinicsRaw();
        $clinicIds = [];
        foreach ($clinicsRaw as $cDoc) {
            if (!$cDoc->exists()) continue;
            $cData = $cDoc->data();
            if (($cData['hospital_id'] ?? '') === $hospitalId) {
                $clinicIds[$cDoc->id()] = true;
            }
        }

        if (empty($clinicIds)) {
            return [];
        }

        // Fetch all prescriptions with given status and filter by clinic IDs
        $allPrescriptions = $this->getPrescriptions(null, null, $status);
        return array_values(array_filter($allPrescriptions, function ($p) use ($clinicIds) {
            return isset($clinicIds[$p['clinic_id'] ?? '']);
        }));
    }

    /**
     * Get a single prescription by ID.
     */
    public function getPrescriptionById(string $id): ?array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $doc = $firestore->collection('prescriptions')->document($id)->snapshot();

            if (!$doc->exists()) return null;

            $data = $doc->data();
            $data['id'] = $doc->id();

            return $data;
        } catch (\Exception $e) {
            \Log::error('getPrescriptionById error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Toggle reminders on/off for a prescription.
     */
    public function togglePrescriptionReminders(string $id, bool $enabled): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $now = (new \DateTime())->format('Y-m-d\TH:i:s\Z');

            $firestore->collection('prescriptions')->document($id)->update([
                ['path' => 'reminders_enabled', 'value' => $enabled],
                ['path' => 'updated_at', 'value' => $now],
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('togglePrescriptionReminders error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deactivate (soft-delete) a prescription.
     */
    public function deactivatePrescription(string $id): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $now = (new \DateTime())->format('Y-m-d\TH:i:s\Z');

            $firestore->collection('prescriptions')->document($id)->update([
                ['path' => 'status', 'value' => 'inactive'],
                ['path' => 'reminders_enabled', 'value' => false],
                ['path' => 'updated_at', 'value' => $now],
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('deactivatePrescription error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update the medications array of a prescription (e.g., to persist snooze state).
     */
    public function updatePrescriptionMedications(string $id, array $medications): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $now = (new \DateTime())->format('Y-m-d\TH:i:s\Z');

            $firestore->collection('prescriptions')->document($id)->update([
                ['path' => 'medications', 'value' => $medications],
                ['path' => 'updated_at', 'value' => $now],
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('updatePrescriptionMedications error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get upcoming doses for a patient across all active prescriptions.
     * Calculates next dose times based on first_dose_time, interval, and duration.
     */
    public function getUpcomingDoses(string $patientId): array
    {
        $prescriptions = $this->getPrescriptions(null, $patientId, 'active');
        $upcomingDoses = [];
        $now = new \DateTime();

        foreach ($prescriptions as $prescription) {
            // Skip if reminders are disabled
            if (!($prescription['reminders_enabled'] ?? true)) {
                continue;
            }

            $startDate = isset($prescription['start_date'])
                ? new \DateTime($prescription['start_date'])
                : null;

            if (!$startDate) continue;

            $medications = $prescription['medications'] ?? [];

            foreach ($medications as $medIndex => $med) {
                $durationDays = (int) ($med['duration_days'] ?? 0);
                $intervalHours = (float) ($med['interval_hours'] ?? 0);
                $firstDoseTime = $med['first_dose_time'] ?? '08:00';

                if ($durationDays <= 0 || $intervalHours <= 0) continue;

                $medEndDate = (clone $startDate)->modify("+{$durationDays} days");

                // If medication period has ended, skip
                if ($now > $medEndDate) continue;

                $intervalMinutes = (int) ($intervalHours * 60);

                // Build first dose DateTime on the start date
                $timeParts = explode(':', $firstDoseTime);
                $firstDose = (clone $startDate)
                    ->setTime((int) ($timeParts[0] ?? 8), (int) ($timeParts[1] ?? 0));

                // Calculate the next dose from now
                if ($now < $firstDose) {
                    // Haven't reached first dose yet
                    $nextDose = $firstDose;
                } else {
                    // Calculate how many intervals have passed since first dose
                    $diffMinutes = (int) (($now->getTimestamp() - $firstDose->getTimestamp()) / 60);
                    $intervalsPassed = (int) floor($diffMinutes / $intervalMinutes);
                    $nextDose = (clone $firstDose)->modify("+" . (($intervalsPassed + 1) * $intervalMinutes) . " minutes");
                }

                // Only include if next dose is within the medication period
                if ($nextDose <= $medEndDate) {
                    $upcomingDoses[] = [
                        'prescription_id' => $prescription['id'] ?? '',
                        'medication_index' => $medIndex,
                        'medication_name' => $med['name'] ?? '',
                        'dose_amount' => $med['dose_amount'] ?? '',
                        'dose_unit' => $med['dose_unit'] ?? '',
                        'scheduled_time' => $nextDose->format('Y-m-d\TH:i:s\Z'),
                        'patient_name' => $prescription['patient_name'] ?? '',
                        'doctor_name' => $prescription['doctor_name'] ?? '',
                    ];
                }
            }
        }

        // Sort by scheduled_time ascending
        usort($upcomingDoses, function ($a, $b) {
            return strcmp($a['scheduled_time'], $b['scheduled_time']);
        });

        return $upcomingDoses;
    }

    // ─── Password Reset Methods ───

    /**
     * Store a password reset token in Firestore password_resets collection.
     *
     * @param string $email
     * @param string $token
     * @return bool
     */
    public function storePasswordResetToken(string $email, string $token): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $result = $firestore->createDocument('password_resets', [
                'email' => $email,
                'token' => $token,
                'created_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
            ], $token);

            return $result !== null;
        } catch (\Exception $e) {
            \Log::error('storePasswordResetToken error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Look up a password reset token and validate it has not expired (1 hour).
     *
     * @param string $token
     * @return array|null Token data with email, or null if invalid/expired
     */
    public function getPasswordResetToken(string $token): ?array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $doc = $firestore->get("password_resets/{$token}");

            if (!$doc || !isset($doc['fields'])) {
                return null;
            }

            $fields = $doc['fields'];
            $email = $fields['email']['stringValue'] ?? null;
            $createdAt = $fields['created_at']['stringValue'] ?? null;

            if (!$email || !$createdAt) {
                return null;
            }

            // Check expiration (1 hour)
            $createdTime = new \DateTime($createdAt);
            $now = new \DateTime();
            $diff = $now->getTimestamp() - $createdTime->getTimestamp();

            if ($diff > 3600) {
                // Token expired — clean up
                $this->deletePasswordResetToken($token);
                return null;
            }

            return [
                'email' => $email,
                'token' => $token,
                'created_at' => $createdAt,
            ];
        } catch (\Exception $e) {
            \Log::error('getPasswordResetToken error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a password reset token after use.
     *
     * @param string $token
     * @return bool
     */
    public function deletePasswordResetToken(string $token): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            return $firestore->deleteDocument("password_resets/{$token}");
        } catch (\Exception $e) {
            \Log::error('deletePasswordResetToken error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update a user's password by email.
     * Finds the user by email and updates their password_hash field.
     *
     * @param string $email
     * @param string $newPassword Plain text password to hash
     * @return bool
     */
    public function updateUserPassword(string $email, string $newPassword): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $user = $this->getUserByEmail($email);

            if (!$user || !isset($user['id'])) {
                return false;
            }

            $result = $firestore->patch("users/{$user['id']}", [
                'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            ], ['password_hash']);

            return $result !== null;
        } catch (\Exception $e) {
            \Log::error('updateUserPassword error: ' . $e->getMessage());
            return false;
        }
    }

    // ─── Audit Logging ────────────────────────────────────────────────────

    /**
     * Log an activity to the audit_logs Firestore collection.
     *
     * @param string      $action   Dot-notation action identifier (e.g. "hospital.approved")
     * @param array       $details  Contextual key-value pairs
     * @param string|null $userId   Override user ID (defaults to session)
     * @param string|null $userName Override user name (defaults to session)
     * @return string|null  The created document ID, or null on failure
     */
    public function logActivity(string $action, array $details = [], ?string $userId = null, ?string $userName = null): ?string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return null;
        }

        try {
            $fields = [
                'action'     => $action,
                'details'    => $details,
                'user_id'    => $userId ?? \Session::get('firebase_user_id', ''),
                'user_name'  => $userName ?? \Session::get('firebase_user_name', ''),
                'user_role'  => \Session::get('firebase_user_role', ''),
                'ip_address' => request()->ip() ?? '',
                'user_agent' => request()->userAgent() ?? '',
                'timestamp'  => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
            ];

            $result = $firestore->createDocument('audit_logs', $fields);

            if ($result && isset($result['name'])) {
                return basename($result['name']);
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Audit log error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch audit logs with optional filters.
     *
     * @param array $filters  Optional keys: action, user_id, date_from, date_to
     * @return array
     */
    public function getAuditLogs(array $filters = []): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return [];
        }

        try {
            $query = $firestore->collection('audit_logs');

            if (!empty($filters['action'])) {
                $query = $query->where('action', '==', $filters['action']);
            }

            if (!empty($filters['user_id'])) {
                $query = $query->where('user_id', '==', $filters['user_id']);
            }

            if (!empty($filters['date_from'])) {
                $query = $query->where('timestamp', '>=', $filters['date_from'] . 'T00:00:00Z');
            }

            if (!empty($filters['date_to'])) {
                $query = $query->where('timestamp', '<=', $filters['date_to'] . 'T23:59:59Z');
            }

            $query = $query->orderBy('timestamp', 'DESC')->limit(100);

            $documents = $query->documents();

            $logs = [];
            foreach ($documents as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $data['id'] = $doc->id();
                    $logs[] = $data;
                }
            }

            return $logs;
        } catch (\Exception $e) {
            \Log::error('Fetch audit logs error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch a single audit log entry by ID.
     *
     * @param string $id
     * @return array|null
     */
    public function getAuditLogById(string $id): ?array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return null;
        }

        try {
            $doc = $firestore->get("audit_logs/{$id}");
            if (!$doc) {
                return null;
            }

            $parsed = (new \App\Services\FirestoreDocument($doc))->data();
            $parsed['id'] = $id;
            return $parsed;
        } catch (\Exception $e) {
            \Log::error('Fetch audit log by ID error: ' . $e->getMessage());
            return null;
        }
    }

    // ─── Financial Dashboard Methods ────────────────────────────────────

    /**
     * Get financial statistics from bookings collection.
     *
     * Queries confirmed/completed bookings and calculates revenue metrics
     * based on doctor consultation fees and payment statuses.
     *
     * @param array $filters Optional filters: date_from, date_to, clinic_id
     * @return array Financial stats including revenue breakdowns and daily data
     */
    public function getFinancialStats(array $filters = []): array
    {
        $cacheKey = 'financial_stats_' . md5(json_encode($filters));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $dateFrom = $filters['date_from'] ?? date('Y-m-01');
        $dateTo = $filters['date_to'] ?? date('Y-m-d');
        $filterClinicId = $filters['clinic_id'] ?? null;

        $defaultStats = [
            'total_revenue' => 0,
            'cash_revenue' => 0,
            'online_revenue' => 0,
            'waived_count' => 0,
            'total_transactions' => 0,
            'avg_transaction_value' => 0,
            'daily_breakdown' => [],
            'recent_transactions' => [],
        ];

        if (!$this->firestoreClient) {
            return $defaultStats;
        }

        try {
            // Fetch all required collections using cached methods
            $allBookings = $this->getBookingsRaw();
            $allDoctors = $this->getDoctorsRaw();
            $allClinics = $this->getClinicsRaw();

            // Build doctor fee lookup: doctor_id => consultation_fee
            $doctorFees = [];
            $doctorNames = [];
            foreach ($allDoctors as $doc) {
                if (!$doc->exists()) continue;
                $dData = $doc->data();
                $doctorFees[$doc->id()] = (float) ($dData['consultation_fee'] ?? 0);
                $doctorNames[$doc->id()] = $this->getLocalizedField($dData, 'name', 'Unknown Doctor');
            }

            // Build clinic name lookup: clinic_id => name
            $clinicNames = [];
            foreach ($allClinics as $doc) {
                if (!$doc->exists()) continue;
                $cData = $doc->data();
                $clinicNames[$doc->id()] = $this->getLocalizedField($cData, 'name', 'Unknown Clinic');
            }

            // Build patient name lookup from bookings (denormalized) + lazy fetch
            $patientNames = [];

            $totalRevenue = 0;
            $cashRevenue = 0;
            $onlineRevenue = 0;
            $waivedCount = 0;
            $totalTransactions = 0;
            $dailyBreakdown = [];
            $recentTransactions = [];

            $confirmedStatuses = ['confirmed', 'completed', 'arrived'];

            foreach ($allBookings as $document) {
                if (!$document->exists()) continue;
                $data = $document->data();

                $status = $data['status'] ?? '';
                if (!in_array($status, $confirmedStatuses)) continue;

                // Extract booking date
                $bookingDate = null;
                if (isset($data['scheduled_date'])) {
                    $sd = $data['scheduled_date'];
                    if ($sd instanceof \Google\Cloud\Core\Timestamp) {
                        $bookingDate = $sd->get()->format('Y-m-d');
                    } elseif (is_string($sd)) {
                        $bookingDate = substr($sd, 0, 10);
                    }
                }

                if (!$bookingDate) continue;

                // Apply date range filter
                if ($bookingDate < $dateFrom || $bookingDate > $dateTo) continue;

                // Apply clinic filter
                $clinicId = $data['clinic_id'] ?? null;
                if ($filterClinicId && $clinicId !== $filterClinicId) continue;

                $paymentStatus = $data['payment_status'] ?? 'unpaid';
                $doctorId = $data['doctor_id'] ?? null;
                $fee = $doctorId ? ($doctorFees[$doctorId] ?? 0) : 0;

                // Initialize daily bucket
                if (!isset($dailyBreakdown[$bookingDate])) {
                    $dailyBreakdown[$bookingDate] = [
                        'total' => 0,
                        'cash' => 0,
                        'online' => 0,
                        'waived' => 0,
                    ];
                }

                if ($paymentStatus === 'waived_followup') {
                    $waivedCount++;
                    $dailyBreakdown[$bookingDate]['waived']++;
                } else if (in_array($paymentStatus, ['cash', 'stripe', 'paid', 'pay_on_arrival'])) {
                    $totalRevenue += $fee;
                    $totalTransactions++;
                    $dailyBreakdown[$bookingDate]['total'] += $fee;

                    if ($paymentStatus === 'cash' || $paymentStatus === 'pay_on_arrival') {
                        $cashRevenue += $fee;
                        $dailyBreakdown[$bookingDate]['cash'] += $fee;
                    } elseif ($paymentStatus === 'stripe' || $paymentStatus === 'paid') {
                        $onlineRevenue += $fee;
                        $dailyBreakdown[$bookingDate]['online'] += $fee;
                    }

                    // Collect recent transactions (we'll sort and limit later)
                    $patientId = $data['patient_id'] ?? null;
                    $patientName = $data['patient_name'] ?? null;
                    if (!$patientName && $patientId) {
                        if (!isset($patientNames[$patientId])) {
                            $patient = $this->getPatientDetails($patientId);
                            $patientNames[$patientId] = $patient['name'] ?? 'Unknown Patient';
                        }
                        $patientName = $patientNames[$patientId];
                    }

                    $recentTransactions[] = [
                        'id' => $document->id(),
                        'date' => $bookingDate,
                        'patient' => $patientName ?? 'Unknown Patient',
                        'doctor' => $doctorId ? ($doctorNames[$doctorId] ?? 'Unknown Doctor') : 'Unknown Doctor',
                        'clinic' => $clinicId ? ($clinicNames[$clinicId] ?? 'Unknown Clinic') : 'Unknown Clinic',
                        'amount' => $fee,
                        'payment_status' => $paymentStatus,
                        'status' => $status,
                        'created_at' => $data['created_at'] ?? null,
                    ];
                }
            }

            // Sort daily breakdown by date
            ksort($dailyBreakdown);

            // Sort recent transactions by date descending, limit to 20
            usort($recentTransactions, function ($a, $b) {
                return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '');
            });
            $recentTransactions = array_slice($recentTransactions, 0, 20);

            $avgTransactionValue = $totalTransactions > 0
                ? round($totalRevenue / $totalTransactions, 2)
                : 0;

            $result = [
                'total_revenue' => $totalRevenue,
                'cash_revenue' => $cashRevenue,
                'online_revenue' => $onlineRevenue,
                'waived_count' => $waivedCount,
                'total_transactions' => $totalTransactions,
                'avg_transaction_value' => $avgTransactionValue,
                'daily_breakdown' => $dailyBreakdown,
                'recent_transactions' => $recentTransactions,
            ];

            // Cache for 5 minutes
            Cache::put($cacheKey, $result, 300);

            return $result;
        } catch (\Exception $e) {
            \Log::error('getFinancialStats error: ' . $e->getMessage());
            return $defaultStats;
        }
    }

    // ─── Clinic Holiday Methods ─────────────────────────────────────────

    /**
     * Get all holidays for a clinic from subcollection clinics/{clinicId}/holidays.
     * Cached for 10 minutes.
     *
     * @param string $clinicId
     * @return array Array of holiday data with id, date, name, name_en, is_recurring, created_at
     */
    public function getClinicHolidays(string $clinicId): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return [];
        }

        $cacheKey = "clinic_holidays_{$clinicId}";

        return Cache::remember($cacheKey, 600, function () use ($firestore, $clinicId) {
            try {
                $docs = $firestore->collection("clinics/{$clinicId}/holidays")->documents();
                $holidays = [];

                foreach ($docs as $doc) {
                    if (!$doc->exists()) continue;
                    $data = $doc->data();
                    $data['id'] = $doc->id();
                    $holidays[] = $data;
                }

                // Sort by date ascending
                usort($holidays, function ($a, $b) {
                    return ($a['date'] ?? '') <=> ($b['date'] ?? '');
                });

                return $holidays;
            } catch (\Exception $e) {
                \Log::error("getClinicHolidays error for clinic {$clinicId}: " . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Add a holiday to the clinic's holidays subcollection.
     *
     * @param string $clinicId
     * @param array $data {date, name, name_en, is_recurring}
     * @return string|null The new holiday document ID
     */
    public function addClinicHoliday(string $clinicId, array $data): ?string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return null;
        }

        try {
            $fields = [
                'date' => $data['date'],
                'name' => $data['name'] ?? '',
                'name_en' => $data['name_en'] ?? '',
                'is_recurring' => (bool)($data['is_recurring'] ?? false),
                'created_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
            ];

            $result = $firestore->createDocument("clinics/{$clinicId}/holidays", $fields);
            if ($result && isset($result['name'])) {
                // Invalidate cache
                Cache::forget("clinic_holidays_{$clinicId}");
                return basename($result['name']);
            }
            return null;
        } catch (\Exception $e) {
            \Log::error("addClinicHoliday error for clinic {$clinicId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a holiday from the clinic's holidays subcollection.
     *
     * @param string $clinicId
     * @param string $holidayId
     * @return bool
     */
    public function deleteClinicHoliday(string $clinicId, string $holidayId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return false;
        }

        try {
            $result = $firestore->deleteDocument("clinics/{$clinicId}/holidays/{$holidayId}");
            if ($result) {
                // Invalidate cache
                Cache::forget("clinic_holidays_{$clinicId}");
            }
            return $result;
        } catch (\Exception $e) {
            \Log::error("deleteClinicHoliday error for clinic {$clinicId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a specific date is a holiday for the clinic.
     * Checks both exact date match and recurring match (month-day).
     *
     * @param string $clinicId
     * @param string $date Y-m-d format
     * @return bool
     */
    public function isClinicHoliday(string $clinicId, string $date): bool
    {
        try {
            $holidays = $this->getClinicHolidays($clinicId);
            $checkMonthDay = date('m-d', strtotime($date));

            foreach ($holidays as $holiday) {
                $holidayDate = $holiday['date'] ?? '';
                $isRecurring = (bool)($holiday['is_recurring'] ?? false);

                // Exact date match
                if ($holidayDate === $date) {
                    return true;
                }

                // Recurring: match on month-day
                if ($isRecurring && !empty($holidayDate)) {
                    $holidayMonthDay = date('m-d', strtotime($holidayDate));
                    if ($holidayMonthDay === $checkMonthDay) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            \Log::error("isClinicHoliday error: " . $e->getMessage());
            return false;
        }
    }

    // ─── Chart Data Methods ──────────────────────────────────────────────

    /**
     * Get bookings for a date range, optionally filtered by clinic.
     * Uses Cache for 5-minute caching to reduce Firestore reads.
     *
     * @param string $dateFrom Start date (Y-m-d)
     * @param string $dateTo End date (Y-m-d)
     * @param string|null $clinicId Optional clinic filter
     * @return array Array of booking data arrays
     */
    public function getBookingsForDateRange(string $dateFrom, string $dateTo, ?string $clinicId = null): array
    {
        $cacheKey = "bookings_date_range_{$dateFrom}_{$dateTo}_" . ($clinicId ?? 'all');

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $firestore = $this->getFirestore();
        if (!$firestore) {
            return [];
        }

        try {
            $allBookings = $this->getBookingsRaw();
            $results = [];

            foreach ($allBookings as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();

                // Filter by clinic if specified
                if ($clinicId && ($data['clinic_id'] ?? null) !== $clinicId) {
                    continue;
                }

                // Extract scheduled_date
                $bookingDate = null;
                if (isset($data['scheduled_date'])) {
                    $sd = $data['scheduled_date'];
                    if ($sd instanceof \Google\Cloud\Core\Timestamp) {
                        $bookingDate = $sd->get()->format('Y-m-d');
                    } elseif (is_string($sd)) {
                        $bookingDate = substr($sd, 0, 10);
                    }
                }

                if ($bookingDate && $bookingDate >= $dateFrom && $bookingDate <= $dateTo) {
                    $results[] = [
                        'id' => $doc->id(),
                        'status' => $data['status'] ?? 'pending',
                        'scheduled_date' => $bookingDate,
                        'clinic_id' => $data['clinic_id'] ?? null,
                        'doctor_id' => $data['doctor_id'] ?? null,
                        'doctor_name' => $data['doctor_name'] ?? null,
                        'patient_id' => $data['patient_id'] ?? null,
                    ];
                }
            }

            Cache::put($cacheKey, $results, 300); // 5 minutes

            return $results;
        } catch (\Exception $e) {
            \Log::error('getBookingsForDateRange error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get doctor utilization data for today.
     * For each doctor, returns their booking count vs daily capacity.
     *
     * @param string|null $clinicId Optional clinic filter
     * @return array Array of ['name' => string, 'bookings' => int, 'capacity' => int]
     */
    public function getDoctorUtilization(?string $clinicId = null): array
    {
        $cacheKey = 'doctor_utilization_' . ($clinicId ?? 'all');

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $firestore = $this->getFirestore();
        if (!$firestore) {
            return [];
        }

        try {
            $today = date('Y-m-d');

            // Get all doctors
            $allDoctors = $this->getDoctorsRaw();
            $doctorMap = []; // doctor_id => ['name' => ..., 'bookings' => 0, 'capacity' => ...]

            foreach ($allDoctors as $dDoc) {
                if (!$dDoc->exists()) continue;
                $dData = $dDoc->data();
                $doctorId = $dDoc->id();

                // Filter by clinic if specified
                if ($clinicId && ($dData['clinic_id'] ?? null) !== $clinicId) {
                    continue;
                }

                // Only include active/available doctors
                $status = $dData['status'] ?? 'off';
                if (!in_array($status, ['available', 'active', 'busy', 'on_duty'])) {
                    continue;
                }

                $doctorMap[$doctorId] = [
                    'name' => $this->getLocalizedField($dData, 'name', 'Unknown Doctor'),
                    'bookings' => 0,
                    'capacity' => (int) ($dData['daily_capacity'] ?? $dData['max_patients'] ?? 20),
                ];
            }

            // Count today's bookings per doctor
            $allBookings = $this->getBookingsRaw();
            foreach ($allBookings as $bDoc) {
                if (!$bDoc->exists()) continue;
                $bData = $bDoc->data();

                // Extract date
                $bookingDate = null;
                if (isset($bData['scheduled_date'])) {
                    $sd = $bData['scheduled_date'];
                    if ($sd instanceof \Google\Cloud\Core\Timestamp) {
                        $bookingDate = $sd->get()->format('Y-m-d');
                    } elseif (is_string($sd)) {
                        $bookingDate = substr($sd, 0, 10);
                    }
                }

                if ($bookingDate !== $today) continue;

                $doctorId = $bData['doctor_id'] ?? null;
                if ($doctorId && isset($doctorMap[$doctorId])) {
                    $doctorMap[$doctorId]['bookings']++;
                }
            }

            $results = array_values($doctorMap);

            Cache::put($cacheKey, $results, 300); // 5 minutes

            return $results;
        } catch (\Exception $e) {
            \Log::error('getDoctorUtilization error: ' . $e->getMessage());
            return [];
        }
    }

    // ─── Patient Medical History Methods ───

    /**
     * Update patient document with medical info fields.
     *
     * @param string $patientId
     * @param array $data  Keys: allergies, chronic_conditions, blood_type,
     *                     emergency_contact_name, emergency_contact_phone,
     *                     emergency_contact_relation, medical_notes
     * @return bool
     */
    public function updatePatientMedicalInfo(string $patientId, array $data): bool
    {
        try {
            $firestore = $this->getFirestore();
            if (!$firestore) return false;

            $updates = [];

            // Array fields
            if (array_key_exists('allergies', $data)) {
                $updates[] = ['path' => 'allergies', 'value' => is_array($data['allergies']) ? $data['allergies'] : []];
            }
            if (array_key_exists('chronic_conditions', $data)) {
                $updates[] = ['path' => 'chronic_conditions', 'value' => is_array($data['chronic_conditions']) ? $data['chronic_conditions'] : []];
            }

            // Simple fields
            $simpleFields = ['blood_type', 'medical_notes'];
            foreach ($simpleFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = ['path' => $field, 'value' => $data[$field]];
                }
            }

            // Emergency contact as nested object
            if (isset($data['emergency_contact_name']) || isset($data['emergency_contact_phone']) || isset($data['emergency_contact_relation'])) {
                $updates[] = ['path' => 'emergency_contact', 'value' => [
                    'name' => $data['emergency_contact_name'] ?? '',
                    'phone' => $data['emergency_contact_phone'] ?? '',
                    'relation' => $data['emergency_contact_relation'] ?? '',
                ]];
            }

            $updates[] = ['path' => 'updated_at', 'value' => new \DateTime()];

            $firestore->collection('users')->document($patientId)->update($updates);

            // Invalidate patient caches
            Cache::forget('patients_index');

            return true;
        } catch (\Exception $e) {
            \Log::error('Error updating patient medical info: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get patient visit history (completed bookings).
     * Queries bookings where patient_id matches AND status is 'completed'.
     * Joins with doctor name and clinic name. Sorted by date desc. Limit 50. Cache 5 min.
     *
     * @param string $patientId
     * @return array
     */
    public function getPatientVisitHistory(string $patientId): array
    {
        $cacheKey = "patient_visit_history_{$patientId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $documents = $this->getBookingsRaw();

            $visits = [];
            foreach ($documents as $document) {
                if (!$document->exists()) continue;
                $data = $document->data();

                if (($data['patient_id'] ?? null) !== $patientId) continue;
                if (($data['status'] ?? null) !== 'completed') continue;

                // Resolve doctor name
                $doctorName = $data['doctor_name'] ?? null;
                if (!$doctorName && !empty($data['doctor_id'])) {
                    $doctor = $this->getDoctorDetails($data['clinic_id'] ?? '', $data['doctor_id']);
                    $doctorName = $this->getLocalizedField($doctor, 'name', 'Unknown Doctor');
                }

                // Resolve clinic name
                $clinicName = $data['clinic_name'] ?? null;
                if (!$clinicName && !empty($data['clinic_id'])) {
                    $clinic = $this->getClinicById($data['clinic_id']);
                    $clinicName = $this->getLocalizedField($clinic, 'name', 'Unknown Clinic');
                }

                // Parse date
                $scheduledDate = $data['scheduled_date'] ?? $data['created_at'] ?? null;
                if ($scheduledDate instanceof \Google\Cloud\Core\Timestamp) {
                    $scheduledDate = $scheduledDate->get()->format('Y-m-d H:i');
                } elseif (is_string($scheduledDate)) {
                    $scheduledDate = substr($scheduledDate, 0, 16);
                }

                $visits[] = [
                    'id' => $document->id(),
                    'date' => $scheduledDate ?? '-',
                    'doctor_name' => $doctorName ?? 'Unknown Doctor',
                    'clinic_name' => $clinicName ?? 'Unknown Clinic',
                    'status' => $data['status'] ?? 'completed',
                    'notes' => $data['notes'] ?? '',
                ];
            }

            // Sort by date descending
            usort($visits, function ($a, $b) {
                return ($b['date'] ?? '') <=> ($a['date'] ?? '');
            });

            // Limit 50
            $visits = array_slice($visits, 0, 50);

            Cache::put($cacheKey, $visits, 300);
            return $visits;
        } catch (\Exception $e) {
            \Log::error('getPatientVisitHistory error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get patient treatment plan history.
     * Queries treatment_plans where patient_id matches. Returns with doctor names. Cache 5 min.
     *
     * @param string $patientId
     * @return array
     */
    public function getPatientTreatmentHistory(string $patientId): array
    {
        $cacheKey = "patient_treatment_history_{$patientId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $query = $firestore->collection('treatment_plans')
                ->where('patient_id', '=', $patientId);

            $snapshot = $query->documents();
            $plans = [];

            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();

                // Resolve doctor name
                $doctorName = $data['doctor_name'] ?? null;
                if (!$doctorName && !empty($data['doctor_id'])) {
                    $doctor = $this->getDoctorDetails($data['clinic_id'] ?? '', $data['doctor_id']);
                    $doctorName = $this->getLocalizedField($doctor, 'name', 'Unknown Doctor');
                }
                $data['doctor_name'] = $doctorName ?? 'Unknown Doctor';

                // Parse dates
                if (isset($data['created_at']) && $data['created_at'] instanceof \Google\Cloud\Core\Timestamp) {
                    $data['created_at'] = $data['created_at']->get()->format('Y-m-d H:i');
                }
                if (isset($data['updated_at']) && $data['updated_at'] instanceof \Google\Cloud\Core\Timestamp) {
                    $data['updated_at'] = $data['updated_at']->get()->format('Y-m-d H:i');
                }

                $plans[] = $data;
            }

            // Sort by created_at descending
            usort($plans, function ($a, $b) {
                return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '');
            });

            Cache::put($cacheKey, $plans, 300);
            return $plans;
        } catch (\Exception $e) {
            \Log::error('getPatientTreatmentHistory error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get patient prescription/medication history.
     * Queries prescriptions where patient_id matches. Sorted by date desc. Cache 5 min.
     *
     * @param string $patientId
     * @return array
     */
    public function getPatientPrescriptionHistory(string $patientId): array
    {
        $cacheKey = "patient_prescription_history_{$patientId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $query = $firestore->collection('prescriptions')
                ->where('patient_id', '=', $patientId);

            $docs = $query->documents();
            $prescriptions = [];

            foreach ($docs as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();

                // Resolve doctor name
                $doctorName = $data['doctor_name'] ?? null;
                if (!$doctorName && !empty($data['doctor_id'])) {
                    $doctor = $this->getDoctorDetails($data['clinic_id'] ?? '', $data['doctor_id']);
                    $doctorName = $this->getLocalizedField($doctor, 'name', 'Unknown Doctor');
                }
                $data['doctor_name'] = $doctorName ?? 'Unknown Doctor';

                // Parse dates
                if (isset($data['created_at']) && $data['created_at'] instanceof \Google\Cloud\Core\Timestamp) {
                    $data['created_at'] = $data['created_at']->get()->format('Y-m-d H:i');
                }

                $data['medications_count'] = count($data['medications'] ?? []);

                $prescriptions[] = $data;
            }

            // Sort by created_at descending
            usort($prescriptions, function ($a, $b) {
                return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '');
            });

            Cache::put($cacheKey, $prescriptions, 300);
            return $prescriptions;
        } catch (\Exception $e) {
            \Log::error('getPatientPrescriptionHistory error: ' . $e->getMessage());
            return [];
        }
    }

    // ─── Appointment Reminder Methods ───

    /**
     * Get bookings for a specific date filtered by statuses.
     * Returns raw booking data with document IDs for reminder processing.
     *
     * @param string $date Date in Y-m-d format
     * @param array $statuses Array of status strings to filter by
     * @return array Array of booking arrays with 'id' key included
     */
    public function getBookingsForDate(string $date, array $statuses = []): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $documents = $this->getBookingsRaw();
            $results = [];

            foreach ($documents as $document) {
                if (!$document->exists()) continue;

                $data = $document->data();

                // Match scheduled_date
                $scheduledDate = $data['scheduled_date'] ?? null;
                $bookingDate = null;

                if ($scheduledDate instanceof \Google\Cloud\Core\Timestamp) {
                    $bookingDate = $scheduledDate->get()->format('Y-m-d');
                } elseif (is_string($scheduledDate)) {
                    $bookingDate = substr($scheduledDate, 0, 10);
                }

                if ($bookingDate !== $date) continue;

                // Match status if filter provided
                if (!empty($statuses)) {
                    $bookingStatus = $data['status'] ?? null;
                    if (!in_array($bookingStatus, $statuses)) continue;
                }

                $data['id'] = $document->id();
                $results[] = $data;
            }

            return $results;
        } catch (\Exception $e) {
            \Log::error('getBookingsForDate error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark a booking as reminder sent.
     * Sets reminder_sent=true and reminder_sent_at to current timestamp.
     *
     * @param string $bookingId
     * @return bool
     */
    public function markReminderSent(string $bookingId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $firestore->collection('bookings')->document($bookingId)->update([
                ['path' => 'reminder_sent', 'value' => true],
                ['path' => 'reminder_sent_at', 'value' => (new \DateTime())->format('Y-m-d\TH:i:s\Z')],
            ]);

            $this->invalidateBookingCaches();
            return true;
        } catch (\Exception $e) {
            \Log::error("markReminderSent error for booking {$bookingId}: " . $e->getMessage());
            return false;
        }
    }

    // ─── Reviews / Ratings Methods ───

    /**
     * Create a review document in the 'reviews' collection.
     * Also updates the doctor's avg_rating and total_reviews fields.
     *
     * @param array $data Review data
     * @return string|null The new review document ID
     */
    public function createReview(array $data): ?string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $fields = [
                'patient_id' => $data['patient_id'] ?? '',
                'patient_name' => $data['patient_name'] ?? '',
                'doctor_id' => $data['doctor_id'] ?? '',
                'doctor_name' => $data['doctor_name'] ?? '',
                'clinic_id' => $data['clinic_id'] ?? '',
                'booking_id' => $data['booking_id'] ?? '',
                'rating' => (int) ($data['rating'] ?? 5),
                'comment' => $data['comment'] ?? '',
                'is_visible' => true,
                'created_at' => (new \DateTime())->format('Y-m-d\TH:i:s\Z'),
            ];

            $result = $firestore->createDocument('reviews', $fields);
            $this->invalidateReviewCaches();

            // Update doctor's aggregate rating
            if (!empty($data['doctor_id']) && !empty($data['clinic_id'])) {
                $this->updateDoctorRating($data['doctor_id'], $data['clinic_id']);
            }

            if ($result && isset($result['name'])) {
                return basename($result['name']);
            }
            return null;
        } catch (\Exception $e) {
            \Log::error('createReview error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all visible reviews for a doctor, sorted by date desc. Cached 5 min.
     *
     * @param string $doctorId
     * @return array
     */
    public function getReviewsForDoctor(string $doctorId): array
    {
        $cacheKey = "reviews_doctor_{$doctorId}";

        return Cache::remember($cacheKey, 300, function () use ($doctorId) {
            $firestore = $this->getFirestore();
            if (!$firestore) return [];

            try {
                $docs = $firestore->collection('reviews')
                    ->where('doctor_id', '=', $doctorId)
                    ->where('is_visible', '=', true)
                    ->documents();

                $reviews = [];
                foreach ($docs as $doc) {
                    if (!$doc->exists()) continue;
                    $data = $doc->data();
                    $data['id'] = $doc->id();
                    $reviews[] = $data;
                }

                // Sort by created_at descending
                usort($reviews, function ($a, $b) {
                    return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '');
                });

                return $reviews;
            } catch (\Exception $e) {
                \Log::error("getReviewsForDoctor error for doctor {$doctorId}: " . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get all visible reviews for a clinic. Cached 5 min.
     *
     * @param string $clinicId
     * @return array
     */
    public function getReviewsForClinic(string $clinicId): array
    {
        $cacheKey = "reviews_clinic_{$clinicId}";

        return Cache::remember($cacheKey, 300, function () use ($clinicId) {
            $firestore = $this->getFirestore();
            if (!$firestore) return [];

            try {
                $docs = $firestore->collection('reviews')
                    ->where('clinic_id', '=', $clinicId)
                    ->where('is_visible', '=', true)
                    ->documents();

                $reviews = [];
                foreach ($docs as $doc) {
                    if (!$doc->exists()) continue;
                    $data = $doc->data();
                    $data['id'] = $doc->id();
                    $reviews[] = $data;
                }

                // Sort by created_at descending
                usort($reviews, function ($a, $b) {
                    return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '');
                });

                return $reviews;
            } catch (\Exception $e) {
                \Log::error("getReviewsForClinic error for clinic {$clinicId}: " . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get all reviews for admin dashboard. Supports filters. Limit 100.
     *
     * @param array $filters Optional: doctor_id, clinic_id, rating, date_from, date_to
     * @return array
     */
    public function getAllReviews(array $filters = []): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $docs = $firestore->collection('reviews')->documents();
            $reviews = [];

            foreach ($docs as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $data['id'] = $doc->id();

                // Apply filters
                if (!empty($filters['doctor_id']) && ($data['doctor_id'] ?? '') !== $filters['doctor_id']) {
                    continue;
                }
                if (!empty($filters['clinic_id']) && ($data['clinic_id'] ?? '') !== $filters['clinic_id']) {
                    continue;
                }
                if (!empty($filters['rating']) && (int) ($data['rating'] ?? 0) !== (int) $filters['rating']) {
                    continue;
                }
                if (!empty($filters['date_from'])) {
                    $createdAt = substr($data['created_at'] ?? '', 0, 10);
                    if ($createdAt < $filters['date_from']) continue;
                }
                if (!empty($filters['date_to'])) {
                    $createdAt = substr($data['created_at'] ?? '', 0, 10);
                    if ($createdAt > $filters['date_to']) continue;
                }

                $reviews[] = $data;
            }

            // Sort by created_at descending
            usort($reviews, function ($a, $b) {
                return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '');
            });

            // Limit to 100
            return array_slice($reviews, 0, 100);
        } catch (\Exception $e) {
            \Log::error('getAllReviews error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Toggle review visibility (show/hide).
     *
     * @param string $reviewId
     * @param bool $visible
     * @return bool
     */
    public function toggleReviewVisibility(string $reviewId, bool $visible): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $firestore->collection('reviews')->document($reviewId)->update([
                ['path' => 'is_visible', 'value' => $visible],
            ]);

            $this->invalidateReviewCaches();
            return true;
        } catch (\Exception $e) {
            \Log::error("toggleReviewVisibility error for review {$reviewId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recalculate and update the doctor's avg_rating and total_reviews fields.
     *
     * @param string $doctorId
     * @param string $clinicId
     * @return void
     */
    public function updateDoctorRating(string $doctorId, string $clinicId): void
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return;

        try {
            // Fetch all reviews for this doctor (including hidden for accurate avg)
            $docs = $firestore->collection('reviews')
                ->where('doctor_id', '=', $doctorId)
                ->documents();

            $totalRating = 0;
            $count = 0;

            foreach ($docs as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                $totalRating += (int) ($data['rating'] ?? 0);
                $count++;
            }

            $avgRating = $count > 0 ? round($totalRating / $count, 1) : 0;

            // Update the doctor document
            $firestore->collection('doctors')->document($doctorId)->update([
                ['path' => 'avg_rating', 'value' => $avgRating],
                ['path' => 'total_reviews', 'value' => $count],
            ]);

            $this->invalidateDoctorCaches();
        } catch (\Exception $e) {
            \Log::error("updateDoctorRating error for doctor {$doctorId}: " . $e->getMessage());
        }
    }

    /**
     * Check if a patient has already reviewed a specific booking.
     *
     * @param string $patientId
     * @param string $bookingId
     * @return bool
     */
    public function hasPatientReviewed(string $patientId, string $bookingId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $docs = $firestore->collection('reviews')
                ->where('patient_id', '=', $patientId)
                ->where('booking_id', '=', $bookingId)
                ->documents();

            foreach ($docs as $doc) {
                if ($doc->exists()) return true;
            }

            return false;
        } catch (\Exception $e) {
            \Log::error("hasPatientReviewed error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Invalidate review-related caches.
     */
    public function invalidateReviewCaches(): void
    {
        $this->forgetCacheByPrefix('reviews_doctor_');
        $this->forgetCacheByPrefix('reviews_clinic_');
    }

    // ─── Coupon / Discount Methods ──────────────────────────────────────

    /**
     * Get cached coupons collection documents.
     */
    public function getCouponsRaw(): array
    {
        return $this->getCollectionCached('coupons', 300);
    }

    /**
     * Invalidate coupon-related caches after writes.
     */
    public function invalidateCouponCaches(): void
    {
        unset($this->requestCache['coupons']);
        Cache::forget('firestore:coupons');
        $this->forgetCacheByPrefix('coupons_');
    }

    /**
     * Get all coupons with optional filters.
     *
     * @param array $filters Optional: clinic_id, is_active
     * @return array
     */
    public function getCoupons(array $filters = []): array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return [];

        try {
            $cacheKey = 'coupons_list_' . md5(json_encode($filters));
            return Cache::remember($cacheKey, 300, function () use ($filters) {
                $documents = $this->getCouponsRaw();
                $coupons = [];

                foreach ($documents as $doc) {
                    if (!$doc->exists()) continue;
                    $data = $doc->data();
                    $data['id'] = $doc->id();

                    // Apply filters
                    if (isset($filters['clinic_id']) && $filters['clinic_id']) {
                        $couponClinic = $data['clinic_id'] ?? null;
                        if ($couponClinic && $couponClinic !== $filters['clinic_id']) continue;
                    }

                    if (isset($filters['is_active'])) {
                        if (($data['is_active'] ?? false) !== $filters['is_active']) continue;
                    }

                    $coupons[] = $data;
                }

                // Sort by created_at descending
                usort($coupons, function ($a, $b) {
                    return ($b['created_at'] ?? '') <=> ($a['created_at'] ?? '');
                });

                return $coupons;
            });
        } catch (\Exception $e) {
            \Log::error('getCoupons error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Find a coupon by its code (case-insensitive).
     *
     * @param string $code
     * @return array|null Coupon data with 'id' key, or null
     */
    public function getCouponByCode(string $code): ?array
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            $documents = $this->getCouponsRaw();
            $upperCode = strtoupper(trim($code));

            foreach ($documents as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();
                if (strtoupper($data['code'] ?? '') === $upperCode) {
                    $data['id'] = $doc->id();
                    return $data;
                }
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('getCouponByCode error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new coupon document. Validates code uniqueness.
     *
     * @param array $data
     * @return string|null Document ID on success, null on failure
     * @throws \Exception if code already exists
     */
    public function createCoupon(array $data): ?string
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return null;

        try {
            // Validate code uniqueness
            $code = strtoupper(trim($data['code'] ?? ''));
            $existing = $this->getCouponByCode($code);
            if ($existing) {
                throw new \Exception('Coupon code already exists');
            }

            $now = (new \DateTime())->format('Y-m-d\TH:i:s\Z');

            $fields = [
                'code' => $code,
                'discount_type' => $data['discount_type'] ?? 'percentage',
                'discount_value' => (float)($data['discount_value'] ?? 0),
                'valid_from' => $data['valid_from'] ?? date('Y-m-d'),
                'valid_to' => $data['valid_to'] ?? date('Y-m-d', strtotime('+30 days')),
                'max_uses' => (int)($data['max_uses'] ?? 0),
                'current_uses' => 0,
                'clinic_id' => !empty($data['clinic_id']) ? $data['clinic_id'] : null,
                'is_active' => (bool)($data['is_active'] ?? true),
                'created_by' => $data['created_by'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $result = $firestore->createDocument('coupons', $fields);
            $this->invalidateCouponCaches();

            if ($result && isset($result['name'])) {
                return basename($result['name']);
            }
            return null;
        } catch (\Exception $e) {
            \Log::error('createCoupon error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update coupon fields.
     *
     * @param string $id Coupon document ID
     * @param array $data Fields to update
     * @return bool
     */
    public function updateCoupon(string $id, array $data): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $updates = [];
            $allowedFields = ['code', 'discount_type', 'discount_value', 'valid_from', 'valid_to', 'max_uses', 'clinic_id', 'is_active'];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $value = $data[$field];
                    if ($field === 'code') $value = strtoupper(trim($value));
                    if ($field === 'discount_value') $value = (float)$value;
                    if ($field === 'max_uses') $value = (int)$value;
                    if ($field === 'is_active') $value = (bool)$value;
                    if ($field === 'clinic_id') $value = !empty($value) ? $value : null;
                    $updates[] = ['path' => $field, 'value' => $value];
                }
            }

            $updates[] = ['path' => 'updated_at', 'value' => (new \DateTime())->format('Y-m-d\TH:i:s\Z')];

            $firestore->collection('coupons')->document($id)->update($updates);
            $this->invalidateCouponCaches();
            return true;
        } catch (\Exception $e) {
            \Log::error('updateCoupon error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a coupon document.
     *
     * @param string $id Coupon document ID
     * @return bool
     */
    public function deleteCoupon(string $id): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $result = $firestore->deleteDocument("coupons/{$id}");
            $this->invalidateCouponCaches();
            return $result;
        } catch (\Exception $e) {
            \Log::error('deleteCoupon error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate a coupon: exists, active, date range, max uses, clinic match.
     *
     * @param string $code
     * @param string|null $clinicId
     * @return array ['valid' => bool, 'coupon' => array|null, 'message' => string|null]
     */
    public function validateCoupon(string $code, ?string $clinicId = null): array
    {
        $coupon = $this->getCouponByCode($code);

        if (!$coupon) {
            return ['valid' => false, 'coupon' => null, 'message' => __('messages.invalid_coupon')];
        }

        if (!($coupon['is_active'] ?? false)) {
            return ['valid' => false, 'coupon' => null, 'message' => __('messages.invalid_coupon')];
        }

        $today = date('Y-m-d');
        $validFrom = $coupon['valid_from'] ?? '';
        $validTo = $coupon['valid_to'] ?? '';

        if ($validFrom && $today < $validFrom) {
            return ['valid' => false, 'coupon' => null, 'message' => __('messages.invalid_coupon')];
        }

        if ($validTo && $today > $validTo) {
            return ['valid' => false, 'coupon' => null, 'message' => __('messages.coupon_expired')];
        }

        $maxUses = (int)($coupon['max_uses'] ?? 0);
        $currentUses = (int)($coupon['current_uses'] ?? 0);
        if ($maxUses > 0 && $currentUses >= $maxUses) {
            return ['valid' => false, 'coupon' => null, 'message' => __('messages.coupon_max_uses_reached')];
        }

        // Check clinic restriction
        $couponClinicId = $coupon['clinic_id'] ?? null;
        if ($couponClinicId && $clinicId && $couponClinicId !== $clinicId) {
            return ['valid' => false, 'coupon' => null, 'message' => __('messages.coupon_not_for_clinic')];
        }

        return ['valid' => true, 'coupon' => $coupon, 'message' => null];
    }

    /**
     * Increment current_uses for a coupon by 1.
     *
     * @param string $couponId
     * @return bool
     */
    public function applyCoupon(string $couponId): bool
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return false;

        try {
            $doc = $firestore->collection('coupons')->document($couponId)->snapshot();
            if (!$doc->exists()) return false;

            $currentUses = (int)($doc->data()['current_uses'] ?? 0);

            $firestore->collection('coupons')->document($couponId)->update([
                ['path' => 'current_uses', 'value' => $currentUses + 1],
                ['path' => 'updated_at', 'value' => (new \DateTime())->format('Y-m-d\TH:i:s\Z')],
            ]);

            $this->invalidateCouponCaches();
            return true;
        } catch (\Exception $e) {
            \Log::error('applyCoupon error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate coupon and calculate discounted price.
     *
     * @param string $couponCode
     * @param float $originalFee
     * @param string|null $clinicId
     * @return array ['success' => bool, 'original' => float, 'discount' => float, 'final' => float, 'coupon_data' => array|null, 'message' => string|null]
     */
    public function calculateDiscount(string $couponCode, float $originalFee, ?string $clinicId = null): array
    {
        $validation = $this->validateCoupon($couponCode, $clinicId);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'original' => $originalFee,
                'discount' => 0,
                'final' => $originalFee,
                'coupon_data' => null,
                'message' => $validation['message'],
            ];
        }

        $coupon = $validation['coupon'];
        $discountType = $coupon['discount_type'] ?? 'percentage';
        $discountValue = (float)($coupon['discount_value'] ?? 0);

        if ($discountType === 'percentage') {
            $discountAmount = round($originalFee * ($discountValue / 100), 2);
        } else {
            $discountAmount = min($discountValue, $originalFee);
        }

        $finalPrice = max(0, round($originalFee - $discountAmount, 2));

        return [
            'success' => true,
            'original' => $originalFee,
            'discount' => $discountAmount,
            'final' => $finalPrice,
            'coupon_data' => $coupon,
            'message' => __('messages.discount_applied'),
        ];
    }
}
