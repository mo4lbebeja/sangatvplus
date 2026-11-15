<?php

namespace App\Mail;

use App\Models\HotelProfile;
use App\Services\ChannelStats;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ChannelUptimeReport extends Mailable
{
    use Queueable;

    public function __construct(public int $days = 7) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Laporan channel TV '.$this->days.' hari · '.HotelProfile::current()->name);
    }

    public function content(): Content
    {
        $hotel = HotelProfile::current();
        $stats = new ChannelStats($this->days, $hotel->timezone ?: 'Asia/Jakarta');

        return new Content(view: 'mail.channel-uptime', with: [
            'hotel' => $hotel,
            'days' => $this->days,
            'uptime' => $stats->uptime(),
            'top' => $stats->topChannels(10),
            'totals' => $stats->totals(),
        ]);
    }
}
