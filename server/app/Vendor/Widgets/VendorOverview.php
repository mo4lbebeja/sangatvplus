<?php

namespace App\Vendor\Widgets;

use App\Models\Central\Hotel;
use App\Vendor\HotelStats;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VendorOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $hotels = Hotel::query()->orderBy('id')->get();
        $active = $hotels->where('status', 'active');
        $stats = $active->map(fn (Hotel $h) => HotelStats::for($h));
        $expiring = $hotels->filter(fn (Hotel $h) => $h->serviceStatus() === 'expiring');
        $staleBackup = $active->filter(fn (Hotel $h) => ($t = HotelStats::for($h)['backup']) === null || $t < now()->subHours(36)->getTimestamp());
        $errors = $stats->where('ok', false)->count();

        return [
            Stat::make('Hotel aktif', $active->count())->icon(Heroicon::OutlinedBuildingOffice2)
                ->description($hotels->count() - $active->count().' dinonaktifkan'.($errors ? " · {$errors} galat database" : ''))
                ->color($errors ? 'danger' : 'primary'),
            Stat::make('TV online', $stats->sum('online').' / '.$stats->sum('tvs'))->icon(Heroicon::OutlinedTv)
                ->description($stats->sum('rooms').' kamar di semua hotel'),
            Stat::make('Sewa segera habis', $expiring->count())->icon(Heroicon::OutlinedCalendarDays)
                ->description($expiring->pluck('name')->implode(', ') ?: '14 hari ke depan aman')
                ->color($expiring->isNotEmpty() ? 'warning' : 'success'),
            Stat::make('Backup terlambat', $staleBackup->count())->icon(Heroicon::OutlinedCircleStack)
                ->description($staleBackup->pluck('slug')->implode(', ') ?: 'semua hotel ter-backup < 36 jam')
                ->color($staleBackup->isNotEmpty() ? 'danger' : 'success'),
        ];
    }
}
