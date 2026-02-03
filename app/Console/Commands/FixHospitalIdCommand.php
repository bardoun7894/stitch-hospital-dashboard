<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirebaseService;

class FixHospitalIdCommand extends Command
{
    protected $signature = 'firebase:fix-hospital-id';
    protected $description = 'Fix clinic hospital_id references to match actual hospital document IDs';

    public function handle()
    {
        $firebaseService = app(FirebaseService::class);
        $firestore = $firebaseService->getFirestore();

        if (!$firestore) {
            $this->error('Firestore not available');
            return 1;
        }

        // Get all hospitals
        $hospitalDocs = $firestore->collection('hospitals')->documents();
        $hospitalIds = [];
        foreach ($hospitalDocs as $doc) {
            if ($doc->exists()) {
                $hospitalIds[] = $doc->id();
                $this->info("Hospital found: {$doc->id()} - " . ($doc->data()['name'] ?? 'unnamed'));
            }
        }

        if (empty($hospitalIds)) {
            $this->error('No hospitals found');
            return 1;
        }

        $targetHospitalId = $hospitalIds[0]; // Use first hospital
        $this->info("Target hospital_id: {$targetHospitalId}");

        // Get all clinics and fix hospital_id
        $clinicDocs = $firestore->collection('clinics')->documents();
        $fixed = 0;
        foreach ($clinicDocs as $doc) {
            if (!$doc->exists()) continue;
            $data = $doc->data();
            $currentHospitalId = $data['hospital_id'] ?? '';

            if ($currentHospitalId !== $targetHospitalId) {
                $this->info("Fixing clinic {$doc->id()}: '{$currentHospitalId}' → '{$targetHospitalId}'");
                $result = $firestore->patch(
                    "clinics/{$doc->id()}",
                    ['hospital_id' => $targetHospitalId],
                    ['hospital_id']
                );
                if ($result) {
                    $fixed++;
                    $this->info("  ✓ Fixed");
                } else {
                    $this->error("  ✗ Failed to update");
                }
            } else {
                $this->info("Clinic {$doc->id()}: already correct");
            }
        }

        $this->info("Done. Fixed {$fixed} clinic(s).");
        return 0;
    }
}
