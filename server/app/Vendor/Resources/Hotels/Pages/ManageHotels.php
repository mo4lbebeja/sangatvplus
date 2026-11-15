<?php

namespace App\Vendor\Resources\Hotels\Pages;

use App\Models\Central\Hotel;
use App\Models\Central\Invoice;
use App\Tenancy\HotelManager;
use App\Vendor\Billing;
use App\Vendor\CsvExport;
use App\Vendor\Onboarding;
use App\Vendor\Resources\Hotels\HotelResource;
use App\Vendor\Widgets\HotelListStats;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
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
            CsvExport::action('hotel', fn () => Hotel::query()->with('plan'), [
                'Kode' => 'slug', 'Nama' => 'name', 'Kota' => 'city', 'PIC' => 'contact_name', 'Telepon' => 'contact_phone', 'Email' => 'contact_email',
                'Paket' => 'plan.name', 'Kuota TV' => 'tv_quota', 'Sewa/bulan' => fn ($h) => $h->monthlyPrice(), 'Siklus (bln)' => 'billing_months',
                'Aktif sejak' => fn ($h) => $h->activated_at?->toDateString(), 'Sewa sampai' => fn ($h) => $h->service_expires_at?->setTimezone('Asia/Jakarta')->toDateString(),
                'Status' => 'status', 'Sumber' => 'source', 'Alasan berhenti' => 'churn_reason',
            ]),
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
                    Step::make('Konten')->icon(Heroicon::OutlinedSquare3Stack3d)->description('Template awal')->schema([
                        Select::make('template_hotel_id')->label('Salin konten dari hotel')->prefixIcon(Heroicon::OutlinedDocumentDuplicate)
                            ->options(fn () => Hotel::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id'))->placeholder('Mulai kosong')->live(),
                        CheckboxList::make('template_parts')->label('Yang disalin')->options(collect(HotelManager::CONTENT_PARTS)->map(fn ($p) => $p['label'])->all())
                            ->default(array_keys(HotelManager::CONTENT_PARTS))->columns(2)->visible(fn (Get $get) => filled($get('template_hotel_id'))),
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
                        $r = Onboarding::create($data);
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('Hotel gagal dibuat')->body($e->getMessage())->danger()->persistent()->send();
                        throw new Halt;
                    }
                    Notification::make()->title("Hotel {$r['hotel']->name} siap")
                        ->body("Login CMS: Kode Hotel {$r['hotel']->slug} · {$data['admin_email']} · kata sandi sementara: {$r['password']} (catat sekarang; admin wajib aktifkan verifikasi dua langkah). {$r['note']}")
                        ->success()->persistent()->send();

                    return $r['hotel'];
                })
                ->successNotification(null),
        ];
    }
}
