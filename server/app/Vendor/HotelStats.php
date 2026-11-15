<?php

namespace App\Vendor;

use App\Enums\DeviceStatus;
use App\Enums\GuestStatus;
use App\Models\Central\Hotel;
use App\Models\ChannelView;
use App\Models\Device;
use App\Models\Guest;
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
        Cache::forget("vendor:details:{$hotel->id}");
    }

    /**
     * Hotel detail page: every TV and 30-day viewing (from the hotel's own database).
     *
     * @return array{ok: bool, devices: array<int, array<string, mixed>>, top_channels: array<int, array{name: string, hours: float}>, watch_hours: float, guests_in_house: int}
     */
    public static function details(Hotel $hotel): array
    {
        return Cache::remember("vendor:details:{$hotel->id}", 60, function () use ($hotel) {
            try {
                return app(HotelManager::class)->run($hotel, function () {
                    $since = now()->subDays(30);

                    return [
                        'ok' => true,
                        'devices' => Device::query()->with('room')->where('status', DeviceStatus::Active)->orderBy('room_id')->get()
                            ->map(fn (Device $d) => [
                                'room' => $d->room?->number ?? '–', 'model' => $d->model, 'version' => $d->app_version,
                                'state' => $d->powerState(), 'last_seen' => $d->last_seen_at?->toIso8601String(),
                                'owner' => (bool) $d->device_owner, 'held' => (bool) $d->hold_updates,
                            ])->all(),
                        'top_channels' => ChannelView::query()->where('started_at', '>=', $since)
                            ->selectRaw('channel_id, sum(seconds) as s')->groupBy('channel_id')->orderByDesc('s')->limit(5)->with('channel:id,name')->get()
                            ->map(fn ($v) => ['name' => $v->channel?->name ?? '?', 'hours' => round($v->s / 3600, 1)])->all(),
                        'watch_hours' => round(ChannelView::query()->where('started_at', '>=', $since)->sum('seconds') / 3600, 1),
                        'guests_in_house' => Guest::query()->where('status', GuestStatus::InHouse)->count(),
                    ];
                });
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage(), 'devices' => [], 'top_channels' => [], 'watch_hours' => 0, 'guests_in_house' => 0];
            }
        });
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
