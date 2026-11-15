<?php

namespace App\Console\Commands;

use App\Models\Central\Hotel;
use App\Models\Central\VendorActivity;
use App\Models\HotelProfile;
use App\Tenancy\HotelManager;
use App\Vendor\MonthlyReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** 1st of the month: last month's service report e-mailed to every hotel (report e-mails + billing e-mail). */
class MonthlyReportCommand extends Command
{
    protected $signature = 'sangatv:laporan-bulanan {--hotel= : kode hotel} {--bulan= : YYYY-MM (default bulan lalu)}';

    protected $description = 'Kirim laporan layanan bulanan ke hotel';

    public function handle(HotelManager $tenancy): int
    {
        if (! $tenancy->enabled()) {
            return self::SUCCESS;
        }
        $month = $this->option('bulan') ? now('Asia/Jakarta')->createFromFormat('Y-m-d', $this->option('bulan').'-01') : now('Asia/Jakarta')->subMonthNoOverflow();
        $hotels = Hotel::query()->where('status', 'active')->when($this->option('hotel'), fn ($q, $s) => $q->where('slug', $s))->get();
        foreach ($hotels as $hotel) {
            try {
                $to = collect(rescue(fn () => $tenancy->run($hotel, fn () => HotelProfile::current()->reportEmailList()), [], false))
                    ->push($hotel->billing_email)->push($hotel->contact_email)->filter()->unique()->values()->all();
                if ($to === []) {
                    continue;
                }
                $data = MonthlyReport::build($hotel, $month);
                Mail::html(view('vendor-console.monthly-report', $data)->render(), fn ($m) => $m->to($to)->subject("Laporan layanan SangaTV Plus {$data['label']} — {$hotel->name}"));
                VendorActivity::record('report.sent', "Laporan bulanan {$data['label']} dikirim ke ".implode(', ', $to), $hotel);
                $this->line("{$hotel->slug}: ".implode(', ', $to));
            } catch (Throwable $e) {
                report($e);
                $this->error("{$hotel->slug}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
