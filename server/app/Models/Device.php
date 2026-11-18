<?php

namespace App\Models;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\DeviceStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

class Device extends Model
{
    use Auditable, HasApiTokens, HasFactory;

    protected $guarded = ['id'];

    /** Heartbeat fields change constantly; don't flood the audit log. */
    protected array $auditExclude = ['last_seen_at', 'last_ip', 'local_ip'];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'service_released' => 'boolean',
            'hold_updates' => 'boolean',
            'kiosk_enabled' => 'boolean',
            'device_owner' => 'boolean',
            'is_default_home' => 'boolean',
            'can_draw_overlays' => 'boolean',
            'can_install_updates' => 'boolean',
            'stock_launcher_active' => 'boolean',
            'screen_on' => 'boolean',
            'screen_changed_at' => 'datetime',
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

    /** Active release newer than what this TV runs (same app package; old TVs report none = staging). */
    public function availableRelease(): ?AppRelease
    {
        return AppRelease::query()
            ->where('package_name', $this->app_package ?: AppRelease::DEFAULT_PACKAGE)
            ->where('is_active', true)
            ->where('version_code', '>', (int) $this->app_version_code)
            ->orderByDesc('version_code')
            ->first();
    }

    /** An update command is still on its way to this TV. */
    public function updateQueued(): bool
    {
        return $this->commands()->where('type', CommandType::CheckUpdate)
            ->where(fn ($query) => $query->where('status', CommandStatus::Pending)
                ->orWhere(fn ($query) => $query->where('status', CommandStatus::Delivered)->where('created_at', '>', now()->subMinutes(10))))
            ->exists();
    }

    /** Ask this TV to install the new version now. False when there is nothing to do (held, up to date, already queued). */
    public function queueUpdate(?int $userId = null): bool
    {
        if ($this->status !== DeviceStatus::Active || $this->hold_updates || ! $this->availableRelease() || $this->updateQueued()) {
            return false;
        }
        $this->commands()->create(['type' => CommandType::CheckUpdate, 'status' => CommandStatus::Pending, 'created_by' => $userId]);

        return true;
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

    /**
     * 'on'      → connected and screen on (green)
     * 'standby' → connected but screen off (red)
     * 'off'     → no heartbeat for > 3 min: powered off, unplugged or network down (red)
     */
    public function powerState(): string
    {
        if (! $this->isOnline()) {
            return 'off';
        }

        return $this->screen_on === false ? 'standby' : 'on';
    }

    /** Query scope helpers for "screen on and connected". */
    public static function onlineThreshold(): Carbon
    {
        return now()->subMinutes(3);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(self::onlineThreshold());
    }

    /** Revoke: invalidate all tokens immediately. */
    public function revoke(): void
    {
        $this->tokens()->delete();
        $this->update(['status' => DeviceStatus::Revoked]);
    }
}
