<?php

namespace App\Vendor\Widgets;

use App\Models\Central\Hotel;
use App\Vendor\HotelStats;
use App\Vendor\Resources\Hotels\HotelResource;
use Filament\Widgets\Widget;

/** Map of client hotels (OpenStreetMap). Coordinates come from each hotel's Profil Hotel. */
class HotelMap extends Widget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    protected string $view = 'vendor-console.widgets.hotel-map';

    protected function getViewData(): array
    {
        return ['points' => Hotel::query()->whereNotNull('latitude')->whereNotNull('longitude')->get()->map(function (Hotel $h) {
            $s = HotelStats::for($h);
            $state = $h->status !== 'active' ? 'suspended' : $h->serviceStatus();

            return ['lat' => (float) $h->latitude, 'lng' => (float) $h->longitude, 'name' => $h->name, 'city' => $h->city,
                'tv' => "{$s['online']}/{$s['tvs']} TV online", 'color' => ['active' => '#10B981', 'expiring' => '#F59E0B', 'released' => '#94A3B8', 'suspended' => '#F43F5E'][$state],
                'url' => HotelResource::getUrl('view', ['record' => $h])];
        })->values()->all(), 'missing' => Hotel::query()->whereNull('latitude')->count()];
    }
}
