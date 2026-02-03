<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Firebase connection test command
Artisan::command('firebase:test', function () {
    $firebaseService = app(\App\Services\FirebaseService::class);
    $firestore = $firebaseService->getFirestore();

    if ($firestore === null) {
        $this->error('❌ Firebase connection FAILED - Firestore client is null');
        return 1;
    }

    try {
        // Try to list clinics collection
        $clinics = $firestore->collection('clinics')->documents();
        $count = 0;
        foreach ($clinics as $doc) {
            if ($doc->exists()) {
                $count++;
                $data = $doc->data();
                $clinicId = $doc->id();
                $clinicName = isset($data['name']) ? $data['name'] : 'Unknown';
                $this->info("  - Clinic: {$clinicId} - {$clinicName}");
            }
        }

        $this->info('✅ Firebase connection SUCCESS!');
        $this->info('   Project: ' . env('FIREBASE_PROJECT_ID'));
        $this->info("   Clinics found: {$count}");

        return 0;
    } catch (\Exception $e) {
        $this->error('❌ Firebase connection FAILED');
        $this->error('   Error: ' . $e->getMessage());
        return 1;
    }
})->purpose('Test Firebase/Firestore connection');
