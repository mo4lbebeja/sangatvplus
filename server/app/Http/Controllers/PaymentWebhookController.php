<?php

namespace App\Http\Controllers;

use App\Models\Central\Invoice;
use App\Models\Central\VendorSetting;
use App\Vendor\Billing;
use App\Vendor\ConsoleNotify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Paid notifications from Xendit / Midtrans → invoice paid → rental extended. */
class PaymentWebhookController
{
    public function xendit(Request $request): JsonResponse
    {
        $token = (string) VendorSetting::secret('xendit_callback_token');
        abort_unless($token !== '' && hash_equals($token, (string) $request->header('x-callback-token')), 401);
        if (in_array(strtoupper((string) $request->input('status')), ['PAID', 'SETTLED'], true)) {
            $this->paid(Invoice::query()->where('number', (string) $request->input('external_id'))->first(), 'xendit', (string) $request->input('id'));
        }

        return response()->json(['ok' => true]);
    }

    public function midtrans(Request $request): JsonResponse
    {
        $key = (string) VendorSetting::secret('midtrans_server_key');
        $sig = hash('sha512', $request->input('order_id').$request->input('status_code').$request->input('gross_amount').$key);
        abort_unless($key !== '' && hash_equals($sig, (string) $request->input('signature_key')), 401);
        $status = (string) $request->input('transaction_status');
        $ok = $status === 'settlement' || ($status === 'capture' && $request->input('fraud_status', 'accept') === 'accept');
        if ($ok) {
            $this->paid(Invoice::query()->where('gateway_ref', (string) $request->input('order_id'))->first(), 'midtrans', (string) $request->input('transaction_id'));
        }

        return response()->json(['ok' => true]);
    }

    private function paid(?Invoice $invoice, string $method, string $ref): void
    {
        if (! $invoice || ! in_array($invoice->status, ['unpaid', 'draft'], true)) {
            return;
        }
        Billing::markPaid($invoice, now(Billing::TZ)->toDateString(), $method, $ref);
        ConsoleNotify::all('Pembayaran diterima', "{$invoice->hotel->name} membayar {$invoice->number} (".Billing::rupiah($invoice->total).') lewat '.ucfirst($method).'.', 'heroicon-o-banknotes', 'success');
    }
}
