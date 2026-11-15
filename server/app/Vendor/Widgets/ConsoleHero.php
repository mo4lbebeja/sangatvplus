<?php

namespace App\Vendor\Widgets;

use App\Models\Central\Hotel;
use App\Vendor\Billing;
use App\Vendor\HotelStats;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class ConsoleHero extends Widget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'vendor-console.widgets.hero';

    protected function getViewData(): array
    {
        $hotels = Hotel::query()->with('plan')->get();
        $stats = $hotels->where('status', 'active')->map(fn (Hotel $h) => HotelStats::for($h));
        $hour = (int) now(Billing::TZ)->format('H');

        return [
            'greeting' => $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 19 ? 'Selamat sore' : 'Selamat malam')),
            'name' => Filament::auth()->user()?->name,
            'date' => now(Billing::TZ)->translatedFormat('l, d F Y'),
            'mrr' => Billing::rupiah(Billing::mrr($hotels)),
            'hotels' => $hotels->filter->isBillable()->count(),
            'tvs' => $stats->sum('tvs'),
            'online' => $stats->sum('online'),
        ];
    }
}
