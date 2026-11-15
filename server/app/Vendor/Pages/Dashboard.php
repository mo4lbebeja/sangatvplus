<?php

namespace App\Vendor\Pages;

use App\Vendor\Widgets\BusinessStats;
use App\Vendor\Widgets\ConsoleHero;
use App\Vendor\Widgets\ExpiringRentals;
use App\Vendor\Widgets\FleetHealth;
use App\Vendor\Widgets\HotelMap;
use App\Vendor\Widgets\PlanMixChart;
use App\Vendor\Widgets\QuickActions;
use App\Vendor\Widgets\RecentActivity;
use App\Vendor\Widgets\RevenueChart;
use App\Vendor\Widgets\UnpaidInvoices;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $title = 'Dasbor';

    public function getWidgets(): array
    {
        return [ConsoleHero::class, QuickActions::class, BusinessStats::class, RevenueChart::class, PlanMixChart::class, HotelMap::class, RecentActivity::class, ExpiringRentals::class, UnpaidInvoices::class, FleetHealth::class];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
    }
}
