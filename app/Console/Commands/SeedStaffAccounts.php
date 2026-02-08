<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirebaseService;
use Google\Cloud\Firestore\FieldValue;

class SeedStaffAccounts extends Command
{
    protected $signature = 'firebase:seed-staff {--fresh : Delete existing staff users before seeding}';

    protected $description = 'Seed staff accounts (super_admin, hospital_manager, clinic_admin, doctor, reception) into Firestore with login credentials';

    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        parent::__construct();
        $this->firebase = $firebase;
    }

    public function handle()
    {
        $firestore = $this->firebase->getFirestore();

        if (!$firestore) {
            $this->error('Firestore not available. Check your service account and FIREBASE_PROJECT_ID in .env');
            return 1;
        }

        $this->info('Starting staff account seeding...');

        $staffAccounts = $this->getStaffAccounts();

        if ($this->option('fresh')) {
            $this->info('Deleting existing staff accounts...');
            foreach ($staffAccounts as $account) {
                try {
                    $firestore->collection('users')->document($account['id'])->delete();
                    $this->line("  Deleted: {$account['id']}");
                } catch (\Exception $e) {
                    // Document may not exist, that's fine
                }
            }
        }

        $created = 0;
        $updated = 0;

        foreach ($staffAccounts as $account) {
            $id = $account['id'];
            $password = $account['password'];
            unset($account['id'], $account['password']);

            // Hash the password
            $account['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $account['is_active'] = true;
            $account['created_at'] = FieldValue::serverTimestamp();

            // Check if document already exists
            $docRef = $firestore->collection('users')->document($id);
            $snapshot = $docRef->snapshot();

            if ($snapshot->exists()) {
                // Update existing doc with password_hash and any missing fields
                $docRef->set($account, ['merge' => true]);
                $this->line("  Updated: {$account['name']} ({$account['role']}) - {$account['email']}");
                $updated++;
            } else {
                // Create new document
                $docRef->set($account);
                $this->line("  Created: {$account['name']} ({$account['role']}) - {$account['email']}");
                $created++;
            }
        }

        $this->info('');
        $this->info('Staff account seeding completed!');
        $this->info("Created: {$created} | Updated: {$updated}");
        $this->info('');
        $this->info('=== LOGIN CREDENTIALS ===');
        $this->info('');

        $headers = ['Role', 'Name', 'Email', 'Password', 'Clinic', 'Hospital'];
        $rows = [];
        foreach ($this->getStaffAccounts() as $account) {
            $rows[] = [
                $account['role'],
                $account['name'],
                $account['email'],
                $account['password'],
                $account['clinic_id'] ?? '-',
                $account['hospital_id'] ?? '-',
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }

    protected function getStaffAccounts(): array
    {
        return [
            // Super Admin - full system access
            [
                'id'          => 'staff_super_admin',
                'name'        => 'System Administrator',
                'email'       => 'admin@clinicqu.com',
                'password'    => 'Admin@123',
                'role'        => 'super_admin',
                'phone'       => '+966500000001',
                'hospital_id' => 'hospital_001',
                'clinic_id'   => null,
            ],

            // Hospital Manager - manages hospital_001
            [
                'id'          => 'staff_hospital_manager',
                'name'        => 'Hospital Manager',
                'email'       => 'manager@clinicqu.com',
                'password'    => 'Manager@123',
                'role'        => 'hospital_manager',
                'phone'       => '+966500000002',
                'hospital_id' => 'hospital_001',
                'clinic_id'   => null,
            ],

            // Clinic Admin - manages General Medicine clinic
            [
                'id'          => 'staff_clinic_admin',
                'name'        => 'Clinic Admin (General Medicine)',
                'email'       => 'clinicadmin@clinicqu.com',
                'password'    => 'Clinic@123',
                'role'        => 'clinic_admin',
                'phone'       => '+966500000003',
                'hospital_id' => 'hospital_001',
                'clinic_id'   => 'gen_med_001',
            ],

            // Doctor - Dr. Sarah Miller (existing in doctors subcollection)
            [
                'id'          => 'user_doc_sarah',
                'name'        => 'Dr. Sarah Miller',
                'email'       => 'sarah.miller@hospital.sa',
                'password'    => 'Doctor@123',
                'role'        => 'doctor',
                'phone'       => '+966500000004',
                'hospital_id' => 'hospital_001',
                'clinic_id'   => 'gen_med_001',
                'doctor_ref'  => 'doc_sarah_miller',
            ],

            // Doctor - Dr. Emily Wong (existing in doctors subcollection)
            [
                'id'          => 'user_doc_emily',
                'name'        => 'Dr. Emily Wong',
                'email'       => 'emily.wong@hospital.sa',
                'password'    => 'Doctor@123',
                'role'        => 'doctor',
                'phone'       => '+966500000005',
                'hospital_id' => 'hospital_001',
                'clinic_id'   => 'peds_001',
                'doctor_ref'  => 'doc_emily_wong',
            ],

            // Reception - Ahmed (existing)
            [
                'id'          => 'user_reception_001',
                'name'        => 'Ahmed Al-Rashid',
                'email'       => 'ahmed@hospital.sa',
                'password'    => 'Reception@123',
                'role'        => 'reception',
                'phone'       => '+966500000006',
                'hospital_id' => 'hospital_001',
                'clinic_id'   => 'gen_med_001',
            ],

            // Additional Clinic Admin - Pediatrics
            [
                'id'          => 'staff_clinic_admin_peds',
                'name'        => 'Clinic Admin (Pediatrics)',
                'email'       => 'peds.admin@clinicqu.com',
                'password'    => 'Clinic@123',
                'role'        => 'clinic_admin',
                'phone'       => '+966500000007',
                'hospital_id' => 'hospital_001',
                'clinic_id'   => 'peds_001',
            ],
        ];
    }
}
