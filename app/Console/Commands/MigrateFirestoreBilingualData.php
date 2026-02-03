<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirebaseService;

class MigrateFirestoreBilingualData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firestore:migrate-bilingual {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add English (name_en) fields to existing clinics and doctors in Firestore';

    protected $firebaseService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Running in DRY-RUN mode - no changes will be made');
        } else {
            $this->warn('⚠️  This will update Firestore documents. Press Ctrl+C to cancel...');
            sleep(2);
        }

        $firestore = $this->firebaseService->getFirestore();

        if (!$firestore) {
            $this->error('❌ Firestore client not initialized. Check your Firebase credentials.');
            return 1;
        }

        $this->info('📋 Starting bilingual migration...');
        $this->newLine();

        // Migrate Clinics
        $this->info('🏥 Migrating Clinics...');
        $this->migrateClinics($firestore, $dryRun);
        $this->newLine();

        // Migrate Doctors
        $this->info('👨‍⚕️ Migrating Doctors...');
        $this->migrateDoctors($firestore, $dryRun);
        $this->newLine();

        if ($dryRun) {
            $this->info('✅ Dry run completed. No changes were made.');
            $this->info('💡 Run without --dry-run to apply changes: php artisan firestore:migrate-bilingual');
        } else {
            $this->info('✅ Migration completed successfully!');
        }

        return 0;
    }

    /**
     * Migrate clinic documents to add name_en field
     */
    protected function migrateClinics($firestore, $dryRun)
    {
        try {
            $clinicsSnapshot = $firestore->collection('clinics')->documents();
            $count = 0;

            foreach ($clinicsSnapshot as $doc) {
                if ($doc->exists()) {
                    $data = $doc->data();
                    $clinicId = $doc->id();

                    // Check if name_en already exists
                    if (isset($data['name_en'])) {
                        $this->line("  ⏭️  Clinic '{$clinicId}' already has name_en: {$data['name_en']}");
                        continue;
                    }

                    // Get Arabic name
                    $arabicName = $data['name'] ?? '';

                    if (empty($arabicName)) {
                        $this->warn("  ⚠️  Clinic '{$clinicId}' has no name field - skipping");
                        continue;
                    }

                    // Translate or provide English equivalent
                    $englishName = $this->translateClinicName($arabicName);

                    $this->info("  📝 Clinic '{$clinicId}':");
                    $this->line("      Arabic (name): {$arabicName}");
                    $this->line("      English (name_en): {$englishName}");

                    if (!$dryRun) {
                        $doc->reference()->update([
                            ['path' => 'name_en', 'value' => $englishName]
                        ]);
                        $this->line("      ✅ Updated");
                    } else {
                        $this->line("      💡 Would be updated (dry-run)");
                    }

                    $count++;
                }
            }

            $this->info("  ✅ Processed {$count} clinics");
        } catch (\Exception $e) {
            $this->error("  ❌ Error migrating clinics: " . $e->getMessage());
        }
    }

    /**
     * Migrate doctor documents to add name_en field
     */
    protected function migrateDoctors($firestore, $dryRun)
    {
        try {
            $clinicsSnapshot = $firestore->collection('clinics')->documents();
            $totalCount = 0;

            foreach ($clinicsSnapshot as $clinicDoc) {
                if ($clinicDoc->exists()) {
                    $clinicId = $clinicDoc->id();
                    $this->line("  🏥 Processing doctors in clinic: {$clinicId}");

                    $doctorsSnapshot = $clinicDoc->reference()
                        ->collection('doctors')
                        ->documents();

                    $count = 0;
                    foreach ($doctorsSnapshot as $doc) {
                        if ($doc->exists()) {
                            $data = $doc->data();
                            $doctorId = $doc->id();

                            // Check if name_en already exists
                            if (isset($data['name_en'])) {
                                $this->line("    ⏭️  Doctor '{$doctorId}' already has name_en: {$data['name_en']}");
                                continue;
                            }

                            // Get Arabic name
                            $arabicName = $data['name'] ?? '';

                            if (empty($arabicName)) {
                                $this->warn("    ⚠️  Doctor '{$doctorId}' has no name field - skipping");
                                continue;
                            }

                            // Translate or provide English equivalent
                            $englishName = $this->translateDoctorName($arabicName);

                            $this->info("    📝 Doctor '{$doctorId}':");
                            $this->line("        Arabic (name): {$arabicName}");
                            $this->line("        English (name_en): {$englishName}");

                            if (!$dryRun) {
                                $doc->reference()->update([
                                    ['path' => 'name_en', 'value' => $englishName]
                                ]);
                                $this->line("        ✅ Updated");
                            } else {
                                $this->line("        💡 Would be updated (dry-run)");
                            }

                            $count++;
                            $totalCount++;
                        }
                    }

                    if ($count > 0) {
                        $this->line("    ✅ Processed {$count} doctors in this clinic");
                    }
                }
            }

            $this->info("  ✅ Processed {$totalCount} total doctors");
        } catch (\Exception $e) {
            $this->error("  ❌ Error migrating doctors: " . $e->getMessage());
        }
    }

    /**
     * Translate clinic name from Arabic to English
     * In production, you would use a translation API or manual mapping
     */
    protected function translateClinicName($arabicName)
    {
        // Common clinic name translations
        $translations = [
            'الطب العام' => 'General Medicine',
            'طب الأطفال' => 'Pediatrics',
            'القلب' => 'Cardiology',
            'طب الجلدية' => 'Dermatology',
            'العظام' => 'Orthopedics',
            'الأسنان' => 'Dentistry',
            'العيون' => 'Ophthalmology',
            'الأنف والأذن والحنجرة' => 'ENT (Ear, Nose & Throat)',
            'النساء والتوليد' => 'Obstetrics & Gynecology',
            'الجراحة العامة' => 'General Surgery',
        ];

        // Return translation if found, otherwise return original with "(Translation Needed)" suffix
        return $translations[$arabicName] ?? "{$arabicName} (Translation Needed)";
    }

    /**
     * Translate doctor name from Arabic to English
     * In production, you would use a translation API or manual mapping
     */
    protected function translateDoctorName($arabicName)
    {
        // For doctor names, we typically transliterate rather than translate
        // In a real scenario, you'd have a database of doctor names or use a transliteration API

        // Common title translations
        $arabicName = str_replace('الدكتور ', 'Dr. ', $arabicName);
        $arabicName = str_replace('الدكتورة ', 'Dr. ', $arabicName);
        $arabicName = str_replace('د. ', 'Dr. ', $arabicName);

        // If no English equivalent found, keep the Arabic name with a note
        if (mb_detect_encoding($arabicName, 'ASCII', true) === false) {
            return "{$arabicName} (Transliteration Needed)";
        }

        return $arabicName;
    }
}
