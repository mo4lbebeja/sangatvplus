<?php

namespace App\Http\Controllers;

use App\Models\Central\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Invoices as CSV (Excel-friendly) for bookkeeping, optionally filtered by issue year. */
class VendorReportController
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless(Auth::guard('vendor')->user()?->hasAccess('finance'), 403);
        $year = $request->integer('tahun') ?: null;
        $rows = Invoice::query()->with('hotel')->when($year, fn ($q) => $q->whereYear('issued_at', $year))->orderBy('issued_at')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No. tagihan', 'Kode hotel', 'Hotel', 'Terbit', 'Jatuh tempo', 'Periode mulai', 'Periode akhir', 'Bulan', 'Subtotal', 'Diskon', 'PPN', 'Total', 'Status', 'Dibayar', 'Metode', 'Referensi'], ';');
            foreach ($rows as $i) {
                fputcsv($out, [$i->number, $i->hotel->slug, $i->hotel->name, $i->issued_at->toDateString(), $i->due_at->toDateString(), $i->period_start->toDateString(),
                    $i->period_end->toDateString(), $i->months, $i->subtotal, $i->discount, $i->tax, $i->total, $i->displayStatus(),
                    $i->paid_at?->setTimezone('Asia/Jakarta')->toDateString(), $i->payment_method, $i->payment_ref], ';');
            }
            fclose($out);
        }, 'tagihan-sangatv'.($year ? "-{$year}" : '').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
