<?php

use App\Http\Controllers\Api\Pms\PmsController;
use App\Http\Controllers\Api\V1\AppUpdateController;
use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\ChannelReportController;
use App\Http\Controllers\Api\V1\EpgController;
use App\Http\Controllers\Api\V1\FacilityController;
use App\Http\Controllers\Api\V1\HeartbeatController;
use App\Http\Controllers\Api\V1\ProvisionController;
use App\Http\Controllers\Api\V1\TechnicianController;
use App\Http\Controllers\Api\V1\ViewerController;
use App\Http\Middleware\AuthenticatePms;
use App\Http\Middleware\EnsureActiveDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('devices/provision', ProvisionController::class)
        ->middleware('throttle:provision')
        ->name('devices.provision');

    Route::middleware(['auth:sanctum', EnsureActiveDevice::class, 'throttle:device-api'])->group(function () {
        Route::get('bootstrap', BootstrapController::class)->name('bootstrap');
        Route::get('channels', ChannelController::class)->name('channels');
        Route::post('channels/{channel}/report', [ChannelReportController::class, 'store'])->name('channels.report');
        Route::get('epg', EpgController::class)->name('epg');
        Route::put('favorites', [ViewerController::class, 'favorites'])->name('favorites');
        Route::post('views', [ViewerController::class, 'views'])->name('views');
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

// Inbound PMS/BMS integration (hotel system pushes rooms & stays). Docs: CMS → Integrasi PMS.
Route::prefix('pms/v1')->name('api.pms.')->middleware([AuthenticatePms::class, 'throttle:pms'])->group(function () {
    Route::get('ping', [PmsController::class, 'ping'])->name('ping');
    Route::get('rooms', [PmsController::class, 'rooms'])->name('rooms');
    Route::put('rooms/{number}', [PmsController::class, 'upsertRoom'])->name('rooms.upsert');
    Route::post('checkins', [PmsController::class, 'checkIn'])->name('checkin');
    Route::post('checkouts', [PmsController::class, 'checkOut'])->name('checkout');
    Route::get('stays', [PmsController::class, 'inHouse'])->name('stays');
    Route::patch('stays/{reservation}', [PmsController::class, 'updateStay'])->name('stays.update');
    Route::post('stays/{reservation}/move', [PmsController::class, 'move'])->name('stays.move');
    Route::post('sync', [PmsController::class, 'sync'])->name('sync');
});
