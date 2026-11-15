<?php

namespace App\Console\Commands;

use App\Mail\InvoiceMail;
use App\Models\Central\Hotel;
use App\Models\Central\Invoice;
use App\Models\Central\VendorSetting;
use App\Services\Notifier;
use App\Tenancy\HotelManager;
use App\Vendor\Billing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Daily (central): renewal invoices N days before a rental ends (hotels with "tagihan otomatis"), e-mailed to the
 * hotel's billing address; Sijalucom gets a summary incl. overdue invoices. The rental grows when the invoice is paid.
 */
class AutoInvoiceCommand extends Command
{
    protected $signature = 'sangatv:tagihan {--tanpa-email : jangan kirim tagihan ke email hotel}';

    protected $description = 'Buat tagihan perpanjangan otomatis & laporkan tagihan lewat jatuh tempo';

    public function handle(HotelManager $tenancy, Notifier $notifier): int
    {
        if (! $tenancy->enabled()) {
            return self::SUCCESS;
        }
        $daysBefore = (int) VendorSetting::read('invoice_days_before');
        $created = [];

        $hotels = Hotel::query()->with('plan')->where('status', 'active')->where('auto_invoice', true)
            ->whereNull('service_released_at')->whereNotNull('service_expires_at')->get()
            ->filter(fn (Hotel $h) => $h->monthlyPrice() > 0 && ($d = $h->daysLeft()) !== null && $d >= 0 && $d <= $daysBefore);

        foreach ($hotels as $hotel) {
            [$start] = Billing::nextPeriod($hotel, $hotel->billing_months);
            $exists = Invoice::query()->where('hotel_id', $hotel->id)->whereIn('status', ['unpaid', 'draft', 'paid'])
                ->whereDate('period_start', $start->toDateString())->exists();
            if ($exists) {
                continue;
            }
            try {
                $invoice = Billing::createInvoice($hotel, $hotel->billing_months, notes: 'Tagihan perpanjangan otomatis.');
                $to = $hotel->billing_email ?: $hotel->contact_email;
                $mailed = false;
                if ($to && ! $this->option('tanpa-email')) {
                    Mail::to($to)->send(new InvoiceMail($invoice));
                    $mailed = true;
                }
                $created[] = "• {$hotel->name}: {$invoice->number} ".Billing::rupiah($invoice->total).', tempo '.$invoice->due_at->translatedFormat('d M').($mailed ? " (dikirim ke {$to})" : '');
            } catch (Throwable $e) {
                report($e);
                $created[] = "• {$hotel->name}: GAGAL — {$e->getMessage()}";
            }
        }

        $overdue = Invoice::query()->with('hotel')->overdue()->orderBy('due_at')->get()
            ->map(fn (Invoice $i) => "• {$i->hotel->name}: {$i->number} ".Billing::rupiah($i->total).' (tempo '.$i->due_at->translatedFormat('d M').')');

        if ($created !== [] || $overdue->isNotEmpty()) {
            $body = ($created ? "Tagihan baru:\n".implode("\n", $created)."\n\n" : '')
                .($overdue->isNotEmpty() ? "Lewat jatuh tempo:\n".$overdue->implode("\n")."\n\n" : '')
                .'Kelola: '.url('/sijalucom/tagihan');
            $notifier->sendToVendor('🧾 Tagihan SangaTV Plus', $body);
            $this->line($body);
        } else {
            $this->info('Tidak ada tagihan baru atau tagihan lewat tempo.');
        }

        return self::SUCCESS;
    }
}
