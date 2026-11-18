<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Enums\DeviceStatus;
use App\Filament\Resources\Devices\DeviceResource;
use App\Models\Device;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class ManageDevices extends ManageRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // TVs check on their own every 6 hours; this skips the wait for every outdated TV.
            Action::make('updateAll')
                ->label(fn () => 'Update semua TV ('.$this->outdated()->count().')')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('warning')
                ->visible(fn () => DeviceResource::canViewAny() && $this->outdated()->isNotEmpty())
                ->requiresConfirmation()
                ->modalHeading(fn () => 'Update '.$this->outdated()->count().' TV sekarang?')
                ->modalDescription(DeviceResource::updateWarning().' TV yang "Tahan update" dilewati.')
                ->modalSubmitActionLabel('Update sekarang')
                ->action(fn () => DeviceResource::sendUpdates($this->outdated())),
        ];
    }

    /** @return Collection<int, Device> Active TVs, not on hold, with a newer version waiting. */
    private function outdated(): Collection
    {
        return Device::query()->where('status', DeviceStatus::Active)->where('hold_updates', false)->get()
            ->filter(fn (Device $d) => $d->availableRelease() && ! $d->updateQueued())->values();
    }
}
