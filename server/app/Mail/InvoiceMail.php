<?php

namespace App\Mail;

use App\Models\Central\Invoice;
use App\Models\Central\VendorSetting;
use App\Vendor\Billing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Invoice e-mailed to the hotel (HTML body = the same document as the printout). */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        $this->invoice->loadMissing('hotel');

        return new Envelope(subject: "Tagihan {$this->invoice->number} — SangaTV Plus {$this->invoice->hotel->name} (".Billing::rupiah($this->invoice->total).')',
            replyTo: array_filter([VendorSetting::read('company_email')]));
    }

    public function content(): Content
    {
        return new Content(htmlString: '<div style="background:#E2E8F0;padding:24px 8px">'
            .view('vendor-console.invoice-document', ['invoice' => $this->invoice])->render().'</div>');
    }
}
