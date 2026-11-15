<?php

namespace App\Http\Controllers;

use App\Models\Central\Hotel;
use App\Models\Central\HotelDocument;
use App\Models\Central\Invoice;
use App\Models\Central\Ticket;
use App\Vendor\Collections;
use App\Vendor\HotelStats;
use App\Vendor\MonthlyReport;
use App\Vendor\Sla;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Client portal (secret link per hotel): invoices & receipts, online payment, documents, monthly reports, SLA. */
class ClientPortalController
{
    private function hotel(string $token): Hotel
    {
        abort_unless(strlen($token) >= 40, 404);

        return Hotel::query()->where('portal_token', $token)->firstOrFail();
    }

    public function show(string $token): View
    {
        $hotel = $this->hotel($token)->load('plan');
        $months = collect(range(0, 5))->map(fn ($i) => now('Asia/Jakarta')->startOfMonth()->subMonthsNoOverflow($i));

        return view('portal.show', [
            'hotel' => $hotel,
            'token' => $token,
            'stats' => HotelStats::for($hotel),
            'sla' => Sla::forMonth($hotel, now()),
            'invoices' => $hotel->invoices()->where('status', '!=', 'void')->orderByDesc('issued_at')->limit(24)->get(),
            'documents' => $hotel->documents()->latest()->get(),
            'tickets' => Ticket::query()->where('hotel_id', $hotel->id)->latest('last_reply_at')->limit(10)->get(),
            'months' => $months,
        ]);
    }

    public function invoice(string $token, Invoice $invoice, string $doc): View
    {
        $hotel = $this->hotel($token);
        abort_unless($invoice->hotel_id === $hotel->id && $invoice->status !== 'void', 404);
        abort_if($doc === 'kwitansi' && $invoice->status !== 'paid', 404);

        return view('vendor-console.invoice-print', ['invoice' => $invoice->load('hotel'),
            'document' => $doc === 'kwitansi' ? 'vendor-console.receipt-document' : 'vendor-console.invoice-document']);
    }

    public function pay(string $token, Invoice $invoice): RedirectResponse
    {
        $hotel = $this->hotel($token);
        abort_unless($invoice->hotel_id === $hotel->id && $invoice->status === 'unpaid', 404);
        $url = Collections::payLink($invoice->load('hotel'));
        abort_if($url === $hotel->portalUrl(), 404, 'Pembayaran online belum tersedia. Silakan transfer ke rekening di tagihan.');

        return redirect()->away($url);
    }

    public function document(string $token, HotelDocument $document): StreamedResponse
    {
        abort_unless($document->hotel_id === $this->hotel($token)->id, 404);

        return Storage::disk('local')->response($document->path);
    }

    public function report(string $token, string $month): View
    {
        $hotel = $this->hotel($token);
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month), 404);

        return view('portal.report', MonthlyReport::build($hotel, now('Asia/Jakarta')->createFromFormat('Y-m-d', $month.'-01')));
    }
}
