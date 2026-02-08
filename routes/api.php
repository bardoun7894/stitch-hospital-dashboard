<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\MobileBookingController;
use App\Http\Controllers\Api\MobileQueueController;
use App\Http\Controllers\Api\MobileClinicsController;
use App\Http\Controllers\Api\MobileHospitalsController;
use App\Http\Controllers\Api\MobileProfileController;
use App\Http\Controllers\Api\MobileNotificationsController;
use App\Http\Controllers\Api\MobileTreatmentPlanController;
use App\Http\Controllers\Api\MobileMedicationController;

/*
|--------------------------------------------------------------------------
| Rate Limiters
|--------------------------------------------------------------------------
*/

// General API rate limiter: 60 requests per minute per user
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Stricter limiter for booking creation: 10 per minute
RateLimiter::for('bookings', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});

// Payment limiter: 5 per minute (prevent abuse)
RateLimiter::for('payments', function (Request $request) {
    return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
});

// Auth/login limiter: 5 attempts per minute
RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint (no auth required, minimal rate limit)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'version' => config('app.version', '1.0.0'),
    ]);
})->name('health');

// Stripe webhook (no auth required - verified by signature)
// No rate limit - Stripe handles this
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripeWebhook'])
    ->name('webhooks.stripe');

// Payment endpoints (requires Firebase auth + payment rate limit)
Route::middleware(['firebase.auth', 'throttle:payments'])->group(function () {
    Route::post('/payment/create-intent', [WebhookController::class, 'createPaymentIntent'])
        ->name('payment.create-intent');
});

/*
|--------------------------------------------------------------------------
| Mobile App API Routes
|--------------------------------------------------------------------------
|
| These routes are for the Flutter mobile application.
| All routes require Firebase authentication token and are rate limited.
|
*/

Route::prefix('mobile')->middleware(['firebase.auth', 'throttle:api'])->group(function () {

    // Hospitals
    Route::get('/hospitals', [MobileHospitalsController::class, 'index'])
        ->name('mobile.hospitals.index');
    Route::get('/hospitals/{hospitalId}', [MobileHospitalsController::class, 'show'])
        ->name('mobile.hospitals.show');
    Route::get('/hospitals/{hospitalId}/clinics', [MobileHospitalsController::class, 'getClinics'])
        ->name('mobile.hospitals.clinics');

    // Clinics & Doctors
    Route::get('/clinics', [MobileClinicsController::class, 'index'])
        ->name('mobile.clinics.index');
    Route::get('/clinics/{clinicId}', [MobileClinicsController::class, 'show'])
        ->name('mobile.clinics.show');
    Route::get('/clinics/{clinicId}/doctors', [MobileClinicsController::class, 'getDoctors'])
        ->name('mobile.clinics.doctors');
    Route::get('/clinics/{clinicId}/doctors/{doctorId}/slots', [MobileClinicsController::class, 'getAvailableSlots'])
        ->name('mobile.clinics.doctors.slots');

    // Specialties
    Route::get('/specialties/{specialty}', [MobileClinicsController::class, 'getBySpecialty'])
        ->name('mobile.specialties.show');

    // Search
    Route::get('/search', [MobileClinicsController::class, 'search'])
        ->name('mobile.search');

    // Bookings (read operations use standard rate limit)
    Route::get('/bookings', [MobileBookingController::class, 'index'])
        ->name('mobile.bookings.index');
    Route::get('/bookings/{bookingId}', [MobileBookingController::class, 'show'])
        ->name('mobile.bookings.show');
    
    // Booking write operations use stricter rate limit
    Route::middleware('throttle:bookings')->group(function () {
        Route::post('/bookings', [MobileBookingController::class, 'store'])
            ->name('mobile.bookings.store');
        Route::post('/bookings/{bookingId}/cancel', [MobileBookingController::class, 'cancel'])
            ->name('mobile.bookings.cancel');
        Route::post('/bookings/{bookingId}/arrive', [MobileBookingController::class, 'confirmArrival'])
            ->name('mobile.bookings.arrive');
        Route::post('/bookings/{bookingId}/reschedule', [MobileBookingController::class, 'reschedule'])
            ->name('mobile.bookings.reschedule');
    });

    // Queue
    Route::get('/queue/status', [MobileQueueController::class, 'getStatus'])
        ->name('mobile.queue.status');
    Route::get('/queue/my-position', [MobileQueueController::class, 'getMyPosition'])
        ->name('mobile.queue.position');

    // Treatment Plans
    Route::get('/treatment-plans/check', [MobileTreatmentPlanController::class, 'check'])
        ->name('mobile.treatment-plans.check');
    Route::get('/treatment-plans', [MobileTreatmentPlanController::class, 'index'])
        ->name('mobile.treatment-plans.index');

    // Medications / Prescriptions
    Route::get('/medications', [MobileMedicationController::class, 'index'])
        ->name('mobile.medications.index');
    Route::get('/medications/upcoming', [MobileMedicationController::class, 'getUpcomingDoses'])
        ->name('mobile.medications.upcoming');
    Route::get('/medications/{id}', [MobileMedicationController::class, 'show'])
        ->name('mobile.medications.show');
    Route::post('/medications/{id}/toggle-reminder', [MobileMedicationController::class, 'toggleReminder'])
        ->name('mobile.medications.toggle-reminder');
    Route::post('/medications/{id}/snooze', [MobileMedicationController::class, 'snoozeReminder'])
        ->name('mobile.medications.snooze');

    // Profile
    Route::post('/profile/bootstrap', [MobileProfileController::class, 'bootstrap'])
        ->name('mobile.profile.bootstrap');
    Route::get('/profile', [MobileProfileController::class, 'show'])
        ->name('mobile.profile.show');
    Route::put('/profile', [MobileProfileController::class, 'update'])
        ->name('mobile.profile.update');
    Route::get('/profile/family', [MobileProfileController::class, 'getFamilyMembers'])
        ->name('mobile.profile.family.index');
    Route::post('/profile/family', [MobileProfileController::class, 'addFamilyMember'])
        ->name('mobile.profile.family.store');
    Route::delete('/profile/family/{memberId}', [MobileProfileController::class, 'deleteFamilyMember'])
        ->name('mobile.profile.family.delete');

    // Notifications
    Route::get('/notifications', [MobileNotificationsController::class, 'index'])
        ->name('mobile.notifications.index');
    Route::post('/notifications/{notificationId}/read', [MobileNotificationsController::class, 'markRead'])
        ->name('mobile.notifications.read');
    Route::post('/notifications/read-all', [MobileNotificationsController::class, 'markAllRead'])
        ->name('mobile.notifications.readAll');
    Route::post('/notifications/fcm-token', [MobileNotificationsController::class, 'saveFcmToken'])
        ->name('mobile.notifications.fcmToken');
});
