<?php

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\Central\FleetSnapshot;
use App\Models\Central\Hotel;
use App\Models\Device;
use App\Models\HotelProfile;
use App\Services\Notifier;
use App\Tenancy\HotelManager;
use App\Vendor\ConsoleNotify;
use Illuminate\Console\Command;
use Throwable;

/** Every 15 minutes (central): TVs online per hotel (uptime/SLA), map coordinates, alert when a whole hotel goes dark. */
class FleetSnapshotCommand extends Command
{
    protected $signature = 'sangatv:snapshot';

    protected $description = 'Catat jumlah TV online per hotel (dasar uptime & SLA)';

    public function handle(HotelManager $tenancy, Notifier $notifier): int
    {
        if (! $tenancy->enabled()) {
            return self::SUCCESS;
        }
        foreach (Hotel::query()->where('status', 'active')->get() as $hotel) {
            try {
                [$tvs, $online, $lat, $lng] = $tenancy->run($hotel, function () {
                    $q = Device::query()->where('status', DeviceStatus::Active);
                    $p = HotelProfile::current();

                    return [(clone $q)->count(), (clone $q)->where('last_seen_at', '>', Device::onlineThreshold())->count(), $p->latitude, $p->longitude];
                });
                $previous = FleetSnapshot::query()->where('hotel_id', $hotel->id)->latest('taken_at')->first();
                FleetSnapshot::query()->create(['hotel_id' => $hotel->id, 'taken_at' => now(), 'tvs' => $tvs, 'online' => $online]);
                if ($lat && $lng && ($hotel->latitude != $lat || $hotel->longitude != $lng)) {
                    $hotel->update(['latitude' => $lat, 'longitude' => $lng]);
                }
                if ($previous && $previous->online >= 2 && $online === 0 && $tvs > 0) {
                    ConsoleNotify::all('Semua TV offline', "{$hotel->name}: {$previous->online} TV tadinya online, sekarang 0. Cek internet/listrik hotel.", 'heroicon-o-signal-slash', 'danger', 'support');
                    rescue(fn () => $notifier->sendToVendor("📴 Semua TV offline · {$hotel->name}", "Tadinya {$previous->online} TV online, sekarang 0. Kemungkinan internet/listrik hotel padam."), report: false);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
        FleetSnapshot::query()->where('taken_at', '<', now()->subDays(400))->delete();

        return self::SUCCESS;
    }
}
