<?php

namespace App\Filament\Resources\AppWhitelist\Pages;

use App\Filament\Resources\AppWhitelist\AppWhitelistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAppWhitelist extends ManageRecords
{
    protected static string $resource = AppWhitelistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
