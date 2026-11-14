<?php

namespace App\Filament\Widgets;

use App\Enums\DeviceStatus;
use App\Enums\GuestStatus;
use App\Models\Channel;
use App\Models\Device;
use App\Models\Guest;
use App\Models\Room;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HotelOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -5;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $rooms = Room::query()->where('is_active', true)->count();
        $occupied = Guest::query()->where('status', GuestStatus::InHouse)->distinct('room_id')->count('room_id');
        $active = Device::query()->where('status', DeviceStatus::Active)->count();
        $online = Device::query()->where('status', DeviceStatus::Active)->where('last_seen_at', '>', now()->subMinutes(3))->count();
        $roomsWithTv = Device::query()->where('status', DeviceStatus::Active)->distinct('room_id')->count('room_id');
        $pct = $rooms > 0 ? round($occupied / $rooms * 100) : 0;

        // Check-ins per day, last 7 days (sparkline).
        $checkins = collect(range(6, 0))->map(fn ($d) => Guest::query()
            ->whereDate('checked_in_at', now()->subDays($d)->toDateString())->count())->all();

        return [
            Stat::make('Okupansi', "{$pct}%")
                ->icon(Heroicon::OutlinedHomeModern)
                ->description("{$occupied} dari {$rooms} kamar terisi")
                ->descriptionIcon(Heroicon::OutlinedKey)
                ->chart($checkins)
                ->color('primary'),
            Stat::make('TV online', "{$online} / {$active}")
                ->icon(Heroicon::OutlinedTv)
                ->description($active - $online > 0 ? ($active - $online).' TV tidak merespons' : 'Semua TV merespons')
                ->descriptionIcon($active - $online > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($active - $online > 0 ? 'warning' : 'success'),
            Stat::make('Belum ada TV', (string) max(0, $rooms - $roomsWithTv))
                ->icon(Heroicon::OutlinedQrCode)
                ->description('Kamar tanpa TV terdaftar')
                ->descriptionIcon(Heroicon::OutlinedInformationCircle)
                ->color('gray'),
            Stat::make('Channel aktif', (string) Channel::query()->where('is_active', true)->count())
                ->icon(Heroicon::OutlinedSignal)
                ->description(Channel::query()->where('is_active', true)->where('source_type', 'like', 'provider_%')->count().' dari penyedia')
                ->descriptionIcon(Heroicon::OutlinedGlobeAlt)
                ->color('info'),
        ];
    }
}
