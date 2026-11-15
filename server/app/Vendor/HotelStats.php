<?php

namespace App\Vendor;

use App\Enums\DeviceStatus;
use App\Models\Central\Hotel;
use App\Models\Device;
use App\Models\Room;
use App\Tenancy\HotelManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Throwable;

/** Vendor panel: a hotel's numbers read from its own database (cached 1 minute). */
class HotelStats
{
    /** @return array{ok: bool, error?: string, tvs: int, online: int, rooms: int, versions: array<string, int>, last_seen: ?string, backup: ?int} */
    public static function for(Hotel $hotel): array
    {
        return Cache::remember("vendor:stats:{$hotel->id}", 60, function () use ($hotel) {
            $backup = self::lastBackup($hotel);
            try {
                return app(HotelManager::class)->run($hotel, function () use ($backup) {
                    $active = Device::query()->where('status', DeviceStatus::Active);

                    return [
                        'ok' => true,
                        'tvs' => (clone $active)->count(),
                        'online' => (clone $active)->where('last_seen_at', '>', Device::onlineThreshold())->count(),
                        'rooms' => Room::query()->where('is_active', true)->count(),
                        'versions' => (clone $active)->whereNotNull('app_version')->selectRaw('app_version, count(*) as n')
                            ->groupBy('app_version')->orderByDesc('app_version')->pluck('n', 'app_version')->all(),
                        'last_seen' => (clone $active)->max('last_seen_at'),
                        'backup' => $backup,
                    ];
                });
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage(), 'tvs' => 0, 'online' => 0, 'rooms' => 0, 'versions' => [], 'last_seen' => null, 'backup' => $backup];
            }
        });
    }

    public static function forget(Hotel $hotel): void
    {
        Cache::forget("vendor:stats:{$hotel->id}");
    }

    /** Unix time of the newest encrypted database backup of this hotel (storage/app/private/backups/<kode>). */
    public static function lastBackup(Hotel $hotel): ?int
    {
        $dir = storage_path('app/private/backups/'.$hotel->slug);
        if (! File::isDirectory($dir)) {
            return null;
        }
        $times = array_map('filemtime', File::glob($dir.'/db-*.enc') ?: []);

        return $times ? max($times) : null;
    }
}
