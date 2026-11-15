<?php

namespace App\Console\Commands;

use App\Mail\ChannelUptimeReport;
use App\Models\HotelProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class UptimeReportCommand extends Command
{
    protected $signature = 'sangatv:uptime-report {--days=7}';

    protected $description = 'Kirim laporan uptime & tontonan channel ke email di Profil Hotel';

    public function handle(): int
    {
        $to = HotelProfile::current()->reportEmailList();
        if ($to === []) {
            $this->warn('Belum ada email penerima (Profil Hotel → Channel TV).');

            return self::SUCCESS;
        }
        Mail::to($to)->send(new ChannelUptimeReport((int) $this->option('days')));
        $this->info('Laporan dikirim ke '.implode(', ', $to));

        return self::SUCCESS;
    }
}
