<?php

namespace App\Filament\Resources\PmsEvents;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\PmsEvents\Pages\ManagePmsEvents;
use App\Models\PmsEvent;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PmsEventResource extends Resource
{
    use RestrictedByRole;

    protected static function requiredFeature(): ?string
    {
        return 'pms';
    }

    protected static ?string $model = PmsEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Integrasi PMS';

    protected static ?string $modelLabel = 'kiriman PMS';

    protected static ?string $pluralModelLabel = 'Log Kiriman';

    protected static ?string $slug = 'pms-log';

    protected static ?int $navigationSort = 3;

    protected static function viewRoles(): array
    {
        return [UserRole::It];
    }

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

    public static function getNavigationBadge(): ?string
    {
        $n = PmsEvent::query()->where('status', 'error')->where('created_at', '>', now()->subDay())->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Kiriman gagal 24 jam terakhir';
    }

    private static function pretty(mixed $v): string
    {
        return $v ? json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s'),
            TextEntry::make('integration.name')->label('Sistem')->placeholder('Tidak dikenal'),
            TextEntry::make('method')->label('Request')->formatStateUsing(fn ($state, PmsEvent $r) => "{$r->method} {$r->path}")->fontFamily('mono'),
            TextEntry::make('http_status')->label('HTTP')->badge()->color(fn (int $state) => $state < 300 ? 'success' : ($state < 500 ? 'warning' : 'danger')),
            TextEntry::make('message')->label('Pesan')->placeholder('—')->columnSpanFull(),
            TextEntry::make('payload')->label('Data dikirim PMS')->state(fn (PmsEvent $r) => self::pretty($r->payload))->fontFamily('mono')->size('xs')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-wrap']),
            TextEntry::make('response')->label('Respons kita')->state(fn (PmsEvent $r) => self::pretty($r->response))->fontFamily('mono')->size('xs')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-wrap']),
            TextEntry::make('ip')->label('IP')->placeholder('—'),
            TextEntry::make('idempotency_key')->label('Idempotency-Key')->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('integration'))
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M H:i:s')->sortable()->icon(Heroicon::OutlinedClock)->color('gray'),
                TextColumn::make('event')->label('Kejadian')->badge()->color('gray')->placeholder('—')
                    ->icon(fn (?string $state) => match ($state) {
                        'checkin' => Heroicon::OutlinedArrowRightEndOnRectangle,
                        'checkout' => Heroicon::OutlinedArrowRightStartOnRectangle,
                        'move' => Heroicon::OutlinedArrowsRightLeft,
                        'sync' => Heroicon::OutlinedArrowPath,
                        default => Heroicon::OutlinedBolt,
                    }),
                TextColumn::make('reservation_id')->label('Reservasi')->searchable()->fontFamily('mono')->placeholder('—'),
                TextColumn::make('room_number')->label('Kamar')->searchable()->placeholder('—'),
                TextColumn::make('http_status')->label('HTTP')->badge()->color(fn (int $state) => $state < 300 ? 'success' : ($state < 500 ? 'warning' : 'danger')),
                TextColumn::make('message')->label('Pesan')->limit(50)->placeholder('—')->color('gray'),
                TextColumn::make('integration.name')->label('Sistem')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->filters([
                SelectFilter::make('status')->options(['ok' => 'Berhasil', 'error' => 'Gagal']),
                SelectFilter::make('event')->label('Kejadian')->options(['checkin' => 'Check-in', 'checkout' => 'Check-out', 'move' => 'Pindah kamar', 'stay.update' => 'Ubah tamu', 'sync' => 'Sinkron', 'room.upsert' => 'Kamar', 'ping' => 'Ping']),
            ])
            ->recordActions([ViewAction::make()->slideOver()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePmsEvents::route('/')];
    }
}
