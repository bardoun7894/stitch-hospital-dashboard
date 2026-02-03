<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirebaseService;
use Google\Cloud\Firestore\FieldValue;

class SeedFirestoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:seed {--fresh : Clear existing data before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed all data (clinics, doctors, patients, bookings, queue states, alerts, settings) into Firestore';

    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        parent::__construct();
        $this->firebase = $firebase;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $firestore = $this->firebase->getFirestore();

        if (!$firestore) {
            $this->error('Firestore not available. Check your service account and FIREBASE_PROJECT_ID in .env');
            return 1;
        }

        $this->info('🔥 Starting Firebase/Firestore seeding...');

        // Seed Clinics (5 total)
        $this->seedClinics($firestore);

        // Seed Doctors as subcollections (5-10 doctors across clinics)
        $this->seedDoctors($firestore);

        // Seed Patients/Users (10+ patients)
        $this->seedPatients($firestore);

        // Seed Bookings (15+ with various statuses)
        $this->seedBookings($firestore);

        // Seed Queue States (for each clinic)
        $this->seedQueueStates($firestore);

        // Seed Alerts (3 system alerts)
        $this->seedAlerts($firestore);

        // Seed Settings (hospital configuration)
        $this->seedSettings($firestore);

        $this->info('');
        $this->info('✅ Firestore seeding completed successfully!');
        $this->info('');
        $this->line('Summary:');
        $this->line(' - 5 Clinics');
        $this->line(' - 10 Doctors (as subcollections)');
        $this->line(' - 12 Patients');
        $this->line(' - 15 Bookings');
        $this->line(' - 5 Queue States');
        $this->line(' - 3 Alerts');
        $this->line(' - 1 Settings Document');

