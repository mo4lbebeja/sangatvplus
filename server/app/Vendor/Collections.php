<?php

namespace App\Vendor;

use App\Mail\InvoiceMail;
use App\Models\Central\Hotel;
use App\Models\Central\Invoice;
use App\Models\Central\VendorActivity;
use App\Models\Central\VendorSetting;
use App\Services\Notifier;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Collections: staged payment reminders to hotels, auto-suspend for long-overdue invoices, auto-reactivation. */
class Collections
{
    /** @return list<int> reminder offsets in days: positive = before the due date, negative = after */
    public static function offsets(): array
    {
        return collect(explode(',', (string) VendorSetting::read('reminder_days')))->map(fn ($d) => (int) trim($d))->unique()->values()->all();
    }

    /** Link a hotel can pay with: the gateway link when online payments are on, else the client portal. */
    public static function payLink(Invoice $invoice): string
    {
        return rescue(fn () => Payments::link($invoice), null, true) ?? $invoice->hotel->portalUrl();
    }

    /** Send due reminders for every unpaid invoice. @return list<string> lines for the Sijalucom summary */
    public static function sendReminders(): array
    {
        $today = now(Billing::TZ)->startOfDay();
        $lines = [];
        foreach (Invoice::query()->with('hotel')->where('status', 'unpaid')->get() as $invoice) {
            $days = (int) $today->diffInDays($invoice->due_at->copy()->startOfDay(), false); // >0 before due
            if (! in_array($days, self::offsets(), true)) {
                continue;
            }
            $key = 'd'.$days;
            $sent = (array) ($invoice->reminders ?? []);
            if (in_array($key, $sent, true)) {
                continue;
            }
            $hotel = $invoice->hotel;
            $link = self::payLink($invoice);
            $when = $days > 0 ? "jatuh tempo {$days} hari lagi" : ($days === 0 ? 'jatuh tempo HARI INI' : 'sudah lewat '.abs($days).' hari dari jatuh tempo');
            $text = "Yth. {$hotel->name},\n\nTagihan SangaTV Plus {$invoice->number} sebesar ".Billing::rupiah($invoice->total)
                ." {$when} (".$invoice->due_at->translatedFormat('d M Y').").\n\nBayar / lihat tagihan: {$link}\n\nAbaikan pesan ini bila sudah membayar. Terima kasih.\n— ".VendorSetting::read('company_name');

            $channels = [];
            if ($to = $hotel->billing_email ?: $hotel->contact_email) {
                try {
                    Mail::to($to)->send((new InvoiceMail($invoice))->reminder($when));
                    $channels[] = 'email';
                } catch (Throwable $e) {
                    report($e);
                }
            }
            if (WhatsApp::send($hotel->contact_phone, $text)) {
                $channels[] = 'WhatsApp';
            }
            $invoice->update(['reminders' => [...$sent, $key]]);
            if ($channels) {
                VendorActivity::record('invoice.reminder', "Pengingat {$invoice->number} ({$when}) via ".implode(' & ', $channels), $hotel);
                $lines[] = "• {$hotel->name}: {$invoice->number} {$when} — ".implode(' & ', $channels);
            }
        }

        return $lines;
    }

    /** Suspend hotels with an invoice overdue more than N days (setting; 0 = off). @return list<string> */
    public static function autoSuspend(): array
    {
        $after = (int) VendorSetting::read('suspend_after_days');
        if ($after < 1) {
            return [];
        }
        $cutoff = now(Billing::TZ)->startOfDay()->subDays($after)->toDateString();
        $lines = [];
        $hotels = Hotel::query()->where('status', 'active')
            ->whereHas('invoices', fn ($q) => $q->where('status', 'unpaid')->whereDate('due_at', '<=', $cutoff))->get();
        foreach ($hotels as $hotel) {
            $hotel->update(['status' => 'suspended', 'suspended_reason' => 'overdue']);
            VendorActivity::record('hotel.suspended', "Ditangguhkan otomatis: tagihan lewat jatuh tempo > {$after} hari", $hotel);
            $lines[] = "• {$hotel->name} ditangguhkan otomatis (tagihan > {$after} hari lewat tempo)";
            WhatsApp::send($hotel->contact_phone, "Yth. {$hotel->name}, layanan SangaTV Plus ditangguhkan karena tagihan belum dibayar lebih dari {$after} hari setelah jatuh tempo. Layanan aktif kembali otomatis setelah pembayaran: {$hotel->portalUrl()}");
            ConsoleNotify::all('Hotel ditangguhkan otomatis', "{$hotel->name}: tagihan lewat jatuh tempo > {$after} hari.", 'heroicon-o-no-symbol', 'danger', 'finance');
        }

        return $lines;
    }

    /** After a payment: lift an automatic suspension when nothing is long overdue any more. */
    public static function reactivateIfSettled(Hotel $hotel): void
    {
        if ($hotel->status !== 'suspended' || $hotel->suspended_reason !== 'overdue') {
            return;
        }
        $after = max(1, (int) VendorSetting::read('suspend_after_days'));
        $cutoff = now(Billing::TZ)->startOfDay()->subDays($after)->toDateString();
        if ($hotel->invoices()->where('status', 'unpaid')->whereDate('due_at', '<=', $cutoff)->exists()) {
            return;
        }
        $hotel->update(['status' => 'active', 'suspended_reason' => null]);
        VendorActivity::record('hotel.activated', 'Aktif kembali otomatis setelah pembayaran', $hotel);
        rescue(fn () => app(Notifier::class)->sendToVendor('✅ Hotel aktif kembali', "{$hotel->name} aktif kembali setelah pembayaran."), report: false);
    }
}
