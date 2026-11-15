<?php

namespace App\Vendor\Resources\Activities;

use App\Models\Central\VendorActivity;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\Activities\Pages\ListActivities;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ActivityResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'aktivitas';

    protected static ?string $model = VendorActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'aktivitas';

    protected static ?string $pluralModelLabel = 'Log Aktivitas';

    protected static function area(): string
    {
        return 'owner';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i', 'Asia/Jakarta')->sortable(),
                TextColumn::make('hotel.name')->label('Hotel')->placeholder('–')->searchable(),
                TextColumn::make('description')->label('Kegiatan')->wrap()->searchable(),
                TextColumn::make('user.name')->label('Oleh')->placeholder('Sistem'),
            ])
            ->filters([
                SelectFilter::make('hotel_id')->label('Hotel')->relationship('hotel', 'name'),
                SelectFilter::make('vendor_user_id')->label('Oleh')->relationship('user', 'name'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListActivities::route('/')];
    }
}
