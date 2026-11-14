<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Device extends Model
{
    use Auditable, HasApiTokens, HasFactory;

    protected $guarded = ['id'];

    /** Heartbeat fields change constantly; don't flood the audit log. */
    protected array $auditExclude = ['last_seen_at', 'last_ip'];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'kiosk_enabled' => 'boolean',
            'device_owner' => 'boolean',
            'is_default_home' => 'boolean',
            'can_draw_overlays' => 'boolean',
            'can_install_updates' => 'boolean',
            'stock_launcher_active' => 'boolean',
            'provisioned_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    /**
     * 'ok'      → SangaTV Plus opens first on power-on,
     * 'partial' → opens after the stock launcher (boot fallback only),
     * 'no'      → needs setup, null → not reported yet.
     */
    public function autoStartState(): ?string
    {
        if ($this->is_default_home === null && ! $this->device_owner) {
            return null;
        }
        if ($this->device_owner || ($this->is_default_home && ! $this->stock_launcher_active)) {
            return 'ok';
        }
        if ($this->is_default_home || $this->can_draw_overlays) {
            return 'partial';
        }

        return 'no';
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(3));
    }

    /** Revoke: invalidate all tokens immediately. */
    public function revoke(): void
    {
        $this->tokens()->delete();
        $this->update(['status' => DeviceStatus::Revoked]);
    }
}
