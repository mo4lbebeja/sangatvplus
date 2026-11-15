<?php

namespace App\Vendor;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\DeviceStatus;
use App\Models\AppRelease;
use App\Models\Central\Hotel;
use App\Models\Central\Release;
use App\Models\Device;
use App\Tenancy\HotelManager;
use Illuminate\Support\Facades\Storage;

/**
 * OTA is managed only by Sijalucom: a central Release is mirrored into each targeted hotel's app_releases
 * (what the TVs of that hotel download); hotels just use it.
 */
class ReleaseSync
{
    /** @return list<Hotel> hotels to write into (single-hotel install: one pseudo-run in the current database) */
    private static function hotels(): array
    {
        return app(HotelManager::class)->enabled() ? Hotel::query()->where('status', 'active')->orderBy('id')->get()->all() : [];
    }

    private static function each(callable $fn): void
    {
        $tenancy = app(HotelManager::class);
        if (! $tenancy->enabled()) {
            $fn(null);

            return;
        }
        foreach (self::hotels() as $hotel) {
            $tenancy->run($hotel, fn () => $fn($hotel));
        }
    }

    /** Write one release into every hotel (inactive where it is not targeted). Returns the number of hotels using it. */
    public static function sync(Release $release): int
    {
        $count = 0;
        self::each(function (?Hotel $hotel) use ($release, &$count) {
            $on = $release->is_active && ($hotel === null || $release->targets($hotel));
            $existing = AppRelease::query()->where('package_name', $release->package_name)->where('version_code', $release->version_code)->first();
            if (! $on && ! $existing) {
                return;
            }
            AppRelease::query()->updateOrCreate(
                ['package_name' => $release->package_name, 'version_code' => $release->version_code],
                ['version_name' => $release->version_name, 'apk_path' => $release->apk_path, 'sha256' => $release->sha256, 'notes' => $release->notes, 'is_active' => $on],
            );
            $count += $on ? 1 : 0;
        });

        return $count;
    }

    /** New hotel: every active release that targets it. */
    public static function toHotel(Hotel $hotel): void
    {
        $releases = Release::query()->where('is_active', true)->orderBy('version_code')->get()->filter->targets($hotel);
        app(HotelManager::class)->run($hotel, function () use ($releases) {
            foreach ($releases as $r) {
                AppRelease::query()->updateOrCreate(['package_name' => $r->package_name, 'version_code' => $r->version_code],
                    ['version_name' => $r->version_name, 'apk_path' => $r->apk_path, 'sha256' => $r->sha256, 'notes' => $r->notes, 'is_active' => true]);
            }
        });
    }

    public static function remove(Release $release): void
    {
        self::each(fn () => AppRelease::query()->where('package_name', $release->package_name)->where('version_code', $release->version_code)->delete());
        if (Release::query()->where('apk_path', $release->apk_path)->whereKeyNot($release->getKey())->doesntExist()) {
            Storage::disk('local')->delete($release->apk_path);
        }
    }

    /** Ask the TVs (same package, not on hold) of the targeted hotels to update now. Returns [sent, held]. */
    public static function push(Release $release): array
    {
        $sent = 0;
        $held = 0;
        self::each(function (?Hotel $hotel) use ($release, &$sent, &$held) {
            if ($hotel && ! $release->targets($hotel)) {
                return;
            }
            $q = Device::query()->where('status', DeviceStatus::Active)
                ->where(fn ($q) => $q->where('app_package', $release->package_name)->when($release->package_name === AppRelease::DEFAULT_PACKAGE, fn ($q) => $q->orWhereNull('app_package')));
            $held += (clone $q)->where('hold_updates', true)->count();
            foreach ((clone $q)->where('hold_updates', false)->get() as $d) {
                $d->commands()->create(['type' => CommandType::CheckUpdate, 'status' => CommandStatus::Pending]);
                $sent++;
            }
        });

        return [$sent, $held];
    }

    /** TVs per hotel already on this version. */
    public static function installed(Release $release): int
    {
        $n = 0;
        self::each(function () use ($release, &$n) {
            $n += Device::query()->where('status', DeviceStatus::Active)->where('app_version_code', $release->version_code)->count();
        });

        return $n;
    }
}