        return 0;
    }

    protected function seedClinics($firestore)
    {
        $this->info('📍 Seeding Clinics...');

        $clinics = [
            [
                'id' => 'gen_med_001',
                'name' => 'General Medicine',
                'name_en' => 'General Medicine',
                'doctors_on_duty' => 2,
                'patients_waiting' => 8,
                'avg_wait' => '15 min',
                'status' => 'Open',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            [
                'id' => 'peds_001',
                'name' => 'Pediatrics',
                'name_en' => 'Pediatrics',
                'doctors_on_duty' => 2,
                'patients_waiting' => 5,
                'avg_wait' => '12 min',
                'status' => 'Open',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            [
                'id' => 'cardio_001',
                'name' => 'Cardiology',
                'name_en' => 'Cardiology',
                'doctors_on_duty' => 2,
                'patients_waiting' => 10,
                'avg_wait' => '22 min',
                'status' => 'Open',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            [
                'id' => 'derma_001',
                'name' => 'Dermatology',
                'name_en' => 'Dermatology',
                'doctors_on_duty' => 1,
                'patients_waiting' => 3,
                'avg_wait' => '10 min',
                'status' => 'Open',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            [
                'id' => 'ortho_001',
                'name' => 'Orthopedics',
                'name_en' => 'Orthopedics',
                'doctors_on_duty' => 1,
                'patients_waiting' => 6,
                'avg_wait' => '18 min',
                'status' => 'Open',
                'created_at' => FieldValue::serverTimestamp(),
            ]
        ];

        foreach ($clinics as $clinicData) {
            $id = $clinicData['id'];
            unset($clinicData['id']);

            $firestore->collection('clinics')->document($id)->set($clinicData);
            $this->line("  ✓ Clinic: {$clinicData['name_en']}");
        }
    }

    protected function seedDoctors($firestore)
    {
        $this->info('👨‍⚕️ Seeding Doctors (as subcollections)...');

        $doctors = [
            // General Medicine doctors
            [
                'id' => 'doc_sarah_miller',
                'clinic_id' => 'gen_med_001',
                'name' => 'Dr. Sarah Miller',
                'specialty' => 'General',
                'clinic_name' => 'General Medicine',
                'status' => 'On Duty',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            [
                'id' => 'doc_john_smith',
                'clinic_id' => 'gen_med_001',
                'name' => 'Dr. John Smith',
                'specialty' => 'General',
                'clinic_name' => 'General Medicine',
                'status' => 'On Duty',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            // Pediatrics doctors
            [
                'id' => 'doc_emily_wong',
                'clinic_id' => 'peds_001',
                'name' => 'Dr. Emily Wong',
                'specialty' => 'Pediatrics',
                'clinic_name' => 'Pediatrics',
                'status' => 'On Duty',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            [
                'id' => 'doc_david_lee',
                'clinic_id' => 'peds_001',
                'name' => 'Dr. David Lee',
                'specialty' => 'Pediatrics',
                'clinic_name' => 'Pediatrics',
                'status' => 'On Duty',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            // Cardiology doctors
            [
                'id' => 'doc_james_chen',
                'clinic_id' => 'cardio_001',
                'name' => 'Dr. James Chen',
                'specialty' => 'Cardiology',
                'clinic_name' => 'Cardiology',
                'status' => 'On Duty',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            [
                'id' => 'doc_maria_garcia',
                'clinic_id' => 'cardio_001',
                'name' => 'Dr. Maria Garcia',
                'specialty' => 'Cardiology',
                'clinic_name' => 'Cardiology',
                'status' => 'On Duty',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            // Dermatology doctor
            [
                'id' => 'doc_linda_kim',
                'clinic_id' => 'derma_001',
                'name' => 'Dr. Linda Kim',
                'specialty' => 'Dermatology',
                'clinic_name' => 'Dermatology',
                'status' => 'On Call',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            [
                'id' => 'doc_robert_brown',
                'clinic_id' => 'derma_001',
                'name' => 'Dr. Robert Brown',
                'specialty' => 'Dermatology',
                'clinic_name' => 'Dermatology',
                'status' => 'Off Duty',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            // Orthopedics doctors
            [
                'id' => 'doc_michael_ross',
                'clinic_id' => 'ortho_001',
                'name' => 'Dr. Michael Ross',
                'specialty' => 'Orthopedics',
                'clinic_name' => 'Orthopedics',
                'status' => 'On Duty',
                'created_at' => FieldValue::serverTimestamp(),
            ],
            [
                'id' => 'doc_anna_taylor',
                'clinic_id' => 'ortho_001',
                'name' => 'Dr. Anna Taylor',
                'specialty' => 'Orthopedics',
                'clinic_name' => 'Orthopedics',
                'status' => 'On Call',
                'created_at' => FieldValue::serverTimestamp(),
            ],
        ];

        foreach ($doctors as $docData) {
            $id = $docData['id'];
            $clinicId = $docData['clinic_id'];
            unset($docData['id']);

            // Add doctor as subcollection: clinics/{clinicId}/doctors/{doctorId}
            $firestore
                ->collection('clinics')
                ->document($clinicId)
                ->collection('doctors')
                ->document($id)
                ->set($docData);

            $this->line("  ✓ Doctor: {$docData['name']} → {$docData['clinic_name']}");
        }
    }

    protected function seedPatients($firestore)
    {
        $this->info('👥 Seeding Patients/Users...');

        $patients = [
            ['id' => 'patient_sarah_jenkins', 'name' => 'Sarah Jenkins', 'phone' => '555-0123', 'email' => 'sarah.jenkins@example.com', 'type' => 'patient'],
            ['id' => 'patient_john_doe', 'name' => 'John Doe', 'phone' => '555-0124', 'email' => 'john.doe@example.com', 'type' => 'patient'],
            ['id' => 'patient_emma_wilson', 'name' => 'Emma Wilson', 'phone' => '555-0125', 'email' => 'emma.wilson@example.com', 'type' => 'patient'],
            ['id' => 'patient_michael_brown', 'name' => 'Michael Brown', 'phone' => '555-0126', 'email' => 'michael.brown@example.com', 'type' => 'patient'],
            ['id' => 'patient_olivia_davis', 'name' => 'Olivia Davis', 'phone' => '555-0127', 'email' => 'olivia.davis@example.com', 'type' => 'patient'],
            ['id' => 'patient_william_moore', 'name' => 'William Moore', 'phone' => '555-0128', 'email' => 'william.moore@example.com', 'type' => 'patient'],
            ['id' => 'patient_sophia_taylor', 'name' => 'Sophia Taylor', 'phone' => '555-0129', 'email' => 'sophia.taylor@example.com', 'type' => 'patient'],
            ['id' => 'patient_james_anderson', 'name' => 'James Anderson', 'phone' => '555-0130', 'email' => 'james.anderson@example.com', 'type' => 'patient'],
            ['id' => 'patient_ava_thomas', 'name' => 'Ava Thomas', 'phone' => '555-0131', 'email' => 'ava.thomas@example.com', 'type' => 'patient'],
            ['id' => 'patient_robert_jackson', 'name' => 'Robert Jackson', 'phone' => '555-0132', 'email' => 'robert.jackson@example.com', 'type' => 'patient'],
            ['id' => 'patient_mia_white', 'name' => 'Mia White', 'phone' => '555-0133', 'email' => 'mia.white@example.com', 'type' => 'patient'],
            ['id' => 'patient_daniel_harris', 'name' => 'Daniel Harris', 'phone' => '555-0134', 'email' => 'daniel.harris@example.com', 'type' => 'patient'],
        ];

        foreach ($patients as $patientData) {
            $id = $patientData['id'];
            unset($patientData['id']);
            $patientData['created_at'] = FieldValue::serverTimestamp();

            $firestore->collection('users')->document($id)->set($patientData);
            $this->line("  ✓ Patient: {$patientData['name']}");
        }
    }

    protected function seedBookings($firestore)
    {
        $this->info('📅 Seeding Bookings...');

        $today = date('Y-m-d');

        $bookings = [
            // Waiting bookings (6)
            ['id' => 'book_001', 'clinic_id' => 'gen_med_001', 'clinic_name' => 'General Medicine', 'doctor_id' => 'doc_sarah_miller', 'doctor_name' => 'Dr. Sarah Miller', 'patient_id' => 'patient_sarah_jenkins', 'patient_name' => 'Sarah Jenkins', 'booking_date' => $today, 'booking_time' => '09:00', 'status' => 'waiting', 'wait_time' => 12, 'queue_number' => 'A001'],
            ['id' => 'book_002', 'clinic_id' => 'peds_001', 'clinic_name' => 'Pediatrics', 'doctor_id' => 'doc_emily_wong', 'doctor_name' => 'Dr. Emily Wong', 'patient_id' => 'patient_emma_wilson', 'patient_name' => 'Emma Wilson', 'booking_date' => $today, 'booking_time' => '09:30', 'status' => 'waiting', 'wait_time' => 8, 'queue_number' => 'P001'],
            ['id' => 'book_003', 'clinic_id' => 'cardio_001', 'clinic_name' => 'Cardiology', 'doctor_id' => 'doc_james_chen', 'doctor_name' => 'Dr. James Chen', 'patient_id' => 'patient_john_doe', 'patient_name' => 'John Doe', 'booking_date' => $today, 'booking_time' => '10:00', 'status' => 'waiting', 'wait_time' => 15, 'queue_number' => 'C001'],
            ['id' => 'book_004', 'clinic_id' => 'gen_med_001', 'clinic_name' => 'General Medicine', 'doctor_id' => 'doc_john_smith', 'doctor_name' => 'Dr. John Smith', 'patient_id' => 'patient_michael_brown', 'patient_name' => 'Michael Brown', 'booking_date' => $today, 'booking_time' => '10:30', 'status' => 'waiting', 'wait_time' => 10, 'queue_number' => 'A002'],
            ['id' => 'book_005', 'clinic_id' => 'ortho_001', 'clinic_name' => 'Orthopedics', 'doctor_id' => 'doc_michael_ross', 'doctor_name' => 'Dr. Michael Ross', 'patient_id' => 'patient_olivia_davis', 'patient_name' => 'Olivia Davis', 'booking_date' => $today, 'booking_time' => '11:00', 'status' => 'waiting', 'wait_time' => 18, 'queue_number' => 'O001'],
            ['id' => 'book_006', 'clinic_id' => 'derma_001', 'clinic_name' => 'Dermatology', 'doctor_id' => 'doc_linda_kim', 'doctor_name' => 'Dr. Linda Kim', 'patient_id' => 'patient_william_moore', 'patient_name' => 'William Moore', 'booking_date' => $today, 'booking_time' => '11:30', 'status' => 'waiting', 'wait_time' => 5, 'queue_number' => 'D001'],

            // In progress bookings (2)
            ['id' => 'book_007', 'clinic_id' => 'peds_001', 'clinic_name' => 'Pediatrics', 'doctor_id' => 'doc_david_lee', 'doctor_name' => 'Dr. David Lee', 'patient_id' => 'patient_sophia_taylor', 'patient_name' => 'Sophia Taylor', 'booking_date' => $today, 'booking_time' => '09:00', 'status' => 'in_progress', 'wait_time' => 0, 'queue_number' => 'P002'],
            ['id' => 'book_008', 'clinic_id' => 'cardio_001', 'clinic_name' => 'Cardiology', 'doctor_id' => 'doc_maria_garcia', 'doctor_name' => 'Dr. Maria Garcia', 'patient_id' => 'patient_james_anderson', 'patient_name' => 'James Anderson', 'booking_date' => $today, 'booking_time' => '09:30', 'status' => 'in_progress', 'wait_time' => 0, 'queue_number' => 'C002'],

            // Completed bookings (5)
            ['id' => 'book_009', 'clinic_id' => 'gen_med_001', 'clinic_name' => 'General Medicine', 'doctor_id' => 'doc_sarah_miller', 'doctor_name' => 'Dr. Sarah Miller', 'patient_id' => 'patient_ava_thomas', 'patient_name' => 'Ava Thomas', 'booking_date' => $today, 'booking_time' => '08:00', 'status' => 'completed', 'wait_time' => 10, 'queue_number' => 'A003'],
            ['id' => 'book_010', 'clinic_id' => 'gen_med_001', 'clinic_name' => 'General Medicine', 'doctor_id' => 'doc_john_smith', 'doctor_name' => 'Dr. John Smith', 'patient_id' => 'patient_robert_jackson', 'patient_name' => 'Robert Jackson', 'booking_date' => $today, 'booking_time' => '08:30', 'status' => 'completed', 'wait_time' => 12, 'queue_number' => 'A004'],
            ['id' => 'book_011', 'clinic_id' => 'peds_001', 'clinic_name' => 'Pediatrics', 'doctor_id' => 'doc_emily_wong', 'doctor_name' => 'Dr. Emily Wong', 'patient_id' => 'patient_mia_white', 'patient_name' => 'Mia White', 'booking_date' => $today, 'booking_time' => '08:00', 'status' => 'completed', 'wait_time' => 8, 'queue_number' => 'P003'],
            ['id' => 'book_012', 'clinic_id' => 'cardio_001', 'clinic_name' => 'Cardiology', 'doctor_id' => 'doc_james_chen', 'doctor_name' => 'Dr. James Chen', 'patient_id' => 'patient_daniel_harris', 'patient_name' => 'Daniel Harris', 'booking_date' => $today, 'booking_time' => '08:00', 'status' => 'completed', 'wait_time' => 20, 'queue_number' => 'C003'],
            ['id' => 'book_013', 'clinic_id' => 'ortho_001', 'clinic_name' => 'Orthopedics', 'doctor_id' => 'doc_michael_ross', 'doctor_name' => 'Dr. Michael Ross', 'patient_id' => 'patient_sarah_jenkins', 'patient_name' => 'Sarah Jenkins', 'booking_date' => $today, 'booking_time' => '08:30', 'status' => 'completed', 'wait_time' => 15, 'queue_number' => 'O002'],

            // No show booking (1)
            ['id' => 'book_014', 'clinic_id' => 'derma_001', 'clinic_name' => 'Dermatology', 'doctor_id' => 'doc_linda_kim', 'doctor_name' => 'Dr. Linda Kim', 'patient_id' => 'patient_john_doe', 'patient_name' => 'John Doe', 'booking_date' => $today, 'booking_time' => '09:00', 'status' => 'no_show', 'wait_time' => 0, 'queue_number' => 'D002'],

            // Cancelled booking (1)
            ['id' => 'book_015', 'clinic_id' => 'gen_med_001', 'clinic_name' => 'General Medicine', 'doctor_id' => 'doc_sarah_miller', 'doctor_name' => 'Dr. Sarah Miller', 'patient_id' => 'patient_emma_wilson', 'patient_name' => 'Emma Wilson', 'booking_date' => $today, 'booking_time' => '10:00', 'status' => 'cancelled', 'wait_time' => 0, 'queue_number' => 'A005'],
        ];

        foreach ($bookings as $bookingData) {
            $id = $bookingData['id'];
            unset($bookingData['id']);
            $bookingData['created_at'] = FieldValue::serverTimestamp();

            $firestore->collection('bookings')->document($id)->set($bookingData);
            $this->line("  ✓ Booking: {$bookingData['patient_name']} → {$bookingData['clinic_name']} ({$bookingData['status']})");
        }
    }

    protected function seedQueueStates($firestore)
    {
        $this->info('📊 Seeding Queue States...');

        $queueStates = [
            ['clinic_id' => 'gen_med_001', 'current_number' => 'A003', 'waiting_count' => 8, 'is_paused' => false],
            ['clinic_id' => 'peds_001', 'current_number' => 'P002', 'waiting_count' => 5, 'is_paused' => false],
            ['clinic_id' => 'cardio_001', 'current_number' => 'C002', 'waiting_count' => 10, 'is_paused' => false],
            ['clinic_id' => 'derma_001', 'current_number' => 'D001', 'waiting_count' => 3, 'is_paused' => false],
            ['clinic_id' => 'ortho_001', 'current_number' => 'O001', 'waiting_count' => 6, 'is_paused' => false],
        ];

        foreach ($queueStates as $queueData) {
            $clinicId = $queueData['clinic_id'];
            $queueData['last_updated'] = FieldValue::serverTimestamp();

            $firestore->collection('queue_states')->document($clinicId)->set($queueData);
            $this->line("  ✓ Queue State: {$clinicId}");
        }
    }

    protected function seedAlerts($firestore)
    {
        $this->info('🔔 Seeding Alerts...');

        $alerts = [
            ['id' => 'alert_001', 'message' => 'High patient volume in General Medicine', 'type' => 'info', 'clinic_id' => 'gen_med_001'],
            ['id' => 'alert_002', 'message' => 'Dr. Chen running 15 minutes behind schedule', 'type' => 'warning', 'clinic_id' => 'cardio_001'],
            ['id' => 'alert_003', 'message' => 'System maintenance scheduled for 2 AM', 'type' => 'info', 'clinic_id' => null],
        ];

        foreach ($alerts as $alertData) {
            $id = $alertData['id'];
            unset($alertData['id']);
            $alertData['created_at'] = FieldValue::serverTimestamp();

            $firestore->collection('alerts')->document($id)->set($alertData);
            $this->line("  ✓ Alert: {$alertData['message']}");
        }
    }

    protected function seedSettings($firestore)
    {
        $this->info('⚙️  Seeding Settings...');

        $settings = [
            'hospital_name' => 'City General Hospital',
            'timezone' => 'Asia/Singapore',
            'queue_display_mode' => 'full',
            'auto_advance_queue' => true,
            'updated_at' => FieldValue::serverTimestamp(),
        ];

        $firestore->collection('settings')->document('hospital_config')->set($settings);
        $this->line("  ✓ Settings: hospital_config");
    }
}
