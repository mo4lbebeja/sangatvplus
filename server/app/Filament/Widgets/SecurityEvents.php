<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SecurityEvents extends TableWidget
{
    /** Render with the page: no loading placeholder that later changes height (layout jump). */
    protected static bool $isLazy = false;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Aktivitas keamanan terbaru';

    public static function canView(): bool
    {
        return AuditLogResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AuditLog::query()->with(['actor' => fn (MorphTo $m) => $m->morphWith([Device::class => ['room']])])
                ->where(fn ($q) => $q->where('event', 'like', 'technician.%')->orWhere('event', 'like', 'device.%')->orWhere('event', 'like', 'guest.%'))
                ->latest('created_at'))
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->since()->icon(Heroicon::OutlinedClock)->color('gray'),
                TextColumn::make('event')->badge()
                    ->color(fn (string $state) => str_contains($state, 'failed') || str_contains($state, 'locked') || str_contains($state, 'revoked') ? 'danger' : (str_contains($state, 'ok') || str_contains($state, 'provisioned') ? 'success' : 'gray'))
                    ->icon(fn (string $state) => match (true) {
                        str_starts_with($state, 'technician.') => Heroicon::OutlinedKey,
                        str_starts_with($state, 'guest.') => Heroicon::OutlinedUser,
                        default => Heroicon::OutlinedTv,
                    }),
                TextColumn::make('actor')->label('Pelaku')->state(fn (AuditLog $record) => match (true) {
                    $record->actor instanceof User => $record->actor->name,
                    $record->actor instanceof Device => 'TV kamar '.($record->actor->room?->number ?? '?'),
                    default => 'Sistem',
                }),
                TextColumn::make('ip')->label('IP')->color('gray')->placeholder('—'),
            ])
            ->emptyStateHeading('Belum ada aktivitas')
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck);
    }
}
