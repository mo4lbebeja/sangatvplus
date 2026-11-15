<?php

namespace App\Vendor\Resources\Invoices\Pages;

use App\Vendor\Resources\Invoices\InvoiceActions;
use App\Vendor\Resources\Invoices\InvoiceResource;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Tagihan '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        return InvoiceActions::all();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('document')->hiddenLabel()->columnSpanFull()->view('vendor-console.invoice-embed'),
        ]);
    }
}
