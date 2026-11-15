<?php

use App\Http\Controllers\HotelExportController;
use App\Http\Controllers\PmsVendorDocController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\VendorReportController;
use App\Http\Middleware\InitializeHotel;
use App\Models\Central\Invoice;
use App\Vendor\Impersonation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Public privacy policy (also linked from the Google OAuth consent screen of the backup app).
Route::view('/privasi', 'legal.privasi')->name('privacy');

// Vendor-facing PMS API document (Markdown download) — Admin & IT only.
Route::get('/admin/integrasi-pms/dokumen-vendor.md', PmsVendorDocController::class)
    ->middleware(['web', InitializeHotel::class, 'auth'])->name('pms.vendor-doc');

// Hotel data export (ZIP of CSV) — Admin only.
Route::get('/admin/hotel-profile/ekspor-data.zip', HotelExportController::class)
    ->middleware(['web', InitializeHotel::class, 'auth'])->name('hotel.export');

// Sijalucom support session ("Masuk sebagai" from the vendor panel) → back to the vendor panel.
Route::get('/admin/selesai-sijalucom', function () {
    Impersonation::stop();

    return redirect('/sijalucom/hotel');
})->middleware('web')->name('vendor.impersonation.stop');

// Sijalucom Console: printable invoice and finance CSV (vendor accounts with the finance role).
Route::middleware('web')->prefix('sijalucom')->group(function () {
    Route::get('/tagihan/{invoice}/cetak', function (Invoice $invoice) {
        abort_unless(Auth::guard('vendor')->user()?->hasAccess('finance'), 403);

        return view('vendor-console.invoice-print', ['invoice' => $invoice->load('hotel')]);
    })->name('vendor.invoice.print');

    Route::get('/laporan/tagihan.csv', VendorReportController::class)->name('vendor.report.invoices');
});

// Support ticket attachments (Sijalucom staff or the hotel's own staff).
Route::get('/dukungan/lampiran/{message}/{i}', TicketAttachmentController::class)
    ->middleware('web')->whereNumber('i')->name('ticket.attachment');
