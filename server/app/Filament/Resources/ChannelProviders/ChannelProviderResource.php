<?php

namespace App\Filament\Resources\ChannelProviders;

use App\Enums\ProviderSigning;
use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\ChannelProviders\Pages\ManageChannelProviders;
use App\Models\ChannelProvider;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ChannelProviderResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = ChannelProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Siaran';

    protected static ?string $modelLabel = 'penyedia';

    protected static ?string $pluralModelLabel = 'Penyedia Channel';

    protected static ?int $navigationSort = 30;

    protected static function viewRoles(): array
    {
        return [UserRole::It];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama penyedia')->required()->maxLength(255),
            Select::make('signing')->label('Metode token URL')->options(ProviderSigning::class)->default(ProviderSigning::None->value)->required(),
            TextInput::make('token_ttl_seconds')->label('Umur token (detik)')->numeric()->minValue(60)->maxValue(3600)->default(600)->required(),
            // Write-only secret: never pre-filled, kept unchanged when left empty.
            TextInput::make('secret')->label('Secret / kunci penyedia')
                ->password()->revealable()
                ->helperText('Disimpan terenkripsi di server, tidak pernah dikirim ke TV. Kosongkan saat edit untuk mempertahankan nilai lama.')
                ->dehydrated(fn (?string $state) => filled($state)),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    /** Move the write-only "secret" field into the encrypted credentials column. */
    public static function mergeSecret(array $data, ?ChannelProvider $record = null): array
    {
        if (filled($data['secret'] ?? null)) {
            $data['credentials'] = array_merge($record?->credentials ?? [], ['secret' => $data['secret']]);
        }
        unset($data['secret']);

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('signing')->label('Token')->badge(),
                TextColumn::make('token_ttl_seconds')->label('TTL (dtk)'),
                IconColumn::make('has_secret')->label('Secret')->boolean()->state(fn (ChannelProvider $r) => filled($r->credentials['secret'] ?? null)),
                TextColumn::make('channels_count')->counts('channels')->label('Channel'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data, ChannelProvider $record) => static::mergeSecret($data, $record)),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageChannelProviders::route('/')];
    }
}
