<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\MobileBookingController;
use App\Http\Controllers\Api\MobileQueueController;
use App\Http\Controllers\Api\MobileClinicsController;
use App\Http\Controllers\Api\MobileHospitalsController;
use App\Http\Controllers\Api\MobileProfileController;
use App\Http\Controllers\Api\MobileNotificationsController;

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

// Health check endpoint (no auth required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'version' => config('app.version', '1.0.0'),
    ]);
})->name('health');

// Stripe webhook (no auth required - verified by signature)
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripeWebhook'])
    ->name('webhooks.stripe');

// Payment endpoints (requires Firebase auth for mobile app)
Route::middleware('firebase.auth')->group(function () {
    Route::post('/payment/create-intent', [WebhookController::class, 'createPaymentIntent'])
        ->name('payment.create-intent');
});

/*
|--------------------------------------------------------------------------
| Mobile App API Routes
|--------------------------------------------------------------------------
|
| These routes are for the Flutter mobile application.
| All routes require Firebase authentication token.
|
*/

Route::prefix('mobile')->middleware('firebase.auth')->group(function () {

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

    // Bookings
    Route::get('/bookings', [MobileBookingController::class, 'index'])
        ->name('mobile.bookings.index');
    Route::post('/bookings', [MobileBookingController::class, 'store'])
        ->name('mobile.bookings.store');
    Route::get('/bookings/{bookingId}', [MobileBookingController::class, 'show'])
        ->name('mobile.bookings.show');
    Route::post('/bookings/{bookingId}/cancel', [MobileBookingController::class, 'cancel'])
        ->name('mobile.bookings.cancel');
    Route::post('/bookings/{bookingId}/arrive', [MobileBookingController::class, 'confirmArrival'])
        ->name('mobile.bookings.arrive');
    Route::post('/bookings/{bookingId}/reschedule', [MobileBookingController::class, 'reschedule'])
        ->name('mobile.bookings.reschedule');

    // Queue
    Route::get('/queue/status', [MobileQueueController::class, 'getStatus'])
        ->name('mobile.queue.status');
    Route::get('/queue/my-position', [MobileQueueController::class, 'getMyPosition'])
        ->name('mobile.queue.position');

    // Profile
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
