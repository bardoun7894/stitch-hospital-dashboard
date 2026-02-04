<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;
use Google\Cloud\Firestore\FirestoreClient;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected $firestoreClient = null;
    protected $messaging = null;

    public function __construct()
    {
        // Check for service account file
        $credentialsPath = base_path(env('FIREBASE_CREDENTIALS', 'firebase.json'));

        if (file_exists($credentialsPath)) {
            // Initialize Firestore with custom REST client (bypasses gRPC)
            try {
                $this->firestoreClient = new FirestoreRestClient(
                    $credentialsPath,
                    env('FIREBASE_PROJECT_ID', 'clinicqu-1e93c')
                );
            } catch (\Exception $e) {
                \Log::error('Firestore Init Error: ' . $e->getMessage());
            }

            // Initialize Messaging (requires gRPC - may fail in Docker without extension)
            try {
                $factory = (new Factory)->withServiceAccount($credentialsPath);
                $this->messaging = $factory->createMessaging();
            } catch (\Exception $e) {
                \Log::warning('Firebase Messaging Init Skipped (gRPC not available): ' . $e->getMessage());
                // Continue without messaging - Firestore will still work
            }
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
            return ['data' => $this->getMockBookings(), 'next_cursor' => null];
        }

        try {
            $query = $this->firestoreClient->collection('bookings')
                ->orderBy('created_at', 'DESC')
                ->limit($limit + 1); // Fetch one extra to check if there's a next page

            // Apply filters
            if ($status) {
                $query = $query->where('status', '=', $status);
            }
            if ($clinicId) {
                $query = $query->where('clinic_id', '=', $clinicId);
            }
            
            // Pagination cursor
            if ($startAfter) {
                $cursorDoc = $this->firestoreClient->collection('bookings')->document($startAfter)->snapshot();
                if ($cursorDoc->exists()) {
                    $query = $query->startAfter($cursorDoc);
                }
            }

            $documents = $query->documents();

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
            return ['data' => $this->getMockBookings(), 'next_cursor' => null];
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
                $snapshot = $firestore->collection('clinics')->documents();
                $today = date('Y-m-d');

                // Pre-fetch today's bookings grouped by clinic
                $bookingsByClinic = [];
                $allBookings = $firestore->collection('bookings')->documents();
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

                // Pre-fetch doctors count per clinic
                $doctorsByClinic = [];
                $allDoctors = $firestore->collection('doctors')->documents();
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
                        'status' => $data['status'] ?? 'off',
                    ];
                }
            }

            return !empty($doctors) ? $doctors : $this->getMockDoctors();
        } catch (\Exception $e) {
            \Log::error('Firestore getDoctors error: ' . $e->getMessage());
            return $this->getMockDoctors();
        }
    }

    protected function getMockDoctors()
    {
        return [];
    }

    public function getPatients()
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
             return $this->getMockPatients(); // Fallback
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
                   str_contains($p['phone'], $searchQuery);
        });
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

            return !empty($alerts) ? $alerts : $this->getMockAlerts();
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

                $allBookings = $firestore->collection('bookings')->documents();
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

            return $this->getMockSettings();
        } catch (\Exception $e) {
            \Log::error('Firestore getSettings error: ' . $e->getMessage());
            return $this->getMockSettings();
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
            return $this->getMockCurrentUser();
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

            return $this->getMockCurrentUser();
        } catch (\Exception $e) {
            \Log::error('Firestore getCurrentUser error: ' . $e->getMessage());
            return $this->getMockCurrentUser();
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

            $allBookings = $firestore
                ->collection('bookings')
                ->documents();

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

        $firestore->collection('doctors')
            ->document($doctorId)
            ->update([
                ['path' => 'schedule', 'value' => $scheduleData['working_hours']],
                ['path' => 'updated_at', 'value' => new \DateTime()],
            ]);
    }

    /**
     * Add unavailability period for a doctor.
     * 
     * @param string $doctorId
     * @param array $data ['start' => DateTime, 'end' => DateTime, 'reason' => string]
     * @return string The ID of the created document
     */
    public function addDoctorUnavailability(string $doctorId, array $data)
    {
        $firestore = $this->getFirestore();
        if (!$firestore) return 'mock-id';

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
             // Return specific mock if id matches, else generic
             return $this->getMockPatientDetails($id);
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
             // Fallback to mock clinics if needed, or return structured mock
             $clinics = $this->getClinics();
             return [
                 'stats' => [
                     'total_patients' => 124,
                     'active_clinics' => 5,
                     'doctors_active' => 12,
                     'avg_wait_hospital' => '24m'
                 ],
                 'clinics' => $clinics
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

    public function getQueueData()
    {
        $firestore = $this->getFirestore();
        if (!$firestore) {
            return $this->getMockQueueData();
        }

        try {
            $today = new \DateTime('today');
            $tomorrow = new \DateTime('tomorrow'); // Midnight tomorrow
            
            // Fetch bookings for today
            $bookingsRef = $firestore->collection('bookings');
            $snapshot = $bookingsRef->documents();
            
            // Get Queue State for Now Serving
            // Scan all clinics/doctors to find active queue data for today
            $nowServing = 0;
            $activeClinicId = null;
            $activeDoctorId = null;
            try {
                $dateKey = date('Y-m-d');
                $clinicsDocs = $firestore->collection('clinics')->documents();
                foreach ($clinicsDocs as $clinicDoc) {
                    if (!$clinicDoc->exists()) continue;
                    $doctorsDocs = $firestore->collection('clinics')
                        ->document($clinicDoc->id())
                        ->collection('doctors')
                        ->documents();
                    foreach ($doctorsDocs as $doctorDoc) {
                        if (!$doctorDoc->exists()) continue;
                        $queueDoc = $firestore->collection('clinics')
                            ->document($clinicDoc->id())
                            ->collection('doctors')
                            ->document($doctorDoc->id())
                            ->collection('dates')
                            ->document($dateKey)
                            ->snapshot();
                        if ($queueDoc->exists()) {
                            $serving = $queueDoc->data()['now_serving'] ?? 0;
                            if ($serving > $nowServing) {
                                $nowServing = $serving;
                                $activeClinicId = $clinicDoc->id();
                                $activeDoctorId = $doctorDoc->id();
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Queue state not yet initialized
            }
            // No fallback — if no active queue found, leave null
            // The UI will show an empty queue state
            
            $bookings = [];
            $stats = [
                'bookings_today' => 0,
                'bookings_trend' => '+0%',
                'arrived' => 0,
                'arrived_trend' => '+0%',
                'avg_wait' => '15 min',
                'wait_trend' => '0%'
            ];
            
            $nextUp = [];
            $skipped = [];
            $currentServing = [
                'id' => null,
                'token' => str_pad($nowServing, 2, '0', STR_PAD_LEFT),
                'patient' => 'Waiting for patient...',
                'type' => '-',
                'room' => '1'
            ];

            // Cache for patient/doctor name lookups
            $patientCache = [];
            $doctorCache = [];

            foreach ($snapshot as $doc) {
                if (!$doc->exists()) continue;
                $data = $doc->data();

                // Parse date
                $scheduledDate = null;
                if (isset($data['scheduled_date'])) {
                    if ($data['scheduled_date'] instanceof \Google\Cloud\Core\Timestamp) {
                        $scheduledDate = $data['scheduled_date']->get()->format('Y-m-d H:i:s');
                    } else {
                        $scheduledDate = $data['scheduled_date'];
                    }
                }

                if (!$scheduledDate) continue;

                // Filter for today
                $bookingTime = strtotime($scheduledDate);
                if ($bookingTime < $today->getTimestamp() || $bookingTime >= $tomorrow->getTimestamp()) {
                    continue;
                }

                $stats['bookings_today']++;

                $status = $data['status'] ?? 'pending';
                if ($status === 'arrived' || isset($data['arrived_at'])) {
                    $stats['arrived']++;
                }

                $formattedStatus = ucfirst($status);
                if ($status === 'acceptedAwaitingPayment') $formattedStatus = 'Accepted';
                if ($status === 'cancelledByClinic') $formattedStatus = 'Cancelled';
                if ($status === 'cancelledByUser') $formattedStatus = 'Cancelled';
                if ($status === 'noShow') $formattedStatus = 'No Show / Skipped';
                if ($data['is_reinserted'] ?? false) $formattedStatus = 'Re-inserted';

                $token = $data['token_number'] ?? 0;

                // Resolve patient name from patient_id or use denormalized field
                $patientName = $data['patient_name'] ?? null;
                if (!$patientName && isset($data['patient_id'])) {
                    $pid = $data['patient_id'];
                    if (!isset($patientCache[$pid])) {
                        $patientCache[$pid] = $this->getPatientDetails($pid)['name'] ?? 'Patient';
                    }
                    $patientName = $patientCache[$pid];
                }
                $patientName = $patientName ?: 'Guest';

                // Resolve doctor name for type display
                $doctorName = $data['doctor_name'] ?? null;
                if (!$doctorName && isset($data['doctor_id'])) {
                    $did = $data['doctor_id'];
                    if (!isset($doctorCache[$did])) {
                        $detail = $this->getDoctorDetails($data['clinic_id'] ?? '', $did);
                        $doctorCache[$did] = $this->getLocalizedField($detail, 'name', 'Doctor');
                    }
                    $doctorName = $doctorCache[$did];
                }

                $booking = [
                    'id' => $doc->id(),
                    'token' => $token > 0 ? '#' . str_pad($token, 2, '0', STR_PAD_LEFT) : 'PENDING',
                    'token_num' => $token,
                    'patient' => $patientName,
                    'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($patientName) . '&background=random',
                    'type' => $doctorName ?? ($data['type'] ?? 'General'),
                    'status' => $formattedStatus,
                    'color' => $this->getStatusColor($status),
                    'time' => date('h:i A', $bookingTime),
                    'is_arrived' => ($status === 'arrived' || isset($data['arrived_at'])),
                    'is_reinserted' => $data['is_reinserted'] ?? false,
                    'last_recalled_at' => isset($data['last_recalled_at']) ?
                        ($data['last_recalled_at'] instanceof \Google\Cloud\Core\Timestamp ? $data['last_recalled_at']->get()->getTimestamp() : strtotime($data['last_recalled_at']))
                        : null
                ];
                
                $bookings[] = $booking;
                
                // Identify Current Serving
                if ($token == $nowServing && $nowServing > 0) {
                     $currentServing = $booking;
                     $currentServing['room'] = '1'; 
                }
                
                // Identify Next Up (Tokens > Now Serving OR Re-inserted < Now Serving)
                // Logic:
                // 1. If Re-inserted AND Status is Confirmed/Arrived, show in Next Up with Priority?
                // 2. OR just show standard tokens > nowServing. 
                // Let's mix: If Re-inserted, treat as high priority or just add to list if status is appropriate.
                
                if ($status === 'confirmed' || $status === 'arrived') {
                    // Standard logic: Token > Now Serving
                    if ($token > $nowServing) {
                         // Only add if not already full
                         if (count($nextUp) < 3) {
                            $booking['wait'] = '~' . (($token - $nowServing) * 10) . ' mins';
                            $nextUp[] = $booking;
                        }
                    }
                    // Re-inserted logic: Token < Now Serving but "active" again
                    elseif (($data['is_reinserted'] ?? false) == true) {
                        // Showing re-inserted patient at top of Next Up?
                        // Or just in list. Let's prepend to nextUp to show priority.
                        $booking['wait'] = 'Next Available';
                        array_unshift($nextUp, $booking);
                        // Trim to 3
                        if (count($nextUp) > 3) array_pop($nextUp);
                    }
                }
                
                // Skipped List
                if ($status === 'noShow') {
                    $skipped[] = $booking;
                }
            }
            
            // Sort bookings by token/time
            usort($bookings, function($a, $b) {
                return $a['token_num'] <=> $b['token_num'];
            });
            
            // Re-sort Next Up: Re-inserted first, then by token
            usort($nextUp, function($a, $b) {
                $aRe = $a['is_reinserted'] ?? false;
                $bRe = $b['is_reinserted'] ?? false;
                if ($aRe && !$bRe) return -1;
                if (!$aRe && $bRe) return 1;
                return $a['token_num'] <=> $b['token_num'];
            });
            
            return [
                'stats' => $stats,
                'current_serving' => $currentServing,
                'next_up' => $nextUp,
                'bookings' => $bookings,
                'skipped' => $skipped,
                'queue_state' => [
                    'clinic_id' => $activeClinicId,
                    'doctor_id' => $activeDoctorId,
                    'date' => date('Y-m-d'),
                    'now_serving' => $nowServing,
                ],
            ];

        } catch (\Exception $e) {
            // Fallback to mock on error
            return $this->getMockQueueData();
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
                'bookings_trend' => '0%',
                'arrived' => 0,
                'arrived_trend' => '0%',
                'avg_wait' => '0 min',
                'wait_trend' => '0%'
            ],
            'current_serving' => null,
            'next_up' => [],
            'bookings' => [],
            'skipped' => []
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
                $userId = $bookingData['user_id'] ?? null;
                
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
    protected function sendFCMNotification(string $fcmToken, string $title, string $body, array $data = []): bool
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
            if (!$this->messaging) {
                \Log::warning("FCM: Messaging not initialized, notification stored in Firestore only");
                return $userId ? true : false;
            }

            $message = CloudMessage::new()
                ->withToken($fcmToken)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);

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
            // TODO: Integrate with SMS Gateway (Twilio, Unifonic, etc.)
            // For now, log the SMS
            \Log::info("SMS Notification: To {$phone} - Msg: {$message}");
            
            // Example Twilio Implementation:
            // $twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
            // $twilio->messages->create($phone, ['from' => env('TWILIO_NUMBER'), 'body' => $message]);

            return true;
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
            $now = new \Google\Cloud\Core\Timestamp(new \DateTime());

            $docRef = $this->firestoreClient
                ->collection('bookings')
                ->add([
                    'patient_id' => $data['patient_id'],
                    'clinic_id' => $data['clinic_id'],
                    'doctor_id' => $data['doctor_id'],
                    'scheduled_date' => new \Google\Cloud\Core\Timestamp($scheduledDate),
                    'notes' => $data['notes'] ?? '',
                    'family_member_ids' => $data['family_member_ids'] ?? [],
                    'includes_self' => $data['includes_self'] ?? true,
                    'doctor_name' => $data['doctor_name'] ?? '',
                    'patient_name' => $data['patient_name'] ?? '',
                    'status' => $data['status'] ?? 'pending',
                    'payment_status' => $data['payment_status'] ?? 'unpaid',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

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
                    ['path' => 'updated_at', 'value' => new \Google\Cloud\Core\Timestamp(new \DateTime())],
                ]);
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
        } catch (\Exception $e) {
            \Log::error('markArrived error: ' . $e->getMessage());
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
     */
    public function addFamilyMember($userId, array $memberData)
    {
        if (!$this->firestoreClient) {
            throw new \Exception('Firestore client not initialized');
        }

        try {
            $profile = $this->getUserProfile($userId);
            $familyMembers = $profile['family_members'] ?? [];

            // Generate unique ID for member
            $memberData['id'] = uniqid('member_');
            $memberData['created_at'] = new \Google\Cloud\Core\Timestamp(new \DateTime());

            $familyMembers[] = $memberData;

            $this->firestoreClient
                ->collection('users')
                ->document($userId)
                ->update([
                    ['path' => 'family_members', 'value' => $familyMembers],
                ]);
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
            $profile = $this->getUserProfile($userId);
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
            // Get doctor's schedule
            $doctor = $this->getDoctorDetails($clinicId, $doctorId);
            $schedule = $doctor['schedule'] ?? [];

            // Get day of week
            $dayOfWeek = strtolower(date('l', strtotime($date)));

            if (!isset($schedule[$dayOfWeek]) || !$schedule[$dayOfWeek]['enabled']) {
                return [];
            }

            $daySchedule = $schedule[$dayOfWeek];
            $startTime = $daySchedule['open'];
            $endTime = $daySchedule['close'];

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
                    $bookingDate = date('Y-m-d', $data['scheduled_date']->get()->getTimestamp());
                    if ($bookingDate === $date) {
                        $bookingTime = date('H:i', $data['scheduled_date']->get()->getTimestamp());
                        $bookedSlots[] = $bookingTime;
                    }
                }
            }

            // Generate 30-minute slots
            $slots = [];
            $currentTime = strtotime($startTime);
            $endTimeStamp = strtotime($endTime);

            while ($currentTime < $endTimeStamp) {
                $timeSlot = date('H:i', $currentTime);
                $slots[] = [
                    'time' => $timeSlot,
                    'available' => !in_array($timeSlot, $bookedSlots),
                ];
                $currentTime = strtotime('+30 minutes', $currentTime);
            }

            return $slots;
        } catch (\Exception $e) {
            \Log::error('getAvailableSlots error: ' . $e->getMessage());
            return [];
        }
    }
}
