<?php

use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\HotelExportController;
use App\Http\Controllers\PmsVendorDocController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\VendorReportController;
use App\Http\Middleware\InitializeHotel;
use App\Models\Central\HotelDocument;
use App\Models\Central\Invoice;
use App\Vendor\Impersonation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::redirect('/', '/admin');

// Public privacy policy (also linked from the Google OAuth consent screen of the backup app).
Route::view('/privasi', 'legal.privasi')->name('privacy');

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

    Route::get('/panduan/dokumen-vendor-pms.md', PmsVendorDocController::class)->name('vendor.pms-doc');

    Route::get('/laporan/tagihan.csv', VendorReportController::class)->name('vendor.report.invoices');

    Route::get('/tagihan/{invoice}/kwitansi', function (Invoice $invoice) {
        abort_unless(Auth::guard('vendor')->user()?->hasAccess('finance') && $invoice->status === 'paid', 404);

        return view('vendor-console.invoice-print', ['invoice' => $invoice->load('hotel'), 'document' => 'vendor-console.receipt-document']);
    })->name('vendor.invoice.receipt');

    Route::get('/tagihan/{invoice}/bukti', function (Invoice $invoice) {
        abort_unless(Auth::guard('vendor')->user()?->hasAccess('finance') && filled($invoice->payment_proof), 404);

        return Storage::disk('local')->response($invoice->payment_proof);
    })->name('vendor.invoice.proof');

    Route::get('/dokumen/{document}', function (HotelDocument $document) {
        abort_unless(Auth::guard('vendor')->check(), 403);

        return Storage::disk('local')->response($document->path);
    })->name('vendor.document.download');
});

// Support ticket attachments (Sijalucom staff or the hotel's own staff).
Route::get('/dukungan/lampiran/{message}/{i}', TicketAttachmentController::class)
    ->middleware('web')->whereNumber('i')->name('ticket.attachment');

// Client portal (secret link per hotel; Console → hotel → "Portal klien").
Route::middleware(['web', 'throttle:60,1'])->prefix('portal/{token}')->controller(ClientPortalController::class)->group(function () {
    Route::get('/', 'show')->name('portal.show');
    Route::get('/tagihan/{invoice}/bayar', 'pay')->name('portal.pay');
    Route::get('/tagihan/{invoice}/{doc}', 'invoice')->whereIn('doc', ['cetak', 'kwitansi'])->name('portal.invoice');
    Route::get('/dokumen/{document}', 'document')->name('portal.document');
    Route::get('/laporan/{month}', 'report')->name('portal.report');
});
