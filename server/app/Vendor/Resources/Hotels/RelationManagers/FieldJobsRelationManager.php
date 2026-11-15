<?php

namespace App\Vendor\Resources\Hotels\RelationManagers;

use App\Vendor\Resources\FieldJobs\FieldJobResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FieldJobsRelationManager extends RelationManager
{
    protected static string $relationship = 'fieldJobs';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedTruck;

    protected static ?string $title = 'Kunjungan';

    public function table(Table $table): Table
    {
        return FieldJobResource::table($table)->filters([]);
    }
}
