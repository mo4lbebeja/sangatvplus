<?php

namespace App\Vendor;

use App\Models\Central\Invoice;
use App\Models\Central\VendorSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Online payment links (Xendit Invoice or Midtrans Snap — chosen in Console → Pengaturan → Integrasi).
 * Paid webhooks mark the invoice paid, which extends the rental.
 */
class Payments
{
    public static function gateway(): ?string
    {
        $g = (string) VendorSetting::read('payment_gateway');

        return match ($g) {
            'xendit' => VendorSetting::secret('xendit_secret_key') ? 'xendit' : null,
            'midtrans' => VendorSetting::secret('midtrans_server_key') ? 'midtrans' : null,
            default => null,
        };
    }

    public static function enabled(): bool
    {
        return self::gateway() !== null;
    }

    /** Create (or return) the payment link of an unpaid invoice. */
    public static function link(Invoice $invoice, bool $fresh = false): ?string
    {
        if ($invoice->status !== 'unpaid' || ! ($gateway = self::gateway())) {
            return null;
        }
        if ($invoice->payment_url && $invoice->gateway === $gateway && ! $fresh) {
            return $invoice->payment_url;
        }
        $invoice->loadMissing('hotel');
        [$url, $ref] = $gateway === 'xendit' ? self::xendit($invoice) : self::midtrans($invoice);
        $invoice->update(['gateway' => $gateway, 'gateway_ref' => $ref, 'payment_url' => $url]);

        return $url;
    }

    private static function xendit(Invoice $invoice): array
    {
        $res = Http::withBasicAuth((string) VendorSetting::secret('xendit_secret_key'), '')->acceptJson()->timeout(20)
            ->post('https://api.xendit.co/v2/invoices', array_filter([
                'external_id' => $invoice->number,
                'amount' => $invoice->total,
                'currency' => 'IDR',
                'description' => "Sewa SangaTV Plus {$invoice->hotel->name} · {$invoice->number}",
                'payer_email' => $invoice->hotel->billing_email ?: $invoice->hotel->contact_email,
                'invoice_duration' => 60 * 60 * 24 * 30,
                'success_redirect_url' => $invoice->hotel->portalUrl(),
                'customer' => ['given_names' => $invoice->hotel->billing_name ?: $invoice->hotel->name],
            ]));
        if (! $res->successful()) {
            throw new RuntimeException('Xendit: '.($res->json('message') ?? $res->status()));
        }

        return [$res->json('invoice_url'), $invoice->number];
    }

    private static function midtrans(Invoice $invoice): array
    {
        $order = $invoice->number.'-'.now()->format('His'); // Midtrans order ids are single-use
        $base = VendorSetting::read('midtrans_production') ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
        $res = Http::withBasicAuth((string) VendorSetting::secret('midtrans_server_key'), '')->acceptJson()->timeout(20)
            ->post($base.'/snap/v1/transactions', [
                'transaction_details' => ['order_id' => $order, 'gross_amount' => $invoice->total],
                'customer_details' => array_filter(['first_name' => $invoice->hotel->billing_name ?: $invoice->hotel->name,
                    'email' => $invoice->hotel->billing_email ?: $invoice->hotel->contact_email, 'phone' => $invoice->hotel->contact_phone]),
                'item_details' => [['id' => $invoice->number, 'price' => $invoice->total, 'quantity' => 1, 'name' => mb_substr("Sewa SangaTV Plus {$invoice->number}", 0, 50)]],
                'callbacks' => ['finish' => $invoice->hotel->portalUrl()],
            ]);
        if (! $res->successful()) {
            throw new RuntimeException('Midtrans: '.(implode(', ', (array) $res->json('error_messages')) ?: $res->status()));
        }

        return [$res->json('redirect_url'), $order];
    }
}
