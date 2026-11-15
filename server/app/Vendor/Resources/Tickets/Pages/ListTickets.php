<?php

namespace App\Vendor\Resources\Tickets\Pages;

use App\Vendor\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected ?string $subheading = 'Keluhan dan permintaan dari hotel. Balasan Anda dikirim ke email pembuat tiket dan tampil di CMS hotel.';

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Perlu ditangani')->icon(Heroicon::OutlinedInboxArrowDown)->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['open', 'in_progress'])),
            'waiting' => Tab::make('Menunggu hotel')->icon(Heroicon::OutlinedClock)->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'waiting')),
            'done' => Tab::make('Selesai')->icon(Heroicon::OutlinedCheckCircle)->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['resolved', 'closed'])),
            'all' => Tab::make('Semua')->icon(Heroicon::OutlinedQueueList),
        ];
    }
}
