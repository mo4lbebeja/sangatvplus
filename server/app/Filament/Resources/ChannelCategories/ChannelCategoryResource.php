<?php

namespace App\Filament\Resources\ChannelCategories;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\ChannelCategories\Pages\ManageChannelCategories;
use App\Models\ChannelCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ChannelCategoryResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = ChannelCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Siaran';

    protected static ?string $modelLabel = 'kategori';

    protected static ?string $pluralModelLabel = 'Kategori Channel';

    protected static ?int $navigationSort = 20;

    protected static function viewRoles(): array
    {
        return [UserRole::It];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(64)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
            TextInput::make('slug')->required()->maxLength(64)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('slug'),
                TextColumn::make('channels_count')->counts('channels')->label('Channel'),
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageChannelCategories::route('/')];
    }
}
