<?php

namespace App\Console\Commands;

use App\Models\Central\Hotel;
use App\Services\Notifier;
use App\Tenancy\HotelManager;
use Illuminate\Console\Command;

/**
 * Daily (central): tell Sijalucom which hotels' rental ends in 30/14/7/3/1 days or ended today, so it can be
 * invoiced or extended before the TVs switch to "Mode lepas".
 */
class RentalReminderCommand extends Command
{
    public const DAYS = [30, 14, 7, 3, 1, 0];

    protected $signature = 'sangatv:pengingat-sewa';

    protected $description = 'Kirim pengingat masa sewa hotel ke Sijalucom';

    public function handle(HotelManager $tenancy, Notifier $notifier): int
    {
        if (! $tenancy->enabled()) {
            return self::SUCCESS;
        }

        $due = Hotel::query()->where('status', 'active')->whereNull('service_released_at')->whereNotNull('service_expires_at')->get()
            ->map(fn (Hotel $h) => [$h, (int) now('Asia/Jakarta')->startOfDay()->diffInDays($h->service_expires_at->setTimezone('Asia/Jakarta')->startOfDay(), false)])
            ->filter(fn (array $x) => in_array($x[1], self::DAYS, true))
            ->sortBy(fn (array $x) => $x[1]);

        if ($due->isEmpty()) {
            $this->info('Tidak ada sewa yang perlu diingatkan hari ini.');

            return self::SUCCESS;
        }

        $lines = $due->map(function (array $x) {
            [$h, $days] = $x;
            $when = $days === 0 ? 'BERAKHIR HARI INI — TV masuk Mode lepas' : "berakhir {$days} hari lagi";
            $contact = collect([$h->contact_name, $h->contact_email])->filter()->implode(', ');

            return "• {$h->name} ({$h->slug}): {$when}, ".$h->service_expires_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y')
                .($contact ? " — kontak: {$contact}" : '');
        })->implode("\n");

        $notifier->sendToVendor('🗓️ Masa sewa hotel', $lines."\n\nPerpanjang di panel vendor: ".url('/sijalucom/hotel'));
        $this->line($lines);

        return self::SUCCESS;
    }
}
