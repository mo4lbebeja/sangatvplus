<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna CMS';

    protected static ?int $navigationSort = 10;

    // Admin only (empty list = admin).

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && $record->getKey() !== Auth::id();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255)->prefixIcon(Heroicon::OutlinedUser),
            TextInput::make('email')->prefixIcon(Heroicon::OutlinedEnvelope)->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('role')->label('Peran')->options(UserRole::class)->required(),
            TextInput::make('password')->label('Kata sandi')->password()->revealable()
                ->rule(Password::min(10)->letters()->numbers())
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText('Minimal 10 karakter, huruf dan angka. Kosongkan saat edit untuk tidak mengubah.'),
            Toggle::make('is_active')->label('Aktif')->default(true)
                ->disabled(fn (?User $record) => $record?->getKey() === Auth::id()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->weight('semibold')->icon(Heroicon::OutlinedUserCircle),
                TextColumn::make('email')->searchable()->icon(Heroicon::OutlinedEnvelope)->color('gray')->copyable(),
                TextColumn::make('role')->label('Peran')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
