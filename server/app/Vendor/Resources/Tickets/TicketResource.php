<?php

namespace App\Vendor\Resources\Tickets;

use App\Models\Central\Ticket;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\Tickets\Pages\ListTickets;
use App\Vendor\Resources\Tickets\Pages\ViewTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TicketResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'tiket';

    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Klien';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'tiket';

    protected static ?string $pluralModelLabel = 'Tiket Dukungan';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static function area(): string
    {
        return 'support';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Ticket::query()->whereIn('status', ['open', 'in_progress'])->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'subject'];
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')->label('Status')->badge()
            ->formatStateUsing(fn ($state) => Ticket::STATUSES[$state] ?? $state)
            ->icon(fn ($state) => ['open' => Heroicon::OutlinedInboxArrowDown, 'in_progress' => Heroicon::OutlinedWrenchScrewdriver, 'waiting' => Heroicon::OutlinedClock,
                'resolved' => Heroicon::OutlinedCheckCircle, 'closed' => Heroicon::OutlinedArchiveBox][$state] ?? null)
            ->color(fn ($state) => ['open' => 'danger', 'in_progress' => 'info', 'waiting' => 'warning', 'resolved' => 'success', 'closed' => 'gray'][$state] ?? 'gray');
    }

    public static function priorityColumn(): TextColumn
    {
        return TextColumn::make('priority')->label('Prioritas')->badge()
            ->formatStateUsing(fn ($state) => Ticket::PRIORITIES[$state] ?? $state)
            ->icon(fn ($state) => $state === 'urgent' ? Heroicon::OutlinedFire : ($state === 'high' ? Heroicon::OutlinedArrowUp : Heroicon::OutlinedMinus))
            ->color(fn ($state) => ['urgent' => 'danger', 'high' => 'warning', 'normal' => 'info', 'low' => 'gray'][$state] ?? 'gray');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['hotel', 'assignee']))
            ->columns([
                TextColumn::make('subject')->label('Tiket')->weight('bold')->searchable(['subject', 'number'])->wrap()
                    ->description(fn (Ticket $t) => "{$t->number} · ".($t->hotel?->name ?? '')),
                TextColumn::make('category')->label('Kategori')->badge()->color('gray')->formatStateUsing(fn ($state) => Ticket::CATEGORIES[$state] ?? $state)
                    ->icon(fn ($state) => ['technical' => Heroicon::OutlinedTv, 'billing' => Heroicon::OutlinedBanknotes, 'content' => Heroicon::OutlinedFilm,
                        'request' => Heroicon::OutlinedLightBulb, 'other' => Heroicon::OutlinedChatBubbleLeftEllipsis][$state] ?? null),
                static::priorityColumn(),
                static::statusColumn(),
                TextColumn::make('assignee.name')->label('Ditangani')->placeholder('Belum ada')->icon(Heroicon::OutlinedUserCircle),
                TextColumn::make('last_reply_at')->label('Balasan terakhir')->since()->sortable()
                    ->description(fn (Ticket $t) => $t->last_reply_by === 'hotel' ? 'dari hotel — perlu dibalas' : 'dari Sijalucom')
                    ->color(fn (Ticket $t) => $t->last_reply_by === 'hotel' && $t->isOpen() ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('hotel_id')->label('Hotel')->relationship('hotel', 'name'),
                SelectFilter::make('category')->label('Kategori')->options(Ticket::CATEGORIES),
                SelectFilter::make('priority')->label('Prioritas')->options(Ticket::PRIORITIES),
            ])
            ->defaultSort('last_reply_at', 'desc')
            ->recordUrl(fn (Ticket $t) => static::getUrl('view', ['record' => $t]))
            ->emptyStateIcon(Heroicon::OutlinedLifebuoy)
            ->emptyStateHeading('Belum ada tiket')
            ->emptyStateDescription('Hotel membuat tiket dari CMS mereka (menu Bantuan Sijalucom).');
    }

    public static function getPages(): array
    {
        return ['index' => ListTickets::route('/'), 'view' => ViewTicket::route('/{record}')];
    }
}
