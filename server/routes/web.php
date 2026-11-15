<?php

use App\Http\Controllers\HotelExportController;
use App\Http\Controllers\PmsVendorDocController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Vendor-facing PMS API document (Markdown download) — Admin & IT only.
Route::get('/admin/integrasi-pms/dokumen-vendor.md', PmsVendorDocController::class)
    ->middleware(['web', 'auth'])->name('pms.vendor-doc');

// Hotel data export (ZIP of CSV) — Admin only.
Route::get('/admin/hotel-profile/ekspor-data.zip', HotelExportController::class)
    ->middleware(['web', 'auth'])->name('hotel.export');
