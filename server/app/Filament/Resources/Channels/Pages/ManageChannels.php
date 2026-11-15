<?php

namespace App\Filament\Resources\Channels\Pages;

use App\Filament\Resources\Channels\ChannelResource;
use App\Models\ChannelProvider;
use App\Models\HotelProfile;
use App\Services\ChannelHealthChecker;
use App\Services\EpgImporter;
use App\Services\M3uImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ManageChannels extends ManageRecords
{
    protected static string $resource = ChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('healthCheck')
                ->label('Cek kesehatan sekarang')
                ->icon(Heroicon::OutlinedHeart)
                ->color('gray')
                ->visible(fn () => ChannelResource::canCreate())
                ->action(function (ChannelHealthChecker $checker) {
                    $n = $checker->checkAll();
                    Notification::make()
                        ->title($n['error'] ? "{$n['error']} channel gagal" : 'Semua channel normal')
                        ->body("Normal {$n['ok']} · Gagal {$n['error']} · Tidak dicek (multicast lokal) {$n['skipped']}")
                        ->{$n['error'] ? 'warning' : 'success'}()->send();
                }),
            Action::make('syncEpg')
                ->label('Sinkron EPG')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->visible(fn () => ChannelResource::canCreate())
                ->modalHeading('Sinkron jadwal acara (EPG)')
                ->modalDescription(fn () => ($s = HotelProfile::current()->epg_status) ? 'Terakhir: '.$s : 'Mengambil jadwal dari sumber XMLTV di Profil Hotel → Channel TV.')
                ->modalIcon(Heroicon::OutlinedCalendarDays)
                ->modalSubmitActionLabel('Sinkron sekarang')
                ->schema([
                    Toggle::make('auto_match')->label('Cocokkan ID EPG otomatis untuk channel yang masih kosong')->default(true)
                        ->helperText('Berdasarkan nama channel. Periksa hasilnya di kolom EPG.'),
                ])
                ->action(function (array $data, EpgImporter $importer) {
                    try {
                        $r = $importer->sync();
                        $matched = 0;
                        if ($data['auto_match'] && ($matched = EpgImporter::autoMatch()) > 0) {
                            $r = $importer->sync();
                        }
                        Notification::make()
                            ->title("{$r['channels']} channel punya jadwal acara")
                            ->body(($matched ? "{$matched} ID EPG dicocokkan otomatis · " : '')."{$r['programmes']} acara · ".implode(' · ', $r['sources']))
                            ->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Sinkron EPG gagal')->body($e->getMessage())->danger()->send();
                    }
                }),
            $this->importAction(),
            CreateAction::make()->icon(Heroicon::OutlinedPlus),
        ];
    }

    private function importAction(): Action
    {
        return Action::make('importM3u')
            ->label('Impor M3U')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->visible(fn () => ChannelResource::canCreate())
            ->modalHeading('Impor channel dari playlist M3U')
            ->modalDescription('Nomor, nama, kategori (group-title) dan logo diambil dari playlist. Channel yang URL-nya sudah ada dilewati.')
            ->modalIcon(Heroicon::OutlinedArrowUpTray)
            ->modalSubmitActionLabel('Impor')
            ->schema([
                Radio::make('mode')->label('Sumber playlist')->inline()->live()->default('file')
                    ->options(['file' => 'Unggah file', 'url' => 'URL playlist']),
                FileUpload::make('file')->label('File .m3u / .m3u8')
                    ->disk('local')->directory('imports')->visibility('private')
                    ->acceptedFileTypes(['audio/x-mpegurl', 'audio/mpegurl', 'application/x-mpegurl', 'application/vnd.apple.mpegurl', 'text/plain', 'application/octet-stream'])
                    ->maxSize(M3uImporter::MAX_PLAYLIST_BYTES / 1024)
                    ->visible(fn (Get $get) => $get('mode') === 'file')
                    ->required(fn (Get $get) => $get('mode') === 'file'),
                TextInput::make('url')->label('URL playlist')->url()->prefixIcon(Heroicon::OutlinedLink)
                    ->placeholder('https://penyedia.example/playlist.m3u8')
                    ->visible(fn (Get $get) => $get('mode') === 'url')
                    ->required(fn (Get $get) => $get('mode') === 'url'),
                Select::make('origin')->label('Asal siaran (untuk stream HLS)')->required()->default('provider')->live()
                    ->options(['provider' => 'Penyedia (internet)', 'local' => 'Server lokal hotel'])
                    ->helperText('udp:// otomatis menjadi multicast lokal, .mpd menjadi DASH.'),
                Select::make('provider_id')->label('Penyedia')->prefixIcon(Heroicon::OutlinedGlobeAlt)
                    ->options(fn () => ChannelProvider::query()->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn (Get $get) => $get('origin') === 'provider')
                    ->helperText('Opsional. Dipakai bila penyedia memerlukan token URL.'),
                TextInput::make('start_number')->label('Nomor awal (bila playlist tanpa tvg-chno)')
                    ->numeric()->minValue(1)->maxValue(9999)->prefixIcon(Heroicon::OutlinedHashtag),
                Toggle::make('download_logos')->label('Unduh logo ke server')->default(true)
                    ->helperText('TV mengambil logo dari server hotel, bukan dari internet.'),
                Toggle::make('activate')->label('Langsung tampil di TV')->default(false)
                    ->helperText('Biarkan mati untuk memeriksa channel dulu (lisensi, kualitas).'),
            ])
            ->action(function (array $data, M3uImporter $importer) {
                try {
                    if ($data['mode'] === 'url') {
                        $content = $importer->fetch($data['url']);
                    } else {
                        $path = is_array($data['file']) ? reset($data['file']) : $data['file'];
                        $content = Storage::disk('local')->get($path);
                        Storage::disk('local')->delete($path);
                    }

                    $entries = $importer->parse((string) $content);
                    if ($entries === []) {
                        Notification::make()->title('Tidak ada channel yang dikenali')->body('Pastikan file berformat M3U dengan baris #EXTINF.')->warning()->send();

                        return;
                    }

                    $r = $importer->import($entries, [
                        'origin' => $data['origin'],
                        'provider_id' => $data['provider_id'] ?? null,
                        'start_number' => $data['start_number'] ?? null,
                        'activate' => (bool) $data['activate'],
                        'download_logos' => (bool) $data['download_logos'],
                    ]);

                    Notification::make()
                        ->title("{$r['created']} channel diimpor")
                        ->body("{$r['skipped']} dilewati (sudah ada) · {$r['logos']} logo diunduh".($data['activate'] ? '' : ' · channel baru masih nonaktif'))
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()->title('Impor gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
