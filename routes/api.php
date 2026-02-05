<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccidentController;
use App\Http\Controllers\Api\AccidentMediaController;
use App\Http\Controllers\Api\ResponderController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\EmergencyContactController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SosController;

Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working',
        'timestamp' => now()->toIso8601String()
    ]);
});

Route::prefix('v1')->group(function () {

    // Public routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:api')->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::get('/auth/profile', [AuthController::class, 'profile']);

        // Accidents
        Route::apiResource('accidents', AccidentController::class);
        Route::post('/accidents/{id}/responders', [AccidentController::class, 'assignResponders']);
        
        // Accident Media
        Route::get('/accidents/{accidentId}/media', [AccidentMediaController::class, 'index']);
        Route::post('/accidents/{accidentId}/media', [AccidentMediaController::class, 'store']);
        Route::delete('/accidents/{accidentId}/media/{mediaId}', [AccidentMediaController::class, 'destroy']);
        Route::get('/accidents/{accidentId}/media/{mediaId}/url', [AccidentMediaController::class, 'getPublicUrl']);
        // Accident Media - add this route
        Route::get('/accidents/{accidentId}/media/{mediaId}/signed-url', [AccidentMediaController::class, 'getSignedUrl']);

        // Responders
        Route::apiResource('responders', ResponderController::class)->except(['store']);
        Route::post('/responders', [ResponderController::class, 'store'])->middleware('role:admin,superadmin');
        Route::post('/responders/update-location', [ResponderController::class, 'updateLocation']);
        Route::patch('/responders/{id}/availability', [ResponderController::class, 'updateAvailability']);

        // Vehicles
        Route::apiResource('vehicles', VehicleController::class);
        Route::post('/vehicles/{id}/update-location', [VehicleController::class, 'updateLocation']);
        Route::post('/vehicles/{id}/update-status', [VehicleController::class, 'updateStatus']);
        Route::get('/vehicles/nearby', [VehicleController::class, 'nearby']);
        Route::get('/vehicles/statistics', [VehicleController::class, 'statistics']);

        // Emergency Contacts
        Route::apiResource('emergency-contacts', EmergencyContactController::class);
        Route::post('/emergency-contacts/{id}/set-primary', [EmergencyContactController::class, 'setPrimary']);
        Route::get('/emergency-contacts/primary', [EmergencyContactController::class, 'getPrimary']);
        Route::post('/emergency-contacts/bulk-update', [EmergencyContactController::class, 'bulkUpdate']);

        // Audit Logs (Admin only)
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('role:admin,superadmin');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Manual SOS Alerts
        Route::post('/sos', [SosController::class, 'store']);
        Route::get('/sos', [SosController::class, 'index']);
    });

});