<?php

namespace App\Vendor\Resources\Hotels\Pages;

use App\Models\Central\VendorActivity;
use App\Vendor\Resources\Hotels\HotelActions;
use App\Vendor\Resources\Hotels\HotelResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewHotel extends ViewRecord
{
    protected static string $resource = HotelResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return "Kode {$this->record->slug} · database {$this->record->database}";
    }

    protected function getHeaderActions(): array
    {
        return [
            HotelActions::extend(),
            HotelActions::impersonate(),
            EditAction::make()->modalWidth('4xl')->after(fn () => VendorActivity::record('hotel.updated', 'Data hotel diubah', $this->record)),
            ActionGroup::make([HotelActions::release(), HotelActions::restore(), HotelActions::suspend(), HotelActions::activate(), HotelActions::refresh()])
                ->label('Lainnya')->button()->color('gray'),
        ];
    }

    public function getContentTabLabel(): ?string
    {
        return 'Ringkasan';
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
