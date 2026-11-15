<?php

namespace App\Vendor\Resources\Invoices\Pages;

use App\Models\Central\Hotel;
use App\Vendor\Billing;
use App\Vendor\Resources\Invoices\InvoiceResource;
use App\Vendor\Widgets\InvoiceListStats;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
        return [InvoiceListStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reportCsv')->label('Ekspor CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->url(route('vendor.report.invoices'), shouldOpenInNewTab: true),
            Action::make('create')->label('Buat tagihan')->icon(Heroicon::OutlinedPlus)
                ->modalWidth('3xl')->modalIcon(Heroicon::OutlinedDocumentPlus)
                ->schema([
                    Select::make('hotel_id')->label('Hotel')->prefixIcon(Heroicon::OutlinedBuildingOffice2)->options(fn () => Hotel::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                    Radio::make('kind')->label('Jenis')->options(['rental' => 'Sewa bulanan (+ biaya tambahan)', 'one_off' => 'Biaya lain saja (instalasi, kunjungan, perangkat…)'])->default('rental')->inline()->live(),
                    Select::make('months')->label('Durasi sewa')->options(Billing::CYCLES + [24 => '24 bulan'])->default(1)->visible(fn ($get) => $get('kind') === 'rental'),
                    Repeater::make('extras')->label('Biaya tambahan')->addActionLabel('Tambah biaya')->defaultItems(0)->columns(6)
                        ->required(fn ($get) => $get('kind') === 'one_off')->minItems(fn ($get) => $get('kind') === 'one_off' ? 1 : 0)
                        ->schema([
                            TextInput::make('description')->label('Uraian')->required()->columnSpan(3)->placeholder('mis. Instalasi 20 TV'),
                            TextInput::make('qty')->label('Jml')->numeric()->default(1)->minValue(1)->required(),
                            TextInput::make('price')->label('Harga')->numeric()->prefix('Rp')->required()->columnSpan(2),
                        ]),
                    Toggle::make('apply')->label('Perpanjang masa sewa sekarang (tanpa menunggu lunas)')->visible(fn ($get) => $get('kind') === 'rental'),
                    Textarea::make('notes')->label('Catatan di tagihan')->rows(2),
                ])
                ->action(function (array $data) {
                    try {
                        $hotel = Hotel::query()->findOrFail($data['hotel_id']);
                        $invoice = $data['kind'] === 'one_off'
                            ? Billing::createOneOff($hotel, $data['extras'] ?? [], $data['notes'] ?? null)
                            : Billing::createInvoice($hotel, (int) $data['months'], (bool) ($data['apply'] ?? false), notes: $data['notes'] ?? null, extras: $data['extras'] ?? []);
                        $this->redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
                    } catch (Throwable $e) {
                        Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
