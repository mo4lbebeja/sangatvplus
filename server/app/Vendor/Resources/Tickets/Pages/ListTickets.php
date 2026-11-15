<?php

namespace App\Vendor\Resources\Tickets\Pages;

use App\Models\Central\Ticket;
use App\Vendor\CsvExport;
use App\Vendor\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected ?string $subheading = 'Keluhan dan permintaan dari hotel. Balasan Anda dikirim ke email pembuat tiket dan tampil di CMS hotel.';

    protected function getHeaderActions(): array
    {
        return [CsvExport::action('tiket', fn () => Ticket::query()->with(['hotel', 'assignee']), [
            'Nomor' => 'number', 'Hotel' => 'hotel.name', 'Judul' => 'subject', 'Kategori' => 'category', 'Prioritas' => 'priority', 'Status' => 'status',
            'Pelapor' => 'requester_name', 'Ditangani' => 'assignee.name', 'Dibuat' => fn ($t) => $t->created_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
            'Selesai' => fn ($t) => $t->resolved_at?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
        ])];
    }

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
