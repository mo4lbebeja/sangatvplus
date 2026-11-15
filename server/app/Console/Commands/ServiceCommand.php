<?php

namespace App\Console\Commands;

use App\Models\HotelProfile;
use App\Support\Audit;
use App\Tenancy\HotelManager;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Vendor-side control of the subscription period (not editable by hotel users in the CMS). */
class ServiceCommand extends Command
{
    protected $signature = 'sangatv:layanan
        {--sampai= : Tanggal akhir layanan (YYYY-MM-DD), atau "tanpa-batas"}
        {--lepas : Lepaskan semua TV sekarang (kembali ke launcher bawaan TV)}
        {--aktifkan : Batalkan pelepasan}
        {--hotel= : Kode Hotel (wajib bila multi-hotel aktif)}';

    protected $description = 'Atur masa berlaku layanan SangaTV Plus dan pelepasan TV';

    public function handle(): int
    {
        $tenancy = app(HotelManager::class);
        if ($tenancy->enabled()) {
            return $this->central($tenancy);
        }
        $hotel = HotelProfile::current();

        if ($until = $this->option('sampai')) {
            $hotel->service_expires_at = $until === 'tanpa-batas' ? null : CarbonImmutable::parse($until, $hotel->timezone ?: 'Asia/Jakarta')->endOfDay();
            Audit::log('service.period_set', $hotel, ['until' => $hotel->service_expires_at?->toIso8601String()]);
        }
        if ($this->option('lepas')) {
            $hotel->service_released_at = now();
            Audit::log('service.released', $hotel);
        }
        if ($this->option('aktifkan')) {
            $hotel->service_released_at = null;
            Audit::log('service.restored', $hotel);
        }
        $hotel->save();

        $this->info('Status: '.$hotel->serviceStatus()
            .' · berlaku sampai: '.($hotel->service_expires_at?->setTimezone($hotel->timezone ?: 'Asia/Jakarta')->format('d M Y') ?? 'tanpa batas')
            .($hotel->service_released_at ? ' · dilepas '.$hotel->service_released_at->diffForHumans() : ''));

        return self::SUCCESS;
    }

    /** Multi-hotel: the period and release are stored on the central hotel record. */
    private function central(HotelManager $tenancy): int
    {
        $hotel = $tenancy->findBySlug($this->option('hotel'));
        if (! $hotel) {
            $this->error('Pakai --hotel=<kode>. Daftar hotel: php artisan sangatv:hotel daftar');

            return self::FAILURE;
        }
        if ($until = $this->option('sampai')) {
            $hotel->service_expires_at = $until === 'tanpa-batas' ? null : CarbonImmutable::parse($until, 'Asia/Jakarta')->endOfDay();
        }
        if ($this->option('lepas')) {
            $hotel->service_released_at = now();
        }
        if ($this->option('aktifkan')) {
            $hotel->service_released_at = null;
        }
        $hotel->save();
        $this->info("{$hotel->slug} · status: ".$hotel->serviceStatus()
            .' · berlaku sampai: '.($hotel->service_expires_at?->setTimezone('Asia/Jakarta')->format('d M Y') ?? 'tanpa batas'));

        return self::SUCCESS;
    }
}
