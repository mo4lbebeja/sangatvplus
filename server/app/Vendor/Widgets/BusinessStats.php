<?php

namespace App\Vendor\Widgets;

use App\Models\Central\Hotel;
use App\Models\Central\Invoice;
use App\Vendor\Billing;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BusinessStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $revenue = Billing::revenueByMonth(6);
        $values = array_values($revenue);
        $thisMonth = end($values) ?: 0;
        $lastMonth = $values[count($values) - 2] ?? 0;
        $growth = $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100) : null;

        $open = Invoice::query()->where('status', 'unpaid');
        $overdue = Invoice::query()->overdue();
        $hotels = Hotel::query()->with('plan')->get();
        $mrr = Billing::mrr($hotels);
        $expiring = $hotels->filter(fn (Hotel $h) => ($d = $h->daysLeft()) !== null && $d >= 0 && $d <= 30 && $h->service_released_at === null)->count();

        return [
            Stat::make('Pendapatan bulan ini', Billing::rupiah($thisMonth))->icon(Heroicon::OutlinedBanknotes)
                ->description($growth === null ? 'tagihan lunas bulan berjalan' : ($growth >= 0 ? "naik {$growth}% dari bulan lalu" : 'turun '.abs($growth).'% dari bulan lalu'))
                ->descriptionIcon($growth !== null && $growth < 0 ? Heroicon::ArrowTrendingDown : Heroicon::ArrowTrendingUp)
                ->color($growth !== null && $growth < 0 ? 'danger' : 'success')->chart($values),
            Stat::make('MRR · ARR', Billing::rupiah($mrr))->icon(Heroicon::OutlinedArrowPath)
                ->description('setahun ≈ '.Billing::rupiah($mrr * 12))->color('primary'),
            Stat::make('Piutang', Billing::rupiah((clone $open)->sum('total')))->icon(Heroicon::OutlinedDocumentText)
                ->description((clone $open)->count().' tagihan terbuka · '.(clone $overdue)->count().' jatuh tempo')
                ->color((clone $overdue)->exists() ? 'danger' : 'gray'),
            Stat::make('Sewa habis ≤ 30 hari', $expiring)->icon(Heroicon::OutlinedCalendarDays)
                ->description($expiring ? 'segera tagih / perpanjang' : 'semua aman')->color($expiring ? 'warning' : 'success'),
        ];
    }
}
