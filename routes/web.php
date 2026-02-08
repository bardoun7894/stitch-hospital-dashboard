<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocalizationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClinicsController;
use App\Http\Controllers\DoctorsController;
use App\Http\Controllers\DoctorScheduleController;
use App\Http\Controllers\PatientsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\BookingsController;
use App\Http\Controllers\TvViewController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\TreatmentPlanController;
use App\Http\Controllers\MedicationController;

// ─── Public Routes ───────────────────────────────────────────────────

// Localization (public)
Route::get('/lang/{locale}', [LocalizationController::class, 'setLocale'])->name('set-locale');
Route::get('/lang/en', [LocalizationController::class, 'switchToEnglish'])->name('switch-to-english');
Route::get('/lang/ar', [LocalizationController::class, 'switchToArabic'])->name('switch-to-arabic');

// Auth (public)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// TV View (public — displayed on waiting room screens)
Route::get('/tv-view', [TvViewController::class, 'index'])->name('tv.index');

// ─── Authenticated Routes ────────────────────────────────────────────

Route::middleware(['auth.session'])->group(function () {

    // Dashboard (all authenticated staff)
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Bookings — view (all staff can view)
    Route::get('/bookings', [BookingsController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [BookingsController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingsController::class, 'store'])->name('bookings.store');
    Route::get('/api/slots', [BookingsController::class, 'getSlots'])->name('api.slots');
    Route::get('/api/bookings/pending', [BookingsController::class, 'pendingBookings'])->name('api.bookings.pending');

    // Clinics — view (clinic_admin, hospital_manager, super_admin)
    Route::get('/clinics', [ClinicsController::class, 'index'])->name('clinics.index');

    // Doctors — view (all staff)
    Route::get('/doctors', [DoctorsController::class, 'index'])->name('doctors.index');
    Route::get('/doctors/{id}/schedule', [DoctorScheduleController::class, 'show'])->name('doctors.schedule');

    // Patients — view (reception, doctor, clinic_admin, hospital_manager, super_admin)
    Route::get('/patients', [PatientsController::class, 'index'])->name('patients.index');
    Route::get('/patients/create', [PatientsController::class, 'create'])->name('patients.create');
    Route::post('/patients', [PatientsController::class, 'store'])->name('patients.store');
    Route::get('/patients/{id}/edit', [PatientsController::class, 'edit'])->name('patients.edit');
    Route::put('/patients/{id}', [PatientsController::class, 'update'])->name('patients.update');
    Route::get('/patients/{id}', [PatientsController::class, 'show'])->name('patients.show');

    // Treatment Plans — doctor, clinic_admin, reception
    Route::middleware(['role:doctor,clinic_admin,reception'])->group(function () {
        Route::get('/treatment-plans', [TreatmentPlanController::class, 'index'])->name('treatment-plans.index');
        Route::post('/treatment-plans', [TreatmentPlanController::class, 'store'])->name('treatment-plans.store');
        Route::post('/treatment-plans/{id}/complete', [TreatmentPlanController::class, 'complete'])->name('treatment-plans.complete');
        Route::delete('/treatment-plans/{id}', [TreatmentPlanController::class, 'destroy'])->name('treatment-plans.destroy');
        Route::get('/treatment-plans/search-patients', [TreatmentPlanController::class, 'searchPatients'])->name('treatment-plans.search-patients');
    });

    // Medications / Prescriptions — doctor, clinic_admin, reception
    Route::middleware(['role:doctor,clinic_admin,reception'])->group(function () {
        Route::get('/medications', [MedicationController::class, 'index'])->name('medications.index');
        Route::post('/medications', [MedicationController::class, 'store'])->name('medications.store');
        Route::get('/medications/search-patients', [MedicationController::class, 'searchPatients'])->name('medications.search-patients');
        Route::get('/medications/{id}', [MedicationController::class, 'show'])->name('medications.show');
        Route::delete('/medications/{id}', [MedicationController::class, 'destroy'])->name('medications.destroy');
    });

    // ─── Clinic Admin+ Routes ────────────────────────────────────────

    Route::middleware(['role:clinic_admin,hospital_manager'])->group(function () {
        // Clinic CRUD
        Route::get('/clinics/create', [ClinicsController::class, 'create'])->name('clinics.create');
        Route::post('/clinics', [ClinicsController::class, 'store'])->name('clinics.store');
        Route::get('/clinics/{id}/edit', [ClinicsController::class, 'edit'])->name('clinics.edit');
        Route::put('/clinics/{id}', [ClinicsController::class, 'update'])->name('clinics.update');
        Route::delete('/clinics/{id}', [ClinicsController::class, 'destroy'])->name('clinics.destroy');

        // Doctor CRUD
        Route::get('/doctors/create', [DoctorsController::class, 'create'])->name('doctors.create');
        Route::post('/doctors', [DoctorsController::class, 'store'])->name('doctors.store');
        Route::get('/doctors/{id}/edit', [DoctorsController::class, 'edit'])->name('doctors.edit');
        Route::put('/doctors/{id}', [DoctorsController::class, 'update'])->name('doctors.update');
        Route::delete('/doctors/{id}', [DoctorsController::class, 'destroy'])->name('doctors.destroy');

        // Doctor schedule management
        Route::post('/doctors/{id}/schedule', [DoctorScheduleController::class, 'update'])->name('doctors.schedule.update');
        Route::post('/doctors/{id}/blockout', [DoctorScheduleController::class, 'addBlockout'])->name('doctors.blockout.add');
        Route::delete('/doctors/{id}/blockout/{blockId}', [DoctorScheduleController::class, 'removeBlockout'])->name('doctors.blockout.remove');

        // Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/clinic', [SettingsController::class, 'updateClinic'])->name('settings.updateClinic');
    });

    // ─── Reception / Clinic Admin Routes (queue + booking actions) ───

    Route::middleware(['role:reception,clinic_admin'])->group(function () {
        // Queue management
        Route::prefix('queue')->name('queue.')->group(function () {
            Route::get('/', [QueueController::class, 'index'])->name('index');
            Route::post('/next', [QueueController::class, 'next'])->name('next');
            Route::post('/skip', [QueueController::class, 'skip'])->name('skip');
            Route::post('/toggle-pause', [QueueController::class, 'togglePause'])->name('toggle-pause');
            Route::get('/stats', [QueueController::class, 'stats'])->name('stats');
            Route::post('/recall', [QueueController::class, 'recall'])->name('recall');
            Route::post('/reinsert', [QueueController::class, 'reinsert'])->name('reinsert');
        });

        // Booking actions
        Route::prefix('bookings')->name('bookings.')->group(function () {
            Route::post('/{id}/accept', [BookingsController::class, 'accept'])->name('accept');
            Route::post('/{id}/reject', [BookingsController::class, 'reject'])->name('reject');
            Route::post('/{id}/confirm-payment', [BookingsController::class, 'confirmPayment'])->name('confirm-payment');
            Route::post('/{id}/mark-arrived', [BookingsController::class, 'markArrived'])->name('mark-arrived');
            Route::get('/{id}/reschedule', [BookingsController::class, 'reschedule'])->name('reschedule');
            Route::post('/{id}/reschedule', [BookingsController::class, 'processReschedule'])->name('process-reschedule');
            Route::post('/{id}/cancel', [BookingsController::class, 'cancel'])->name('cancel');
        });
    });

    // ─── Reports (clinic_admin, hospital_manager, super_admin) ───────

    Route::prefix('reports')->name('reports.')->middleware(['role:clinic_admin,hospital_manager'])->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('/daily', [ReportsController::class, 'dailyStats'])->name('daily');
        Route::get('/doctor-load', [ReportsController::class, 'doctorLoad'])->name('doctor-load');
    });

    // ─── Hospital Routes (hospital_manager views, super_admin full CRUD) ───

    Route::get('/hospital', [HospitalController::class, 'index'])
        ->name('hospital.index')
        ->middleware(['role:hospital_manager']);

    Route::middleware(['role:hospital_manager'])->group(function () {
        Route::get('/hospital/create', [HospitalController::class, 'create'])->name('hospital.create');
        Route::post('/hospital', [HospitalController::class, 'store'])->name('hospital.store');
        Route::get('/hospital/{id}/edit', [HospitalController::class, 'edit'])->name('hospital.edit');
        Route::put('/hospital/{id}', [HospitalController::class, 'update'])->name('hospital.update');
        Route::delete('/hospital/{id}', [HospitalController::class, 'destroy'])->name('hospital.destroy');
    });

    // ─── User Management (clinic_admin, hospital_manager, super_admin)

    Route::prefix('users')->name('users.')->middleware(['role:clinic_admin,hospital_manager'])->group(function () {
        Route::get('/', [UsersController::class, 'index'])->name('index');
        Route::get('/create', [UsersController::class, 'create'])->name('create');
        Route::post('/', [UsersController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [UsersController::class, 'edit'])->name('edit');
        Route::put('/{id}', [UsersController::class, 'update'])->name('update');
        Route::delete('/{id}', [UsersController::class, 'destroy'])->name('destroy');
    });
});
