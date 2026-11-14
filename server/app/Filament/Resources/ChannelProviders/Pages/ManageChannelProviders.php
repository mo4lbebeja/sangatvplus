<?php

namespace App\Filament\Resources\ChannelProviders\Pages;

use App\Filament\Resources\ChannelProviders\ChannelProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChannelProviders extends ManageRecords
{
    protected static string $resource = ChannelProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data) => ChannelProviderResource::mergeSecret($data)),
        ];
    }
}
