<?php

namespace App\Filament\Pages;

use App\Models\Channel;
use App\Models\HotelProfile;
use App\Models\User;
use App\Services\WeatherService;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class HotelProfilePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?string $navigationLabel = 'Profil Hotel';

    protected static ?string $title = 'Profil Hotel';

    protected static ?string $slug = 'hotel-profile';

    protected static ?int $navigationSort = 30;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->is_active && $user->isAdmin();
    }

    public function mount(): void
    {
        $hotel = HotelProfile::current();

        // wifi_password is a hidden attribute; fill it explicitly. The PIN is write-only.
        $this->form->fill([
            ...$hotel->only(['name', 'tagline', 'about', 'logo_path', 'address', 'reception_phone', 'reception_extension',
                'wifi_ssid', 'check_in_time', 'check_out_time', 'check_out_info', 'timezone', 'latitude', 'longitude', 'screensaver_minutes', 'tv_start_volume', 'tv_start_channel_id', 'show_hdmi_inputs', 'epg_urls', 'report_emails', 'bmkg_adm4']),
            'wifi_password' => $hotel->wifi_password,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identitas')->icon(Heroicon::OutlinedBuildingOffice2)->description('Nama dan logo tampil di Beranda TV.')->columns(2)->schema([
                    TextInput::make('name')->label('Nama hotel')->required()->maxLength(80),
                    TextInput::make('tagline')->maxLength(120),
                    FileUpload::make('logo_path')->label('Logo (PNG transparan)')->image()->disk('public')->directory('branding')->maxSize(512)
                        // Fixed small preview height: fits the row, so nothing jumps when the preview loads.
                        ->imagePreviewHeight('96'),
                    Textarea::make('about')->label('Tentang hotel')->rows(4)->maxLength(2000),
                    TextInput::make('address')->label('Alamat')->maxLength(255)->columnSpanFull(),
                ]),
                Section::make('Kontak & Wi-Fi')->icon(Heroicon::OutlinedWifi)->description('Kartu Wi-Fi & resepsionis di layar Info Hotel.')->columns(2)->schema([
                    TextInput::make('reception_phone')->label('Telepon resepsionis')->prefixIcon(Heroicon::OutlinedPhone)->tel()->maxLength(32),
                    TextInput::make('reception_extension')->label('Ext. dari kamar')->maxLength(16),
                    TextInput::make('wifi_ssid')->label('SSID Wi-Fi tamu')->prefixIcon(Heroicon::OutlinedWifi)->maxLength(64),
                    TextInput::make('wifi_password')->label('Password Wi-Fi tamu')->prefixIcon(Heroicon::OutlinedLockClosed)->maxLength(64)
                        ->helperText('Ditampilkan ke tamu sebagai QR di TV.'),
                ]),
                Section::make('Check-in / Check-out')->icon(Heroicon::OutlinedClock)->columns(2)->schema([
                    TextInput::make('check_in_time')->label('Jam check-in')->required()->regex('/^\d{2}:\d{2}$/')->placeholder('14:00'),
                    TextInput::make('check_out_time')->label('Jam check-out')->required()->regex('/^\d{2}:\d{2}$/')->placeholder('12:00'),
                    Textarea::make('check_out_info')->label('Info check-out')->rows(3)->maxLength(1000)->columnSpanFull(),
                ]),
                Section::make('Layar TV')->icon(Heroicon::OutlinedMoon)->description('Berlaku untuk semua TV kamar.')->schema([
                    Select::make('screensaver_minutes')->label('Screensaver TV muncul setelah')->required()
                        ->options(HotelProfile::SCREENSAVER_OPTIONS)
                        ->helperText('Lama remote tidak disentuh sebelum screensaver bawaan TV tampil. Saat tamu menekan remote, SangaTV Plus kembali ke Beranda.'),
                    Select::make('tv_start_volume')->label('Volume saat TV dinyalakan')
                        ->options(collect([10, 15, 20, 25, 30, 40, 50])->mapWithKeys(fn ($v) => [$v => "{$v}%"])->all())
                        ->placeholder('Tidak diatur (ikuti TV)')
                        ->helperText('Mencegah TV menyala terlalu keras setelah tamu sebelumnya.'),
                    Select::make('tv_start_channel_id')->label('Channel pertama saat membuka TV')
                        ->options(fn () => Channel::query()->where('is_active', true)->orderBy('number')->get()->mapWithKeys(fn ($c) => [$c->id => $c->number.' · '.$c->name])->all())
                        ->placeholder('Channel terakhir yang ditonton')->searchable()
                        ->helperText('Berlaku saat menu TV dibuka pertama kali setelah TV dinyalakan.'),
                    Toggle::make('show_hdmi_inputs')->label('Tampilkan input HDMI di menu Aplikasi')
                        ->helperText('Tamu bisa menyambung laptop/konsol ke TV.'),
                ])->columns(2),
                Section::make('Channel TV')->icon(Heroicon::OutlinedTv)->description('Jadwal acara, kunci channel dewasa, dan laporan mingguan.')->columns(2)->schema([
                    Textarea::make('epg_urls')->label('Sumber jadwal acara (XMLTV)')->rows(3)->columnSpanFull()
                        ->placeholder("https://www.open-epg.com/files/indonesia1.xml\nhttps://epg.pw/xmltv/epg_ID.xml.gz")
                        ->helperText(fn () => 'Satu URL per baris (.xml / .xml.gz), urutan = prioritas. Diperbarui otomatis tiap 6 jam.'
                            .(($h = HotelProfile::current())->epg_synced_at ? ' Terakhir '.$h->epg_synced_at->diffForHumans().': '.$h->epg_status : '')),
                    TextInput::make('new_adult_pin')->label('PIN channel dewasa (4 digit)')->prefixIcon(Heroicon::OutlinedLockClosed)
                        ->password()->revealable()
                        ->helperText(fn () => HotelProfile::current()->adult_pin
                            ? 'PIN aktif. Isi untuk mengganti; ketik "hapus" untuk menyembunyikan channel dewasa.'
                            : 'Belum diatur: channel dewasa disembunyikan dari TV.')
                        ->rule(fn () => fn (string $attribute, mixed $value, \Closure $fail) => blank($value) || $value === 'hapus' || preg_match('/^\d{4}$/', (string) $value) ? null : $fail('PIN harus 4 digit.')),
                    Textarea::make('report_emails')->label('Email laporan uptime mingguan')->rows(2)
                        ->placeholder('it@hotel.com, gm@hotel.com')
                        ->helperText('Dikirim tiap Senin 07:00: uptime per channel dan channel paling banyak ditonton.'),
                ]),
                Section::make('Lokasi & cuaca')->icon(Heroicon::OutlinedMapPin)->description('Cuaca di Beranda TV dari prakiraan resmi BMKG (gratis, sumber "BMKG" selalu dicantumkan).')->columns(3)->schema([
                    TextInput::make('bmkg_adm4')->label('Kode wilayah BMKG (desa/kelurahan)')->placeholder('mis. 32.05.13.2001')
                        ->regex('/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/')->columnSpanFull()
                        ->helperText('Kode wilayah tingkat IV (Kemendagri) lokasi hotel. Cari nama desa/kelurahan di kodewilayah.id lalu salin kodenya. Kosongkan untuk menyembunyikan cuaca.'),
                    Select::make('timezone')->label('Zona waktu')->required()
                        ->options(['Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT']),
                    TextInput::make('latitude')->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('longitude')->numeric()->minValue(-180)->maxValue(180),
                ]),
                Section::make('PIN menu teknisi')->icon(Heroicon::OutlinedKey)->description('Membuka menu teknisi di TV (kombinasi tombol tersembunyi).')->schema([
                    TextInput::make('new_pin')->label('PIN baru (6 digit)')->prefixIcon(Heroicon::OutlinedKey)
                        ->password()->revealable()
                        ->regex('/^\d{6}$/')
                        ->notIn(['000000', '111111', '123456', '654321', '121212'])
                        ->helperText('Kosongkan bila tidak diubah. TV menerima PIN baru saat sinkron berikutnya.'),
                ]),
            ]);
    }

    public function getSubheading(): ?string
    {
        $h = HotelProfile::current();
        $until = $h->serviceExpiry()?->copy()->setTimezone($h->timezone ?: 'Asia/Jakarta')->translatedFormat('d F Y');

        return match ($h->serviceStatus()) {
            'released' => 'Layanan dilepas: TV sudah kembali ke layar utama bawaan.',
            'expiring' => "Layanan berlaku sampai {$until}.",
            default => $until ? "Layanan berlaku sampai {$until}." : 'Layanan aktif.',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportData')
                ->label('Ekspor data')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn () => route('hotel.export'))
                ->tooltip('Kamar, tamu, channel, fasilitas, konten, TV, statistik (ZIP berisi CSV). Rahasia tidak ikut.'),
            Action::make('releaseTvs')
                ->label('Lepaskan semua TV')
                ->icon(Heroicon::OutlinedPower)
                ->color('danger')
                ->visible(fn () => HotelProfile::current()->serviceStatus() !== 'released')
                ->modalHeading('Lepaskan semua TV dari SangaTV Plus?')
                ->modalDescription('Dalam ±1 menit setiap TV yang menyala berhenti menjadi layar utama, memulihkan pengaturannya, lalu kembali ke layar utama bawaan TV dan menawarkan penghapusan aplikasi. TV yang sedang mati menyusul saat dinyalakan. Bisa dibatalkan selama aplikasi belum dihapus dari TV.')
                ->modalIcon(Heroicon::OutlinedPower)
                ->schema([
                    TextInput::make('confirm')->label('Ketik LEPASKAN untuk konfirmasi')->required()->in(['LEPASKAN'])
                        ->validationMessages(['in' => 'Ketik LEPASKAN (huruf besar).']),
                ])
                ->modalSubmitActionLabel('Lepaskan semua TV')
                ->action(function () {
                    $h = HotelProfile::current();
                    $h->forceFill(['service_released_at' => now()])->save();
                    Audit::log('service.released', $h);
                    Notification::make()->title('Semua TV akan dilepas dalam ±1 menit')->warning()->send();
                }),
            Action::make('restoreService')
                ->label('Batalkan pelepasan')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn () => HotelProfile::current()->service_released_at !== null)
                ->requiresConfirmation()
                ->modalDescription('TV yang masih memiliki aplikasi SangaTV Plus akan kembali memakai layar SangaTV Plus dalam ±1 menit.')
                ->action(function () {
                    $h = HotelProfile::current();
                    $h->forceFill(['service_released_at' => null])->save();
                    Audit::log('service.restored', $h);
                    Notification::make()->title('Pelepasan dibatalkan')->body($h->serviceStatus() === 'released' ? 'Catatan: masa layanan sudah berakhir, TV tetap dilepas sampai diperpanjang.' : 'TV kembali ke SangaTV Plus dalam ±1 menit.')->success()->send();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan perubahan')->icon(Heroicon::OutlinedCheck)->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $hotel = HotelProfile::current();

        $pin = $data['new_pin'] ?? null;
        $adultPin = $data['new_adult_pin'] ?? null;
        unset($data['new_pin'], $data['new_adult_pin']);
        if ($adultPin === 'hapus') {
            $hotel->adult_pin = null;
        } elseif (filled($adultPin)) {
            $hotel->adult_pin = HotelProfile::hashPin($adultPin);
        }

        $hotel->fill($data);
        if (filled($pin)) {
            $hotel->setTechnicianPin($pin);
        }
        $hotel->save();

        if (filled($pin)) {
            Audit::log('technician.pin_changed', $hotel);
            $this->data['new_pin'] = null;
        }
        if (filled($adultPin)) {
            Audit::log($adultPin === 'hapus' ? 'adult_pin.removed' : 'adult_pin.changed', $hotel);
            $this->data['new_adult_pin'] = null;
        }

        if ($hotel->wasChanged('bmkg_adm4') && filled($hotel->bmkg_adm4)) {
            $loc = app(WeatherService::class)->bmkgLocation($hotel->bmkg_adm4);
            Notification::make()->title($loc ? "Cuaca BMKG: {$loc['desa']}, {$loc['kecamatan']}, {$loc['kotkab']}" : 'Kode wilayah BMKG tidak ditemukan')
                ->body($loc ? 'TV menampilkan cuaca lokasi ini dalam ±1 menit.' : 'Periksa kembali kodenya di kodewilayah.id.')
                ->{$loc ? 'success' : 'warning'}()->send();
        }

        Notification::make()->title('Profil hotel disimpan')->success()->send();
    }
}
