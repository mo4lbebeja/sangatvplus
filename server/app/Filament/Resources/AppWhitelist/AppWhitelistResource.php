<?php

namespace App\Filament\Resources\AppWhitelist;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\AppWhitelist\Pages\ManageAppWhitelist;
use App\Models\AppWhitelist;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AppWhitelistResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = AppWhitelist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Perangkat';

    protected static ?string $modelLabel = 'aplikasi';

    protected static ?string $pluralModelLabel = 'Aplikasi Diizinkan';

    protected static ?string $slug = 'app-whitelist';

    protected static ?int $navigationSort = 20;

    protected static function viewRoles(): array
    {
        return [UserRole::It];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('package_name')->label('Package name')->required()->maxLength(255)
                ->regex('/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z0-9_]+)+$/')
                ->unique(ignoreRecord: true)
                ->placeholder('com.google.android.youtube.tv'),
            TextInput::make('label')->label('Nama tampil')->required()->maxLength(64),
            FileUpload::make('icon_path')->label('Ikon (opsional)')->image()->disk('public')->directory('app-icons')->maxSize(256),
            Toggle::make('clear_on_checkout')->label('Hapus data saat check-out')->default(true)
                ->helperText('Login tamu (YouTube, Netflix, dll.) dihapus saat check-out.'),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            Toggle::make('is_active')->label('Tampil di launcher')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon_path')->label('')->disk('public')->imageHeight(28),
                TextColumn::make('label')->label('Aplikasi'),
                TextColumn::make('package_name')->label('Package')->copyable(),
                IconColumn::make('clear_on_checkout')->label('Hapus saat C/O')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAppWhitelist::route('/')];
    }
}
