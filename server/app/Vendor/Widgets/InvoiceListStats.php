<?php

namespace App\Vendor\Widgets;

use App\Models\Central\Invoice;
use App\Vendor\Billing;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvoiceListStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $month = now(Billing::TZ)->startOfMonth()->utc();
        $paidMonth = Invoice::query()->where('status', 'paid')->where('paid_at', '>=', $month);
        $unpaid = Invoice::query()->where('status', 'unpaid');
        $overdue = Invoice::query()->overdue();

        return [
            Stat::make('Lunas bulan ini', Billing::rupiah((clone $paidMonth)->sum('total')))->icon(Heroicon::OutlinedCheckBadge)->description((clone $paidMonth)->count().' tagihan')->color('success'),
            Stat::make('Belum dibayar', Billing::rupiah((clone $unpaid)->sum('total')))->icon(Heroicon::OutlinedClock)->description((clone $unpaid)->count().' tagihan terbuka')->color('warning'),
            Stat::make('Lewat jatuh tempo', Billing::rupiah((clone $overdue)->sum('total')))->icon(Heroicon::OutlinedExclamationTriangle)
                ->description((clone $overdue)->count().' tagihan — tindak lanjuti')->color((clone $overdue)->exists() ? 'danger' : 'gray'),
            Stat::make('Total tagihan', Invoice::query()->where('status', '!=', 'void')->count())->icon(Heroicon::OutlinedDocumentDuplicate)
                ->description('sepanjang waktu')->color('primary'),
        ];
    }
}
