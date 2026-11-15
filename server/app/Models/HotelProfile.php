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

    protected $hidden = ['wifi_password', 'technician_pin'];

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
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['name' => 'Hotel']);
    }

    /**
     * Store the technician PIN as PBKDF2-SHA256 so the TV can also verify
     * it offline (the plain PIN is never stored or sent).
     */
    public function setTechnicianPin(string $pin): void
    {
        $salt = random_bytes(16);
        $iterations = 120_000;

        $this->technician_pin = [
            'algo' => 'pbkdf2-sha256',
            'iterations' => $iterations,
            'salt' => base64_encode($salt),
            'hash' => base64_encode(hash_pbkdf2('sha256', $pin, $salt, $iterations, 32, true)),
        ];
    }

    public function verifyTechnicianPin(string $pin): bool
    {
        $p = $this->technician_pin;
        if (! is_array($p) || ($p['algo'] ?? null) !== 'pbkdf2-sha256') {
            return false;
        }

        $calc = hash_pbkdf2('sha256', $pin, base64_decode($p['salt']), (int) $p['iterations'], 32, true);

        return hash_equals(base64_decode($p['hash']), $calc);
    }
}
