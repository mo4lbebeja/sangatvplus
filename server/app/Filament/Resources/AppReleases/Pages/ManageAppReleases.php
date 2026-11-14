<?php

namespace App\Filament\Resources\AppReleases\Pages;

use App\Filament\Resources\AppReleases\AppReleaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAppReleases extends ManageRecords
{
    protected static string $resource = AppReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Unggah rilis')->mutateDataUsing(fn (array $data) => AppReleaseResource::withChecksum($data)),
        ];
    }
}
