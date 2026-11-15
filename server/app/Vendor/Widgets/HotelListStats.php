<?php

namespace App\Vendor\Widgets;

use App\Models\Central\Hotel;
use App\Vendor\HotelStats;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Summary strip above the hotel list. */
class HotelListStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $hotels = Hotel::query()->with('plan')->get();
        $active = $hotels->where('status', 'active');
        $stats = $active->map(fn (Hotel $h) => HotelStats::for($h));
        $expiring = $hotels->filter(fn (Hotel $h) => $h->serviceStatus() === 'expiring' && $h->status === 'active')->count();
        $quota = $hotels->sum('tv_quota');

        return [
            Stat::make('Hotel aktif', $active->count())->icon(Heroicon::OutlinedBuildingOffice2)
                ->description(($hotels->count() - $active->count()).' ditangguhkan · '.$hotels->filter(fn ($h) => $h->service_released_at)->count().' mode lepas')->color('primary'),
            Stat::make('TV terpasang', $stats->sum('tvs').($quota ? " / {$quota}" : ''))->icon(Heroicon::OutlinedTv)
                ->description($quota ? round($stats->sum('tvs') / max(1, $quota) * 100).'% dari kuota kontrak' : 'kuota belum diatur')->color('info'),
            Stat::make('TV online sekarang', $stats->sum('online'))->icon(Heroicon::OutlinedSignal)
                ->description($stats->sum('tvs') ? round($stats->sum('online') / $stats->sum('tvs') * 100).'% menyala' : 'belum ada TV')->color('success'),
            Stat::make('Sewa segera habis', $expiring)->icon(Heroicon::OutlinedClock)
                ->description($expiring ? 'dalam 14 hari — siapkan perpanjangan' : 'tidak ada dalam 14 hari')->color($expiring ? 'warning' : 'gray'),
        ];
    }
}
