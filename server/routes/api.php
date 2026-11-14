<?php

use App\Http\Controllers\Api\V1\AppUpdateController;
use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\FacilityController;
use App\Http\Controllers\Api\V1\HeartbeatController;
use App\Http\Controllers\Api\V1\ProvisionController;
use App\Http\Controllers\Api\V1\TechnicianController;
use App\Http\Middleware\EnsureActiveDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('devices/provision', ProvisionController::class)
        ->middleware('throttle:provision')
        ->name('devices.provision');

    Route::middleware(['auth:sanctum', EnsureActiveDevice::class, 'throttle:device-api'])->group(function () {
        Route::get('bootstrap', BootstrapController::class)->name('bootstrap');
        Route::get('channels', ChannelController::class)->name('channels');
        Route::get('facilities', FacilityController::class)->name('facilities');
        Route::post('heartbeat', [HeartbeatController::class, 'beat'])->name('heartbeat');
        Route::post('commands/{command}/ack', [HeartbeatController::class, 'ack'])->name('commands.ack');
        Route::post('technician/verify', [TechnicianController::class, 'verify'])->name('technician.verify');
        Route::post('technician/offline-attempts', [TechnicianController::class, 'reportOfflineAttempts'])->name('technician.offline');
        Route::post('technician/events', [TechnicianController::class, 'event'])->name('technician.event');
        Route::get('app/update', [AppUpdateController::class, 'check'])->name('app.update');
        Route::get('app/releases/{release}/download', [AppUpdateController::class, 'download'])->name('app.download');
    });
});
