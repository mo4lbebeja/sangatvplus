<?php

namespace App\Vendor\Resources\Invoices;

use App\Mail\InvoiceMail;
use App\Models\Central\Invoice;
use App\Models\Central\VendorActivity;
use App\Vendor\Billing;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InvoiceActions
{
    public static function markPaid(): Action
    {
        return Action::make('markPaid')->label('Tandai lunas')->icon(Heroicon::OutlinedCheckBadge)->color('success')
            ->visible(fn (Invoice $record) => in_array($record->status, ['unpaid', 'draft'], true))
            ->modalHeading(fn (Invoice $record) => "Pembayaran {$record->number}")
            ->modalDescription(fn (Invoice $record) => Billing::rupiah($record->total).' · '.$record->hotel->name.($record->applied_at ? '' : ' · masa sewa akan diperpanjang sampai '.$record->period_end->translatedFormat('d M Y')))
            ->schema([
                DatePicker::make('paid_on')->label('Tanggal bayar')->native(false)->displayFormat('d M Y')->default(now(Billing::TZ))->maxDate(now(Billing::TZ))->required(),
                Select::make('method')->label('Metode')->options(Invoice::METHODS)->default('transfer')->required(),
                TextInput::make('ref')->label('No. referensi / bukti transfer')->maxLength(120),
            ])
            ->action(function (Invoice $record, array $data) {
                try {
                    Billing::markPaid($record, $data['paid_on'], $data['method'], $data['ref'] ?? null);
                    Notification::make()->title("{$record->number} lunas")->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function applyNow(): Action
    {
        return Action::make('applyNow')->label('Perpanjang sekarang')->icon(Heroicon::OutlinedForward)->color('warning')
            ->tooltip('Perpanjang masa sewa sebelum tagihan lunas')
            ->visible(fn (Invoice $record) => $record->status === 'unpaid' && $record->applied_at === null)
            ->requiresConfirmation()
            ->modalDescription(fn (Invoice $record) => 'Masa sewa '.$record->hotel->name.' diperpanjang sampai '.$record->period_end->translatedFormat('d M Y').' walaupun belum dibayar.')
            ->action(fn (Invoice $record) => Billing::apply($record));
    }

    public static function print(): Action
    {
        return Action::make('print')->label('Cetak / PDF')->icon(Heroicon::OutlinedPrinter)->color('gray')
            ->url(fn (Invoice $record) => route('vendor.invoice.print', $record), shouldOpenInNewTab: true);
    }

    public static function send(): Action
    {
        return Action::make('send')->label('Kirim email')->icon(Heroicon::OutlinedEnvelope)->color('gray')
            ->visible(fn (Invoice $record) => $record->status !== 'void')
            ->schema([
                TextInput::make('to')->label('Kepada')->email()->required()
                    ->default(fn (Invoice $record) => $record->hotel->billing_email ?: $record->hotel->contact_email),
            ])
            ->action(function (Invoice $record, array $data) {
                try {
                    Mail::to($data['to'])->send(new InvoiceMail($record));
                    VendorActivity::record('invoice.sent', "Tagihan {$record->number} dikirim ke {$data['to']}", $record->hotel);
                    Notification::make()->title("Tagihan dikirim ke {$data['to']}")->success()->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Email gagal dikirim')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function void(): Action
    {
        return Action::make('void')->label('Batalkan')->icon(Heroicon::OutlinedXCircle)->color('danger')
            ->visible(fn (Invoice $record) => in_array($record->status, ['unpaid', 'draft'], true))
            ->requiresConfirmation()
            ->modalDescription('Tagihan dibatalkan. Masa sewa yang sudah diperpanjang dengan tagihan ini tidak otomatis dikurangi.')
            ->schema([TextInput::make('reason')->label('Alasan')->maxLength(160)])
            ->action(fn (Invoice $record, array $data) => Billing::void($record, $data['reason'] ?? null));
    }

    /** @return array<int, Action> */
    public static function all(): array
    {
        return [static::markPaid(), static::print(), static::send(), static::applyNow(), static::void()];
    }
}
