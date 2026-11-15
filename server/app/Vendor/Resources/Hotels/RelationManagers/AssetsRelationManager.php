<?php

namespace App\Vendor\Resources\Hotels\RelationManagers;

use App\Vendor\Resources\Assets\AssetResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedArchiveBox;

    protected static ?string $title = 'Perangkat';

    public function table(Table $table): Table
    {
        return AssetResource::table($table)->filters([]);
    }
}
