<?php

namespace App\Filament\Resources\Facilities;

use App\Enums\FacilityCategory;
use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\Facilities\Pages\ManageFacilities;
use App\Models\Facility;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class FacilityResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = Facility::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?string $modelLabel = 'fasilitas';

    protected static ?string $pluralModelLabel = 'Fasilitas & Restoran';

    protected static ?int $navigationSort = 20;

    protected static function viewRoles(): array
    {
        return [UserRole::FrontOffice];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('category')->label('Kategori')->options(FacilityCategory::class)->required()->default(FacilityCategory::Facility->value),
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            TextInput::make('location')->label('Lokasi')->prefixIcon(Heroicon::OutlinedMapPin)->placeholder('Lantai 4')->maxLength(255),
            TextInput::make('hours')->label('Jam buka')->prefixIcon(Heroicon::OutlinedClock)->placeholder('06.00 – 22.00')->maxLength(255),
            Textarea::make('description')->label('Deskripsi')->rows(3)->maxLength(500)->columnSpanFull(),
            FileUpload::make('image_path')->label('Foto')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->disk('public')->directory('facilities')->maxSize(8 * 1024)
                ->helperText('Hingga 8 MB, otomatis dikecilkan.')->columnSpanFull(),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')->label('')->disk('public')->imageHeight(40),
                TextColumn::make('name')->label('Nama')->searchable()->weight('semibold'),
                TextColumn::make('category')->label('Kategori')->badge(),
                TextColumn::make('location')->label('Lokasi')->icon(Heroicon::OutlinedMapPin)->color('gray'),
                TextColumn::make('hours')->label('Jam')->icon(Heroicon::OutlinedClock)->color('gray'),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([SelectFilter::make('category')->label('Kategori')->options(FacilityCategory::class)])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFacilities::route('/')];
    }
}
