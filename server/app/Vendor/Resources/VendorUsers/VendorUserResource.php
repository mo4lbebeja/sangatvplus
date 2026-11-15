<?php

namespace App\Vendor\Resources\VendorUsers;

use App\Models\Central\VendorUser;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\VendorUsers\Pages\ManageVendorUsers;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class VendorUserResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'tim';

    protected static ?string $model = VendorUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'akun tim';

    protected static ?string $pluralModelLabel = 'Tim Sijalucom';

    protected static function area(): string
    {
        return 'owner';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(120),
            TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->label('Peran')->options(VendorUser::ROLES)->required()->default('support'),
            Toggle::make('is_active')->label('Aktif')->default(true)->inline(false)
                ->disabled(fn (?VendorUser $record) => $record?->is(static::vendor())),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->weight('bold')->description(fn (VendorUser $u) => $u->email),
                TextColumn::make('role')->label('Peran')->badge()->formatStateUsing(fn ($state) => str(VendorUser::ROLES[$state] ?? $state)->before(' (')),
                IconColumn::make('two_factor')->label('2FA')->boolean()->state(fn (VendorUser $u) => filled($u->app_authentication_secret)),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('created_at')->label('Dibuat')->date('d M Y'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('reset')->label('Reset sandi & 2FA')->icon(Heroicon::OutlinedKey)->color('warning')->iconButton()
                    ->visible(fn (VendorUser $u) => ! $u->is(static::vendor()))
                    ->requiresConfirmation()
                    ->action(function (VendorUser $record) {
                        $password = Str::password(16, symbols: false);
                        $record->forceFill(['password' => $password, 'app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
                        Notification::make()->title('Kata sandi baru')->body("{$record->email} · {$password} (sampaikan langsung; wajib pasang 2FA saat login)")->persistent()->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVendorUsers::route('/')];
    }
}
