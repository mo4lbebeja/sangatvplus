<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class HotelProfile extends Model
{
    /** Idle time before the TV screensaver (minutes). 0 = keep each TV's own setting. */
    public const SCREENSAVER_OPTIONS = [5 => '5 menit', 10 => '10 menit', 15 => '15 menit', 30 => '30 menit', 60 => '1 jam', 120 => '2 jam', 0 => 'Ikuti pengaturan TV'];

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
        return match (true) {
            $this->service_released_at !== null => 'released',
            $this->service_expires_at !== null && $this->service_expires_at->isPast() => 'released',
            $this->service_expires_at !== null && $this->service_expires_at->lte(now()->addDays(self::SERVICE_WARN_DAYS)) => 'expiring',
            default => 'active',
        };
    }

    /** Payload sent to TVs in bootstrap and every heartbeat. */
    public function servicePayload(): array
    {
        return ['status' => $this->serviceStatus(), 'expires_at' => $this->service_expires_at?->toIso8601String()];
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
