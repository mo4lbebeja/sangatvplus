<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use UnitEnum;

class AuditLogResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'log';

    protected static ?string $pluralModelLabel = 'Audit Log';

    protected static ?int $navigationSort = 20;

    protected static function viewRoles(): array
    {
        return [UserRole::It];
    }

    // Audit log is append-only.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function actorLabel(AuditLog $log): string
    {
        return match (true) {
            $log->actor instanceof User => $log->actor->name,
            $log->actor instanceof Device => 'TV kamar '.($log->actor->room?->number ?? '?'),
            default => 'Sistem',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s'),
            TextEntry::make('event'),
            TextEntry::make('actor')->label('Pelaku')->state(fn (AuditLog $record) => static::actorLabel($record)),
            TextEntry::make('subject')->label('Objek')->state(fn (AuditLog $record) => $record->subject_type ? "{$record->subject_type} #{$record->subject_id}" : '—'),
            TextEntry::make('ip')->label('IP')->placeholder('—'),
            KeyValueEntry::make('properties')->label('Detail')->columnSpanFull()
                ->state(fn (AuditLog $record) => collect($record->properties ?? [])->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['actor' => fn (MorphTo $m) => $m->morphWith([Device::class => ['room']])]))
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable()->icon(Heroicon::OutlinedClock)->color('gray'),
                TextColumn::make('event')->searchable()->badge()
                    ->color(fn (string $state) => str_contains($state, 'failed') || str_contains($state, 'locked') || str_contains($state, 'revoked') ? 'danger' : 'gray'),
                TextColumn::make('actor_label')->label('Pelaku')->state(fn (AuditLog $record) => static::actorLabel($record))->icon(fn (AuditLog $record) => $record->actor instanceof Device ? Heroicon::OutlinedTv : ($record->actor ? Heroicon::OutlinedUser : Heroicon::OutlinedCog6Tooth)),
                TextColumn::make('subject_type')->label('Objek')->formatStateUsing(fn ($state, AuditLog $record) => "{$state} #{$record->subject_id}")->placeholder('—'),
                TextColumn::make('ip')->label('IP')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('security')->label('Hanya event keamanan')
                    ->query(fn (Builder $query) => $query->where(fn ($q) => $q->where('event', 'like', 'technician.%')->orWhere('event', 'like', 'device.%')->orWhere('event', 'like', '%.failed'))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = AuditLog::query()->where('created_at', '>', now()->subDay())
            ->where(fn ($q) => $q->where('event', 'like', '%failed%')->orWhere('event', 'like', '%locked%')->orWhere('event', 'like', '%revoked%'))
            ->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Event keamanan 24 jam terakhir';
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }
}
