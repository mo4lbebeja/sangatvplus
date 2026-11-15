<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\HotelProfile;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/** Overview: vendor checklist (show/hide), API docs, and the vendor document download. */
class PmsIntegrationPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Integrasi PMS';

    protected static ?string $navigationLabel = 'Panduan & Checklist';

    protected static ?string $title = 'Integrasi PMS / BMS';

    protected static ?string $slug = 'integrasi-pms';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.pms-integration';

    /** @var array<string, array{done: bool, note: string}> */
    public array $checklist = [];

    public static function canAccess(): bool
    {
        $u = Auth::user();

        return $u instanceof User && $u->is_active && ($u->isAdmin() || $u->hasRole(UserRole::It));
    }

    /** @return array<string, array{q: string, hint: string}> */
    public static function questions(): array
    {
        return [
            'rest' => ['q' => 'Apakah PMS bisa mengirim HTTP/REST (webhook) ke sistem luar?', 'hint' => 'Paling penting. Bila hanya FIAS (protokol TV/PBX hotel lewat TCP), perlu penghubung (middleware) di server hotel.'],
            'events' => ['q' => 'Kejadian apa saja yang bisa dikirim?', 'hint' => 'Minimal: check-in, check-out, pindah kamar, ubah nama tamu.'],
            'format' => ['q' => 'Format nomor kamar dan sapaan yang dipakai PMS?', 'hint' => 'Mis. 101 / 0101 / A-101; MR, MRS, BPK, IBU. Nomor kamar harus sama dengan di CMS.'],
            'retry' => ['q' => 'Apakah PMS mengirim ulang saat gagal (retry) dan bisa sinkron penuh?', 'hint' => 'Sinkron penuh = daftar semua tamu menginap; dipakai saat pemasangan awal & 1× sehari.'],
            'source' => ['q' => 'Dari mana kiriman berasal (server PMS di hotel atau cloud) dan IP-nya?', 'hint' => 'IP dipakai untuk pembatasan di kunci API.'],
            'auth' => ['q' => 'Apakah PMS bisa membuat tanda tangan HMAC-SHA256?', 'hint' => 'Bila tidak, gunakan mode Bearer + pembatasan IP.'],
            'clock' => ['q' => 'Apakah jam server PMS sinkron (NTP)?', 'hint' => 'Selisih waktu > 5 menit membuat kiriman ditolak (stale_timestamp).'],
            'cost' => ['q' => 'Biaya/lisensi modul interface, kontak teknis, dan jadwal pemasangan?', 'hint' => 'Biasanya modul interface PMS berbayar.'],
            'sandbox' => ['q' => 'Apakah vendor punya lingkungan uji (sandbox) sebelum live?', 'hint' => 'Bisa diuji ke production dengan kunci API terpisah dan kamar uji.'],
        ];
    }

    public function mount(): void
    {
        $saved = (array) (HotelProfile::current()->pms_checklist ?? []);
        foreach (array_keys(self::questions()) as $k) {
            $this->checklist[$k] = ['done' => (bool) ($saved[$k]['done'] ?? false), 'note' => (string) ($saved[$k]['note'] ?? '')];
        }
    }

    /** Auto-save every change to the checklist. */
    public function updatedChecklist(): void
    {
        $clean = [];
        foreach (array_keys(self::questions()) as $k) {
            $clean[$k] = ['done' => (bool) ($this->checklist[$k]['done'] ?? false), 'note' => Str::limit((string) ($this->checklist[$k]['note'] ?? ''), 1000, '')];
        }
        $hotel = HotelProfile::current();
        $hotel->pms_checklist = $clean;
        $hotel->save();

        Notification::make()->title('Checklist tersimpan')->success()->duration(1500)->send();
    }

    public function doneCount(): int
    {
        return collect($this->checklist)->where('done', true)->count();
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/api/pms/v1';
    }

    public static function vendorMarkdown(): string
    {
        return view('pms.vendor-doc', ['base' => self::baseUrl(), 'hotel' => HotelProfile::current()->name])->render();
    }

    public function docHtml(): HtmlString
    {
        return new HtmlString(Str::markdown(self::vendorMarkdown(), ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }
}
