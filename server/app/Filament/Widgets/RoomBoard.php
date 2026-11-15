<?php

namespace App\Filament\Widgets;

use App\Enums\DeviceStatus;
use App\Models\Room;
use Filament\Widgets\Widget;

/** Live board of every room per floor: occupancy, guest and TV status at a glance. */
class RoomBoard extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.room-board';

    protected ?string $pollingInterval = '30s';

    protected function getViewData(): array
    {
        $rooms = Room::query()
            ->with(['currentGuest', 'devices' => fn ($q) => $q->where('status', DeviceStatus::Active)])
            ->orderBy('floor')->orderBy('number')
            ->get()
            ->map(function (Room $r) {
                $tv = $r->devices->first();

                return [
                    'number' => $r->number,
                    'floor' => $r->floor,
                    'active' => $r->is_active,
                    'guest' => $r->currentGuest?->displayName(),
                    'tv' => $tv === null ? 'none' : ($tv->powerState() === 'on' ? 'on' : 'off'),
                ];
            });

        return ['floors' => $rooms->groupBy('floor')];
    }
}
