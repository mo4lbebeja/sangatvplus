<?php

namespace App\Mail;

use App\Models\Central\Invoice;
use App\Models\Central\VendorSetting;
use App\Vendor\Billing;
use App\Vendor\Collections;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Invoice e-mailed to the hotel (HTML body = the same document as the printout). */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?string $reminderNote = null;

    public function __construct(public Invoice $invoice) {}

    /** Payment reminder variant ("jatuh tempo 3 hari lagi"). */
    public function reminder(string $when): static
    {
        $this->reminderNote = $when;

        return $this;
    }

    public function envelope(): Envelope
    {
        $this->invoice->loadMissing('hotel');

        return new Envelope(subject: ($this->reminderNote ? "Pengingat: tagihan {$this->invoice->number} {$this->reminderNote} — " : "Tagihan {$this->invoice->number} — ")."SangaTV Plus {$this->invoice->hotel->name} (".Billing::rupiah($this->invoice->total).')',
            replyTo: array_filter([VendorSetting::read('company_email')]));
    }

    public function content(): Content
    {
        $link = Collections::payLink($this->invoice);
        $button = $this->invoice->status === 'unpaid'
            ? '<div style="text-align:center;margin:0 0 16px"><a href="'.e($link).'" style="display:inline-block;background:#4F46E5;color:#fff;padding:12px 22px;border-radius:10px;font-weight:700;text-decoration:none;font-family:sans-serif">Bayar / lihat tagihan</a></div>'
            : '';

        return new Content(htmlString: '<div style="background:#E2E8F0;padding:24px 8px">'.$button
            .view('vendor-console.invoice-document', ['invoice' => $this->invoice])->render().'</div>');
    }
}
