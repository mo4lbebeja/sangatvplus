<?php

namespace App\Vendor\Resources\Hotels\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Riwayat';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i', 'Asia/Jakarta')->sortable(),
                TextColumn::make('description')->label('Kegiatan')->wrap(),
                TextColumn::make('user.name')->label('Oleh')->placeholder('Sistem'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
