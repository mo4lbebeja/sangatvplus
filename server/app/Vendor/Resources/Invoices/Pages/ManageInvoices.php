<?php

namespace App\Vendor\Resources\Invoices\Pages;

use App\Models\Central\Hotel;
use App\Vendor\Billing;
use App\Vendor\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class ManageInvoices extends ManageRecords
{
    protected static string $resource = InvoiceResource::class;

    protected ?string $subheading = 'Tagihan sewa bulanan semua hotel. Masa sewa bertambah saat tagihan ditandai lunas.';

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')->icon(Heroicon::OutlinedQueueList),
            'unpaid' => Tab::make('Belum dibayar')->icon(Heroicon::OutlinedClock)->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'unpaid')),
            'overdue' => Tab::make('Lewat tempo')->icon(Heroicon::OutlinedExclamationTriangle)->modifyQueryUsing(fn (Builder $query) => $query->overdue()),
            'paid' => Tab::make('Lunas')->icon(Heroicon::OutlinedCheckBadge)->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [\App\Vendor\Widgets\InvoiceListStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reportCsv')->label('Ekspor CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->url(route('vendor.report.invoices'), shouldOpenInNewTab: true),
            Action::make('create')->label('Buat tagihan')->icon(Heroicon::OutlinedPlus)
                ->schema([
                    Select::make('hotel_id')->label('Hotel')->options(fn () => Hotel::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                    Select::make('months')->label('Durasi')->options(Billing::CYCLES + [24 => '24 bulan'])->default(1)->required(),
                    Toggle::make('apply')->label('Perpanjang masa sewa sekarang (tanpa menunggu lunas)'),
                    Textarea::make('notes')->label('Catatan di tagihan')->rows(2),
                ])
                ->action(function (array $data) {
                    try {
                        $invoice = Billing::createInvoice(Hotel::query()->findOrFail($data['hotel_id']), (int) $data['months'], (bool) $data['apply'], notes: $data['notes'] ?? null);
                        $this->redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
                    } catch (Throwable $e) {
                        Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
