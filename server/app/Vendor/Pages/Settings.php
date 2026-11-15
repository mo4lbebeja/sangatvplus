<?php

namespace App\Vendor\Pages;

use App\Models\Central\VendorActivity;
use App\Models\Central\VendorSetting;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\WhatsApp;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    use VendorAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static ?string $title = 'Pengaturan';

    protected static ?string $slug = 'pengaturan';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static function area(): string
    {
        return 'owner';
    }

    public function mount(): void
    {
        $values = VendorSetting::allSettings();
        foreach (VendorSetting::SECRETS as $k) {
            $values[$k] = null; // never sent to the browser
        }
        $this->form->fill($values);
    }

    private static function secretHint(string $key): string
    {
        return filled(VendorSetting::read($key)) ? 'Tersimpan (terenkripsi). Kosongkan agar tidak berubah.' : 'Belum diisi.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make('Perusahaan')->icon(Heroicon::OutlinedBuildingStorefront)->columns(2)->schema([
                    TextInput::make('company_name')->label('Nama perusahaan')->prefixIcon(Heroicon::OutlinedBuildingStorefront)->required()->maxLength(120),
                    TextInput::make('company_email')->label('Email')->prefixIcon(Heroicon::OutlinedEnvelope)->email(),
                    TextInput::make('company_phone')->label('Telepon / WhatsApp')->prefixIcon(Heroicon::OutlinedPhone)->maxLength(40),
                    TextInput::make('company_npwp')->label('NPWP')->prefixIcon(Heroicon::OutlinedIdentification)->maxLength(32),
                    Textarea::make('company_address')->label('Alamat (kop tagihan)')->rows(2)->columnSpanFull(),
                ]),
                Tab::make('Tagihan & penagihan')->icon(Heroicon::OutlinedDocumentText)->columns(2)->schema([
                    TextInput::make('invoice_prefix')->label('Awalan nomor tagihan')->prefixIcon(Heroicon::OutlinedHashtag)->required()->maxLength(12)->helperText('Nomor: INV/202611/0001'),
                    TextInput::make('tax_percent')->label('PPN')->prefixIcon(Heroicon::OutlinedReceiptPercent)->numeric()->suffix('%')->minValue(0)->maxValue(100)->helperText('0 bila belum PKP.'),
                    TextInput::make('due_days')->label('Jatuh tempo (hari setelah terbit)')->prefixIcon(Heroicon::OutlinedCalendar)->numeric()->minValue(0)->maxValue(60)->required(),
                    TextInput::make('invoice_days_before')->label('Tagihan perpanjangan dibuat H-')->prefixIcon(Heroicon::OutlinedClock)->numeric()->minValue(1)->maxValue(60)->required(),
                    TextInput::make('reminder_days')->label('Pengingat bayar ke hotel (hari)')->prefixIcon(Heroicon::OutlinedBellAlert)
                        ->helperText('Positif = sebelum jatuh tempo, 0 = hari H, negatif = sesudah. Contoh: 7,3,0,-3,-7')->regex('/^\s*-?\d+(\s*,\s*-?\d+)*\s*$/'),
                    TextInput::make('suspend_after_days')->label('Tangguhkan otomatis bila telat (hari)')->prefixIcon(Heroicon::OutlinedNoSymbol)->numeric()->minValue(0)->maxValue(90)
                        ->helperText('0 = mati. Hotel aktif kembali otomatis setelah membayar.'),
                    Textarea::make('bank_accounts')->label('Rekening pembayaran (transfer manual)')->rows(3)->placeholder('BCA 1234567890 a.n. Sijalucom')->columnSpanFull(),
                    Textarea::make('invoice_footer')->label('Catatan kaki tagihan')->rows(2)->columnSpanFull(),
                ]),
                Tab::make('Integrasi')->icon(Heroicon::OutlinedPuzzlePiece)->schema([
                    Section::make('Pembayaran online')->icon(Heroicon::OutlinedCreditCard)->description('Hotel membayar lewat QRIS / Virtual Account / e-wallet; tagihan lunas & sewa diperpanjang otomatis.')->columns(2)->schema([
                        Select::make('payment_gateway')->label('Penyedia aktif')->options(['none' => 'Tidak aktif (transfer manual)', 'xendit' => 'Xendit', 'midtrans' => 'Midtrans'])->required()->live()->columnSpanFull(),
                        TextInput::make('xendit_secret_key')->label('Xendit — Secret API key')->password()->revealable()->helperText(fn () => self::secretHint('xendit_secret_key')),
                        TextInput::make('xendit_callback_token')->label('Xendit — Callback verification token')->password()->revealable()->helperText(fn () => self::secretHint('xendit_callback_token')),
                        Placeholder::make('xendit_hook')->label('Xendit — URL webhook "Invoice paid"')->content(fn () => new HtmlString('<code>'.e(route('api.payments.xendit')).'</code>')),
                        Placeholder::make('spacer1')->hiddenLabel()->content(''),
                        TextInput::make('midtrans_server_key')->label('Midtrans — Server key')->password()->revealable()->helperText(fn () => self::secretHint('midtrans_server_key')),
                        Toggle::make('midtrans_production')->label('Midtrans mode produksi (mati = sandbox/uji)')->inline(false),
                        Placeholder::make('midtrans_hook')->label('Midtrans — Payment notification URL')->content(fn () => new HtmlString('<code>'.e(route('api.payments.midtrans')).'</code>'))->columnSpanFull(),
                    ]),
                    Section::make('WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftRight)->description('Pengingat tagihan & pemberitahuan ke nomor PIC hotel.')->columns(2)->schema([
                        Select::make('whatsapp_provider')->label('Penyedia')->options(['none' => 'Tidak aktif', 'fonnte' => 'Fonnte', 'wablas' => 'Wablas', 'custom' => 'HTTP kustom (POST JSON phone, message)'])->required()->live(),
                        TextInput::make('whatsapp_token')->label('Token / API key')->password()->revealable()->helperText(fn () => self::secretHint('whatsapp_token')),
                        TextInput::make('whatsapp_url')->label('URL server (Wablas: domain server; kustom: endpoint)')->url()->columnSpanFull()
                            ->visible(fn ($get) => in_array($get('whatsapp_provider'), ['wablas', 'custom'], true)),
                    ]),
                ]),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testWa')->label('Uji WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftRight)->color('gray')
                ->schema([TextInput::make('phone')->label('Nomor tujuan')->tel()->required()->default(fn () => VendorSetting::read('company_phone'))])
                ->action(function (array $data) {
                    $ok = WhatsApp::send($data['phone'], 'Uji pesan dari Sijalucom Console — SangaTV Plus.');
                    Notification::make()->title($ok ? 'Pesan WhatsApp terkirim' : 'Gagal mengirim (cek penyedia & token)')->{$ok ? 'success' : 'danger'}()->send();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Simpan')->icon(Heroicon::OutlinedCheck)->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['tax_percent'] = (float) ($data['tax_percent'] ?? 0);
        $data['due_days'] = (int) $data['due_days'];
        $data['invoice_days_before'] = (int) $data['invoice_days_before'];
        $data['suspend_after_days'] = (int) ($data['suspend_after_days'] ?? 0);
        $data['midtrans_production'] = (bool) ($data['midtrans_production'] ?? false);
        unset($data['xendit_hook'], $data['midtrans_hook'], $data['spacer1']);
        VendorSetting::put($data);
        VendorActivity::record('settings.updated', 'Pengaturan perusahaan & tagihan diubah');
        foreach (VendorSetting::SECRETS as $k) {
            $this->data[$k] = null;
        }
        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }
}
