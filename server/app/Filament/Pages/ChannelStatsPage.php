<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Mail\ChannelUptimeReport;
use App\Models\HotelProfile;
use App\Models\User;
use App\Services\ChannelStats;
use App\Services\Notifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/** Viewing statistics (which channels are worth their licence) and per-channel uptime. */
class ChannelStatsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Siaran';

    protected static ?string $navigationLabel = 'Statistik & Uptime';

    protected static ?string $title = 'Statistik & Uptime Channel';

    protected static ?string $slug = 'statistik-channel';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.channel-stats';

    public int $days = 7;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->is_active && ($user->isAdmin() || $user->hasRole(UserRole::It));
    }

    public function getSubheading(): ?string
    {
        return 'Tontonan dicatat per kamar (tanpa nama tamu), sesi di bawah 10 detik diabaikan. Uptime dari cek server tiap 10 menit.';
    }

    public function updatedDays(): void
    {
        $this->days = in_array($this->days, [1, 7, 30, 90], true) ? $this->days : 7;
    }

    public function stats(): ChannelStats
    {
        return new ChannelStats($this->days, HotelProfile::current()->timezone ?: 'Asia/Jakarta');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testAlert')
                ->label('Kirim notifikasi uji')
                ->icon(Heroicon::OutlinedBellAlert)
                ->color('gray')
                ->modalHeading('Uji peringatan otomatis')
                ->modalDescription(function () {
                    $n = app(Notifier::class);

                    return 'Telegram: '.($n->telegramConfigured() ? 'terhubung' : 'belum diatur (TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID)')
                        .' · Email: '.($n->emailConfigured() ? implode(', ', $n->emailRecipients()) : 'belum diatur (SMTP & email penerima)');
                })
                ->modalSubmitActionLabel('Kirim uji')
                ->action(function (Notifier $notifier) {
                    $r = $notifier->send('Uji peringatan', 'Notifikasi SangaTV Plus berfungsi. Peringatan channel gagal & TV tidak terhubung akan dikirim ke sini.');
                    $sent = array_keys(array_filter($r));
                    Notification::make()->title($sent ? 'Terkirim ke '.implode(' & ', $sent) : 'Belum ada saluran yang terhubung')
                        ->{$sent ? 'success' : 'warning'}()->send();
                }),
            Action::make('sendReport')
                ->label('Kirim laporan ke email')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Kirim laporan uptime sekarang?')
                ->modalDescription(fn () => ($to = HotelProfile::current()->reportEmailList())
                    ? 'Ke: '.implode(', ', $to).' (periode '.$this->days.' hari).'
                    : 'Belum ada email penerima. Isi di Profil Hotel → Channel TV.')
                ->action(function () {
                    $to = HotelProfile::current()->reportEmailList();
                    if ($to === []) {
                        Notification::make()->title('Belum ada email penerima')->warning()->send();

                        return;
                    }
                    try {
                        Mail::to($to)->send(new ChannelUptimeReport($this->days));
                        Notification::make()->title('Laporan dikirim')->body(implode(', ', $to))->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Email gagal dikirim')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
