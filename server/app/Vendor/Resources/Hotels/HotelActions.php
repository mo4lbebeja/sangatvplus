<?php

namespace App\Vendor\Resources\Hotels;

use App\Enums\UserRole;
use App\Models\Central\Hotel;
use App\Models\Central\Invoice;
use App\Models\Central\VendorActivity;
use App\Models\User;
use App\Tenancy\HotelManager;
use App\Vendor\Billing;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\HotelStats;
use App\Vendor\Impersonation;
use App\Vendor\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Throwable;

/** Hotel actions shared by the list and the detail page. */
class HotelActions
{
    use VendorAccess;

    public static function extend(): Action
    {
        return Action::make('extend')->label('Perpanjang sewa')->icon(Heroicon::OutlinedCalendarDays)->color('primary')
            ->visible(fn () => static::allowed('finance'))
            ->modalHeading(fn (Hotel $record) => "Perpanjang sewa {$record->name}")
            ->modalDescription(fn (Hotel $record) => 'Sewa dihitung per bulan, mulai '.Billing::nextPeriod($record, 1)[0]->translatedFormat('d M Y')
                .'. Harga: '.Billing::rupiah($record->monthlyPrice()).'/bulan.')
            ->schema([
                Select::make('months')->label('Durasi')->options(Billing::CYCLES + [24 => '24 bulan'])->required()
                    ->default(fn (Hotel $record) => $record->billing_months)->live(),
                Radio::make('mode')->label('Pembayaran')->required()->default('unpaid')->options([
                    'paid' => 'Sudah dibayar — buat tagihan lunas & perpanjang',
                    'unpaid' => 'Buat tagihan, perpanjang sekarang (bayar menyusul)',
                    'on_payment' => 'Buat tagihan, perpanjang setelah lunas',
                    'free' => 'Tanpa tagihan (kompensasi / gratis)',
                ])->live(),
                Select::make('method')->label('Metode bayar')->options(Invoice::METHODS)->default('transfer')->visible(fn (Get $get) => $get('mode') === 'paid'),
                TextInput::make('ref')->label('No. referensi / bukti')->maxLength(120)->visible(fn (Get $get) => $get('mode') === 'paid'),
                Textarea::make('notes')->label('Catatan')->rows(2),
            ])
            ->action(function (Hotel $record, array $data) {
                try {
                    $months = (int) $data['months'];
                    if ($data['mode'] === 'free') {
                        [, $end] = Billing::nextPeriod($record, $months);
                        $record->update(['service_expires_at' => $end->toDateString(), 'service_released_at' => null, 'activated_at' => $record->activated_at ?? now()]);
                        VendorActivity::record('rental.extended', "Diperpanjang gratis {$months} bulan sampai ".$end->translatedFormat('d M Y').($data['notes'] ? " — {$data['notes']}" : ''), $record);
                        Notification::make()->title('Sewa diperpanjang sampai '.$end->translatedFormat('d M Y'))->success()->send();

                        return;
                    }
                    $invoice = Billing::createInvoice($record, $months, applyNow: $data['mode'] === 'unpaid', notes: $data['notes'] ?? null);
                    if ($data['mode'] === 'paid') {
                        Billing::markPaid($invoice, now(Billing::TZ)->toDateString(), $data['method'], $data['ref'] ?? null);
                    }
                    Notification::make()->title("Tagihan {$invoice->number} dibuat")
                        ->body($data['mode'] === 'on_payment' ? 'Masa sewa bertambah setelah tagihan ditandai lunas.' : 'Sewa sampai '.$record->fresh()->service_expires_at->setTimezone(Billing::TZ)->translatedFormat('d M Y').'.')
                        ->actions([Action::make('open')->label('Lihat tagihan')->url(InvoiceResource::getUrl('view', ['record' => $invoice]))])
                        ->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function impersonate(): Action
    {
        return Action::make('impersonate')->label('Masuk CMS')->icon(Heroicon::OutlinedArrowRightEndOnRectangle)->color('gray')
            ->visible(fn (Hotel $record) => $record->isAccessible() && static::allowed('support'))
            ->modalHeading(fn (Hotel $record) => "Masuk ke CMS {$record->name}")
            ->modalDescription('Tanpa kata sandi staf. Tercatat di riwayat aktivitas hotel; tekan "Selesai" di bilah atas untuk kembali.')
            ->schema([
                Select::make('user_id')->label('Masuk sebagai')->required()
                    ->options(fn (Hotel $record) => rescue(fn () => app(HotelManager::class)->run($record, fn () => User::query()->where('is_active', true)
                        ->orderBy('role')->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} · {$u->email} ({$u->role->value})"])->all()), [], false))
                    ->default(fn (Hotel $record) => rescue(fn () => app(HotelManager::class)->run($record, fn () => User::query()
                        ->where('is_active', true)->where('role', UserRole::Admin)->value('id')), null, false)),
            ])
            ->modalSubmitActionLabel('Masuk')
            ->action(function (Hotel $record, array $data, $livewire) {
                Impersonation::start($record, (int) $data['user_id'], Filament::auth()->user());
                VendorActivity::record('support.login', 'Masuk ke CMS hotel (dukungan)', $record);
                $livewire->redirect(url('/admin'), navigate: false);
            });
    }

    public static function release(): Action
    {
        return Action::make('release')->label('Lepas layanan TV')->icon(Heroicon::OutlinedPower)->color('danger')
            ->visible(fn (Hotel $record) => $record->service_released_at === null && static::allowed('owner'))
            ->requiresConfirmation()
            ->modalDescription('Semua TV hotel ini masuk Mode lepas (kembali ke launcher bawaan) pada heartbeat berikutnya. CMS tetap bisa dibuka untuk ekspor data.')
            ->action(function (Hotel $record) {
                $record->update(['service_released_at' => now()]);
                VendorActivity::record('service.released', 'Layanan TV dilepas (kontrak selesai)', $record);
            });
    }

    public static function restore(): Action
    {
        return Action::make('restore')->label('Pulihkan layanan TV')->icon(Heroicon::OutlinedArrowPath)->color('success')
            ->visible(fn (Hotel $record) => $record->service_released_at !== null && static::allowed('owner'))
            ->requiresConfirmation()
            ->action(function (Hotel $record) {
                $record->update(['service_released_at' => null]);
                VendorActivity::record('service.restored', 'Layanan TV dipulihkan', $record);
            });
    }

    public static function suspend(): Action
    {
        return Action::make('suspend')->label('Tangguhkan hotel')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
            ->visible(fn (Hotel $record) => $record->status === 'active' && static::allowed('owner'))
            ->requiresConfirmation()
            ->modalDescription('Staf hotel tidak bisa login, TV & PMS hotel ini ditolak server (TV memakai data tersimpan, lalu Mode lepas setelah 30 hari offline). Data tidak dihapus.')
            ->schema([TextInput::make('reason')->label('Alasan')->placeholder('mis. tagihan belum dibayar')->maxLength(160)])
            ->action(function (Hotel $record, array $data) {
                $record->update(['status' => 'suspended']);
                VendorActivity::record('hotel.suspended', 'Hotel ditangguhkan'.(filled($data['reason'] ?? null) ? ": {$data['reason']}" : ''), $record);
            });
    }

    public static function activate(): Action
    {
        return Action::make('activate')->label('Aktifkan kembali')->icon(Heroicon::OutlinedCheckCircle)->color('success')
            ->visible(fn (Hotel $record) => $record->status !== 'active' && static::allowed('owner'))
            ->requiresConfirmation()
            ->action(function (Hotel $record) {
                $record->update(['status' => 'active']);
                VendorActivity::record('hotel.activated', 'Hotel diaktifkan kembali', $record);
            });
    }

    public static function refresh(): Action
    {
        return Action::make('refresh')->label('Muat ulang data TV')->icon(Heroicon::OutlinedArrowPath)->color('gray')
            ->action(fn (Hotel $record) => HotelStats::forget($record));
    }

    /** @return array<int, Action> */
    public static function all(): array
    {
        return [static::extend(), static::impersonate(), static::release(), static::restore(), static::suspend(), static::activate(), static::refresh()];
    }
}
