<?php

namespace App\Filament\Resources\Guests\Pages;

use App\Filament\Resources\Guests\GuestResource;
use App\Models\Room;
use App\Services\GuestService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGuests extends ManageRecords
{
    protected static string $resource = GuestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Check-in tamu')
                ->modalHeading('Check-in tamu')
                ->using(fn (array $data, GuestService $service) => $service->checkIn(
                    Room::findOrFail($data['room_id']),
                    collect($data)->except('room_id')->all(),
                )),
        ];
    }
}
