<?php

namespace App\Vendor\Resources\Hotels\Pages;

use App\Models\Central\Hotel;
use App\Models\Central\Invoice;
use App\Models\Central\VendorActivity;
use App\Tenancy\HotelManager;
use App\Vendor\Billing;
use App\Vendor\Resources\Hotels\HotelResource;
use App\Vendor\Widgets\HotelListStats;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ManageHotels extends ManageRecords
{
    protected static string $resource = HotelResource::class;

    protected ?string $subheading = 'Semua hotel penyewa SangaTV Plus: paket, masa sewa, TV, dan status.';

    protected function getHeaderWidgets(): array
    {
        return [HotelListStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Hotel baru')->icon(Heroicon::OutlinedPlus)->modalIcon(Heroicon::OutlinedBuildingOffice2)->modalDescription('Kode hotel, paket, penagihan, lalu aktivasi — 4 langkah.')
                ->visible(fn () => HotelResource::allowed('owner'))
                ->modalWidth('4xl')
                ->steps([
                    Step::make('Hotel')->icon(Heroicon::OutlinedBuildingOffice2)->description('Identitas & kontak')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('slug')->label('Kode Hotel')->required()->maxLength(40)
                                ->helperText('Diketik staf sebelum login, mis. napolin. Huruf kecil, angka, tanda minus.')
                                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                    $slug = strtolower(trim((string) $value));
                                    if (! HotelManager::validSlug($slug)) {
                                        $fail('Kode hotel hanya huruf kecil, angka, dan tanda minus (3–40 karakter), bukan kata cadangan.');
                                    } elseif (Hotel::query()->where('slug', $slug)->exists()) {
                                        $fail('Kode hotel sudah dipakai.');
                                    }
                                }),
                            TextInput::make('name')->label('Nama hotel')->required()->maxLength(120),
                            ...HotelResource::contactFields(),
                        ]),
                    ]),
                    Step::make('Paket')->icon(Heroicon::OutlinedCreditCard)->description('Harga & kuota')->schema([
                        Grid::make(2)->schema(HotelResource::subscriptionFields()),
                    ]),
                    Step::make('Penagihan')->icon(Heroicon::OutlinedDocumentText)->description('Data tagihan')->schema([
                        Grid::make(2)->schema(HotelResource::billingFields()),
                    ]),
                    Step::make('Aktivasi')->icon(Heroicon::OutlinedRocketLaunch)->description('Mulai sewa & admin')->schema([
                        Radio::make('activation')->label('Mulai layanan')->required()->default('unpaid')->live()->options([
                            'paid' => 'Aktifkan hari ini — sudah dibayar (tagihan lunas)',
                            'unpaid' => 'Aktifkan hari ini — buat tagihan (bayar menyusul)',
                            'trial' => 'Uji coba gratis',
                            'later' => 'Belum diaktifkan (hanya siapkan akun)',
                        ]),
                        Grid::make(2)->schema([
                            Select::make('months')->label('Durasi pertama')->options(Billing::CYCLES)->default(1)
                                ->visible(fn (Get $get) => in_array($get('activation'), ['paid', 'unpaid'], true)),
                            Select::make('method')->label('Metode bayar')->options(Invoice::METHODS)->default('transfer')
                                ->visible(fn (Get $get) => $get('activation') === 'paid'),
                            TextInput::make('trial_days')->label('Lama uji coba (hari)')->numeric()->minValue(1)->maxValue(90)->default(14)
                                ->visible(fn (Get $get) => $get('activation') === 'trial'),
                            TextInput::make('admin_email')->label('Email admin hotel (login CMS)')->email()->required(),
                        ]),
                    ]),
                ])
                ->using(function (array $data) {
                    try {
                        $r = app(HotelManager::class)->provision($data['slug'], $data['name'], $data['admin_email']);
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('Hotel gagal dibuat')->body($e->getMessage())->danger()->persistent()->send();
                        throw new Halt;
                    }
                    $hotel = $r['hotel'];
                    $hotel->update(collect($data)->only(['city', 'contact_name', 'contact_phone', 'contact_email', 'plan_id', 'tv_quota', 'billing_months',
                        'custom_monthly_price', 'discount_percent', 'auto_invoice', 'billing_name', 'billing_email', 'npwp', 'billing_address'])
                        ->map(fn ($v) => $v === '' ? null : $v)->all() + ['discount_percent' => (float) ($data['discount_percent'] ?? 0)]);
                    VendorActivity::record('hotel.created', "Hotel dibuat (kode {$hotel->slug})", $hotel);

                    $extra = '';
                    try {
                        match ($data['activation']) {
                            'paid', 'unpaid' => (function () use ($hotel, $data, &$extra) {
                                $invoice = Billing::createInvoice($hotel->fresh(), (int) $data['months'], applyNow: true);
                                if ($data['activation'] === 'paid') {
                                    Billing::markPaid($invoice, now(Billing::TZ)->toDateString(), $data['method'] ?? 'transfer');
                                }
                                $extra = " Tagihan {$invoice->number} dibuat.";
                            })(),
                            'trial' => (function () use ($hotel, $data, &$extra) {
                                $days = (int) ($data['trial_days'] ?: 14);
                                $end = now(Billing::TZ)->addDays($days - 1);
                                $hotel->update(['service_expires_at' => $end->toDateString(), 'activated_at' => now()]);
                                VendorActivity::record('rental.trial', "Uji coba {$days} hari sampai ".$end->translatedFormat('d M Y'), $hotel);
                                $extra = " Uji coba sampai {$end->translatedFormat('d M Y')}.";
                            })(),
                            default => null,
                        };
                    } catch (Throwable $e) {
                        $extra = ' Aktivasi belum berhasil: '.$e->getMessage();
                    }

                    Notification::make()->title("Hotel {$hotel->name} siap")
                        ->body("Login CMS: Kode Hotel {$hotel->slug} · {$data['admin_email']} · kata sandi sementara: {$r['password']} (catat sekarang; admin wajib aktifkan verifikasi dua langkah).{$extra}")
                        ->success()->persistent()->send();

                    return $hotel;
                })
                ->successNotification(null),
        ];
    }
}
