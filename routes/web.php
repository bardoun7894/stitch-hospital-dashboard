<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocalizationController;
use App\Http\Controllers\Auth\LoginController;

// Localization Routes (public)
Route::get('/lang/{locale}', [LocalizationController::class, 'setLocale'])->name('set-locale');
Route::get('/lang/en', [LocalizationController::class, 'switchToEnglish'])->name('switch-to-english');
Route::get('/lang/ar', [LocalizationController::class, 'switchToArabic'])->name('switch-to-arabic');

// Auth Routes (public)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

use App\Http\Controllers\ClinicsController;
Route::get('/clinics', [ClinicsController::class, 'index'])->name('clinics.index');

use App\Http\Controllers\DoctorsController;
use App\Http\Controllers\DoctorScheduleController;
Route::get('/doctors', [DoctorsController::class, 'index'])->name('doctors.index');
Route::get('/doctors/{id}/schedule', [DoctorScheduleController::class, 'show'])->name('doctors.schedule');
Route::post('/doctors/{id}/schedule', [DoctorScheduleController::class, 'update'])->name('doctors.schedule.update');
Route::post('/doctors/{id}/blockout', [DoctorScheduleController::class, 'addBlockout'])->name('doctors.blockout.add');
Route::delete('/doctors/{id}/blockout/{blockId}', [DoctorScheduleController::class, 'removeBlockout'])->name('doctors.blockout.remove');

use App\Http\Controllers\PatientsController;
Route::get('/patients', [PatientsController::class, 'index'])->name('patients.index');
Route::get('/patients/create', [PatientsController::class, 'create'])->name('patients.create');
Route::post('/patients', [PatientsController::class, 'store'])->name('patients.store');
Route::get('/patients/{id}', [PatientsController::class, 'show'])->name('patients.show');

use App\Http\Controllers\SettingsController;
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings/clinic', [SettingsController::class, 'updateClinic'])
    ->name('settings.updateClinic')
    ->middleware(['role:clinic_admin,super_admin']);

use App\Http\Controllers\BookingsController;
Route::get('/bookings', [BookingsController::class, 'index'])->name('bookings.index');
Route::get('/bookings/create', [BookingsController::class, 'create'])->name('bookings.create');
Route::post('/bookings', [BookingsController::class, 'store'])->name('bookings.store');
Route::get('/api/slots', [BookingsController::class, 'getSlots'])->name('api.slots');

use App\Http\Controllers\TvViewController;
Route::get('/tv-view', [TvViewController::class, 'index'])->name('tv.index');

// Queue management routes (reception/clinic_admin only)
use App\Http\Controllers\QueueController;
Route::prefix('queue')->name('queue.')->middleware(['role:reception,clinic_admin'])->group(function () {
    Route::get('/', [QueueController::class, 'index'])->name('index');
    Route::post('/next', [QueueController::class, 'next'])->name('next');
    Route::post('/skip', [QueueController::class, 'skip'])->name('skip');
    Route::post('/toggle-pause', [QueueController::class, 'togglePause'])->name('toggle-pause');
    Route::get('/stats', [QueueController::class, 'stats'])->name('stats');
    Route::post('/recall', [QueueController::class, 'recall'])->name('recall');
    Route::post('/reinsert', [QueueController::class, 'reinsert'])->name('reinsert');
});

// Booking action routes (reception/clinic_admin only)
Route::prefix('bookings')->name('bookings.')->middleware(['role:reception,clinic_admin'])->group(function () {
    Route::post('/{id}/accept', [BookingsController::class, 'accept'])->name('accept');
    Route::post('/{id}/reject', [BookingsController::class, 'reject'])->name('reject');
    Route::post('/{id}/confirm-payment', [BookingsController::class, 'confirmPayment'])->name('confirm-payment');
    Route::post('/{id}/mark-arrived', [BookingsController::class, 'markArrived'])->name('mark-arrived');
    
    Route::get('/{id}/reschedule', [BookingsController::class, 'reschedule'])->name('reschedule');
    Route::post('/{id}/reschedule', [BookingsController::class, 'processReschedule'])->name('process-reschedule');
    Route::post('/{id}/cancel', [BookingsController::class, 'cancel'])->name('cancel');
});

// Reports routes
use App\Http\Controllers\ReportsController;
Route::prefix('reports')->name('reports.')->middleware(['role:clinic_admin,super_admin'])->group(function () {
    Route::get('/', [ReportsController::class, 'index'])->name('index');
    Route::get('/daily', [ReportsController::class, 'dailyStats'])->name('daily');
    Route::get('/doctor-load', [ReportsController::class, 'doctorLoad'])->name('doctor-load');
});

// Hospital Manager routes
use App\Http\Controllers\HospitalController;
Route::get('/hospital', [HospitalController::class, 'index'])
    ->name('hospital.index')
    ->middleware(['role:hospital_manager']);


