<?php

namespace App\Filament\Resources\ChannelCategories\Pages;

use App\Filament\Resources\ChannelCategories\ChannelCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChannelCategories extends ManageRecords
{
    protected static string $resource = ChannelCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
