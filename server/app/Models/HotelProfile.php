<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Tenancy\HotelManager;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class HotelProfile extends Model
{
    /** Idle time before the TV screensaver (minutes). 0 = keep each TV's own setting. */
    public const SCREENSAVER_OPTIONS = [5 => '5 menit', 10 => '10 menit', 15 => '15 menit', 30 => '30 menit', 60 => '1 jam', 120 => '2 jam', 0 => 'Ikuti pengaturan TV'];

    /** TV designs (keys match TvThemes in the TV app). */
    public const TV_THEMES = [
        'ocean' => ['name' => 'Samudra', 'style' => 'Sinematik', 'desc' => 'Foto penuh dengan bilah menu di bawah. Desain bawaan.', 'colors' => ['#061A2B', '#38C6D9', '#F2F8FB']],
        'royal' => ['name' => 'Emas Klasik', 'style' => 'Grand Lobby', 'desc' => 'Menu samping berlapis emas, judul besar mengikuti pilihan tamu.', 'colors' => ['#0B0906', '#D9B56A', '#F7F1E3']],
        'tropical' => ['name' => 'Tropis', 'style' => 'Kartu Daun', 'desc' => 'Kartu berbentuk daun yang melebar saat dipilih remote.', 'colors' => ['#0A1F18', '#8FD694', '#F3F8EF']],
        'sunset' => ['name' => 'Senja', 'style' => 'Roda Panorama', 'desc' => 'Jam besar di tengah, menu berputar seperti roda di cakrawala.', 'colors' => ['#221110', '#F2A65A', '#FBF2E8']],
        'heritage' => ['name' => 'Nusantara', 'style' => 'Gapura Batik', 'desc' => 'Jendela foto berbentuk gapura di atas batik nila, menu seperti candi.', 'colors' => ['#171A45', '#E0A93F', '#F6EEE2']],
        'sunda' => ['name' => 'Daerah', 'style' => 'Pasundan', 'desc' => 'Budaya Sunda: atap Julang Ngapak, kujang, bilik bambu, dan angklung yang bergetar saat dipilih.', 'colors' => ['#2A1A0E', '#E2B04A', '#4F7A3A']],
    ];

    use Auditable;

    protected $guarded = ['id'];

    protected $hidden = ['wifi_password', 'technician_pin', 'adult_pin'];

    protected function casts(): array
    {
        return [
            'wifi_password' => 'encrypted',
            'technician_pin' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'home_music_enabled' => 'boolean',
            'home_music_volume' => 'integer',
            'screensaver_minutes' => 'integer',
            'pms_checklist' => 'array',
            'tv_start_volume' => 'integer',
            'show_hdmi_inputs' => 'boolean',
            'adult_pin' => 'array',
            'epg_synced_at' => 'datetime',
            'service_expires_at' => 'datetime',
            'service_released_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['name' => 'Hotel']);
    }

    /**
     * PINs are stored as PBKDF2-SHA256 so the TV can also verify them offline
     * (the plain PIN is never stored or sent).
     */
    public static function hashPin(string $pin): array
    {
        $salt = random_bytes(16);
        $iterations = 120_000;

        return [
            'algo' => 'pbkdf2-sha256',
            'iterations' => $iterations,
            'salt' => base64_encode($salt),
            'hash' => base64_encode(hash_pbkdf2('sha256', $pin, $salt, $iterations, 32, true)),
        ];
    }

    public static function pinMatches(mixed $stored, string $pin): bool
    {
        if (! is_array($stored) || ($stored['algo'] ?? null) !== 'pbkdf2-sha256') {
            return false;
        }

        $calc = hash_pbkdf2('sha256', $pin, base64_decode($stored['salt']), (int) $stored['iterations'], 32, true);

        return hash_equals(base64_decode($stored['hash']), $calc);
    }

    public function setTechnicianPin(string $pin): void
    {
        $this->technician_pin = self::hashPin($pin);
    }

    public function verifyTechnicianPin(string $pin): bool
    {
        return self::pinMatches($this->technician_pin, $pin);
    }

    /** Days before the end date when the CMS starts warning. */
    public const SERVICE_WARN_DAYS = 14;

    /** "active" | "expiring" | "released" (released manually or the service period has ended). */
    public function serviceStatus(): string
    {
        // Multi-hotel: the rental period lives in the central hotel record (set by Sijalucom); the hotel's own
        // "Lepaskan semua TV" (service_released_at here) still releases its TVs.
        if ($hotel = app(HotelManager::class)->current()) {
            $central = $hotel->serviceStatus(self::SERVICE_WARN_DAYS);

            return $this->service_released_at !== null || $central === 'released' ? 'released' : $central;
        }

        return match (true) {
            $this->service_released_at !== null => 'released',
            $this->service_expires_at !== null && $this->service_expires_at->isPast() => 'released',
            $this->service_expires_at !== null && $this->service_expires_at->lte(now()->addDays(self::SERVICE_WARN_DAYS)) => 'expiring',
            default => 'active',
        };
    }

    /** Payload sent to TVs in bootstrap and every heartbeat. */
    /** End of the service period: the central rental record (multi-hotel), else this hotel's own date. */
    public function serviceExpiry(): ?CarbonInterface
    {
        return app(HotelManager::class)->current()?->service_expires_at ?? $this->service_expires_at;
    }

    public function servicePayload(): array
    {
        return ['status' => $this->serviceStatus(), 'expires_at' => $this->serviceExpiry()?->toIso8601String()];
    }

    /** XMLTV sources, one per line. */
    public function epgUrlList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->epg_urls)), fn ($u) => str_starts_with($u, 'http')));
    }

    public function reportEmailList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $this->report_emails)), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }
}
