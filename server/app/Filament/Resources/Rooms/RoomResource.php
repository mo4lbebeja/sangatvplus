<?php

namespace App\Filament\Resources\Rooms;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\Rooms\Pages\ManageRooms;
use App\Models\ProvisioningCode;
use App\Models\Room;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class RoomResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = Room::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Tamu & Kamar';

    protected static ?string $modelLabel = 'kamar';

    protected static ?string $pluralModelLabel = 'Kamar';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'number';

    protected static function viewRoles(): array
    {
        return [UserRole::FrontOffice, UserRole::It];
    }

    protected static function manageRoles(): array
    {
        return [UserRole::It];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('number')->label('Nomor kamar')->prefixIcon(Heroicon::OutlinedHomeModern)->required()->maxLength(16)->unique(ignoreRecord: true),
            TextInput::make('floor')->label('Lantai')->required()->numeric()->minValue(0)->maxValue(200),
            TextInput::make('type')->label('Tipe')->maxLength(64),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Kamar')->searchable()->sortable()->icon(Heroicon::OutlinedHomeModern)->iconColor('primary')->weight('bold'),
                TextColumn::make('floor')->label('Lantai')->sortable()->icon(Heroicon::OutlinedBuildingOffice)->color('gray'),
                TextColumn::make('type')->label('Tipe')->badge()->color(fn (?string $state) => $state === 'Suite' ? 'warning' : 'gray'),
                TextColumn::make('currentGuest.name')->label('Tamu')->placeholder('Kosong')->icon(Heroicon::OutlinedUser)->iconColor('success'),
                TextColumn::make('devices_count')->counts('devices')->label('TV')->icon(Heroicon::OutlinedTv)->color(fn (int $state) => $state > 0 ? 'primary' : 'gray'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('number')
            ->filters([
                SelectFilter::make('floor')->label('Lantai')->options(fn () => Room::query()->distinct()->orderBy('floor')->pluck('floor', 'floor')->all()),
            ])
            ->recordActions([
                Action::make('provisioningCode')
                    ->label('Kode daftar TV')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->iconButton()->tooltip('Kode daftar TV')->color('primary')
                    ->visible(fn () => static::canCreate())
                    ->requiresConfirmation()
                    ->modalDescription('Buat kode sekali pakai (berlaku 30 menit) untuk mendaftarkan TV di kamar ini. Kode lama yang belum dipakai tetap berlaku sampai kedaluwarsa.')
                    ->action(function (Room $record) {
                        [$code, $plain] = ProvisioningCode::issue($record, Auth::user());
                        Audit::log('provisioning_code.issued', $record, ['expires_at' => $code->expires_at->toIso8601String()]);
                        Notification::make()
                            ->title("Kode kamar {$record->number}: {$plain}")
                            ->body('Masukkan kode ini di layar pendaftaran TV. Berlaku sampai '.$code->expires_at->timezone(config('app.timezone'))->format('H:i').'. Kode tidak akan ditampilkan lagi.')
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRooms::route('/')];
    }
}
