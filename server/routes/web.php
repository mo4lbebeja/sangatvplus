<?php

use App\Http\Controllers\HotelExportController;
use App\Http\Controllers\PmsVendorDocController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Public privacy policy (also linked from the Google OAuth consent screen of the backup app).
Route::view('/privasi', 'legal.privasi')->name('privacy');

// Vendor-facing PMS API document (Markdown download) — Admin & IT only.
Route::get('/admin/integrasi-pms/dokumen-vendor.md', PmsVendorDocController::class)
    ->middleware(['web', 'auth'])->name('pms.vendor-doc');

// Hotel data export (ZIP of CSV) — Admin only.
Route::get('/admin/hotel-profile/ekspor-data.zip', HotelExportController::class)
    ->middleware(['web', 'auth'])->name('hotel.export');
