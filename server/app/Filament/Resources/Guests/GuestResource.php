<?php

namespace App\Filament\Resources\Guests;

use App\Enums\GuestStatus;
use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\Guests\Pages\ManageGuests;
use App\Models\Guest;
use App\Services\GuestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class GuestResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = Guest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Tamu & Kamar';

    protected static ?string $modelLabel = 'tamu';

    protected static ?string $pluralModelLabel = 'Tamu';

    protected static ?int $navigationSort = 10;

    protected static function viewRoles(): array
    {
        return [UserRole::FrontOffice];
    }

    /** Guest history is kept; checkout instead of delete. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('room_id')->label('Kamar')
                ->relationship('room', 'number', fn ($query) => $query->where('is_active', true)->orderBy('number'))
                ->searchable()->preload()->required()->prefixIcon(Heroicon::OutlinedHomeModern)
                ->disabledOn('edit'),
            Select::make('salutation')->label('Sapaan')->options([
                'Bapak' => 'Bapak', 'Ibu' => 'Ibu', 'Mr.' => 'Mr.', 'Mrs.' => 'Mrs.', 'Ms.' => 'Ms.',
            ]),
            TextInput::make('name')->label('Nama tamu')->required()->maxLength(255)->prefixIcon(Heroicon::OutlinedUser),
            Select::make('language')->label('Bahasa')->options(['id' => 'Indonesia', 'en' => 'English'])->default('id')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room.number')->label('Kamar')->sortable()->searchable()->icon(Heroicon::OutlinedHomeModern)->iconColor('primary')->weight('bold'),
                TextColumn::make('salutation')->label('Sapaan'),
                TextColumn::make('name')->label('Nama')->searchable()->weight('semibold')->description(fn (Guest $record) => $record->language === 'en' ? 'English' : 'Indonesia'),
                TextColumn::make('status')->badge(),
                TextColumn::make('checked_in_at')->label('Check-in')->dateTime('d M Y H:i')->sortable()->icon(Heroicon::OutlinedCalendarDays)->color('gray'),
                TextColumn::make('checked_out_at')->label('Check-out')->dateTime('d M Y H:i')->placeholder('—'),
            ])
            ->defaultSort('checked_in_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(GuestStatus::class)->default(GuestStatus::InHouse->value),
            ])
            ->recordActions([
                Action::make('checkout')
                    ->label('Check-out')
                    ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                    ->iconButton()->tooltip('Check-out')
                    ->color('danger')
                    ->visible(fn (Guest $record) => $record->status === GuestStatus::InHouse && static::canCreate())
                    ->requiresConfirmation()
                    ->modalDescription('Tamu akan di-check-out dan data aplikasi (YouTube, Netflix, dll.) di TV kamar ini akan dihapus.')
                    ->action(function (Guest $record, GuestService $service) {
                        $n = $service->checkOut($record);
                        Notification::make()->title('Check-out berhasil')->body("Perintah hapus data dikirim ke {$n} TV.")->success()->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Guest::query()->where('status', GuestStatus::InHouse)->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Tamu menginap';
    }

    public static function getPages(): array
    {
        return ['index' => ManageGuests::route('/')];
    }
}
