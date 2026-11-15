<?php

namespace App\Vendor\Pages;

use App\Models\Central\VendorActivity;
use App\Models\Central\VendorSetting;
use App\Vendor\Concerns\VendorAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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

    protected static ?string $title = 'Pengaturan Perusahaan & Tagihan';

    protected static ?string $slug = 'pengaturan';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static function area(): string
    {
        return 'owner';
    }

    public function mount(): void
    {
        $this->form->fill(VendorSetting::allSettings());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Profil perusahaan')->description('Tampil di kop tagihan.')->icon(Heroicon::OutlinedBuildingStorefront)->columns(2)->schema([
                TextInput::make('company_name')->label('Nama perusahaan')->required()->maxLength(120),
                TextInput::make('company_email')->label('Email')->email(),
                TextInput::make('company_phone')->label('Telepon / WhatsApp')->maxLength(40),
                TextInput::make('company_npwp')->label('NPWP')->maxLength(32),
                Textarea::make('company_address')->label('Alamat')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Tagihan')->icon(Heroicon::OutlinedDocumentText)->columns(2)->schema([
                TextInput::make('invoice_prefix')->label('Awalan nomor')->required()->maxLength(12)->helperText('Nomor: INV/202611/0001'),
                TextInput::make('tax_percent')->label('PPN')->numeric()->suffix('%')->minValue(0)->maxValue(100)->helperText('0 bila belum PKP.'),
                TextInput::make('due_days')->label('Jatuh tempo (hari setelah terbit)')->numeric()->minValue(0)->maxValue(60)->required(),
                TextInput::make('invoice_days_before')->label('Tagihan otomatis dibuat H- (hari sebelum sewa habis)')->numeric()->minValue(1)->maxValue(60)->required(),
                Textarea::make('bank_accounts')->label('Rekening pembayaran')->rows(3)->placeholder("BCA 1234567890 a.n. Sijalucom\nQRIS tersedia")->columnSpanFull(),
                Textarea::make('invoice_footer')->label('Catatan kaki tagihan')->rows(2)->columnSpanFull(),
            ]),
        ]);
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
        VendorSetting::put($data);
        VendorActivity::record('settings.updated', 'Pengaturan perusahaan & tagihan diubah');
        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }
}
