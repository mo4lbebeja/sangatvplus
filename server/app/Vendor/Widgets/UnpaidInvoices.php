<?php

namespace App\Vendor\Widgets;

use App\Models\Central\Invoice;
use App\Vendor\Billing;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\Invoices\InvoiceResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UnpaidInvoices extends TableWidget
{
    use VendorAccess;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static function area(): string
    {
        return 'finance';
    }

    public static function canView(): bool
    {
        return static::allowed();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Tagihan belum dibayar')
            ->description('Urut jatuh tempo')
            ->query(Invoice::query()->with('hotel')->where('status', 'unpaid')->orderBy('due_at'))
            ->columns([
                TextColumn::make('hotel.name')->label('Hotel')->weight('semibold')->description(fn (Invoice $i) => $i->number),
                TextColumn::make('total')->label('Jumlah')->formatStateUsing(fn ($state) => Billing::rupiah($state))
                    ->description(fn (Invoice $i) => ($i->isOverdue() ? 'lewat tempo ' : 'tempo ').$i->due_at->translatedFormat('d M'))
                    ->color(fn (Invoice $i) => $i->isOverdue() ? 'danger' : null),
            ])
            ->recordUrl(fn (Invoice $i) => InvoiceResource::getUrl('view', ['record' => $i]))
            ->emptyStateHeading('Semua tagihan lunas')->emptyStateDescription(null)
            ->paginated([5]);
    }
}
