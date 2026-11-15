<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** A hotel renting SangaTV Plus (central database). Its data lives in its own database ($database). */
class Hotel extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // The CMS caches the hotel record for a minute: changes by Sijalucom (suspend, period) apply right away.
        static::saved(fn (Hotel $hotel) => Cache::forget("central:hotel:{$hotel->id}"));
    }

    protected function casts(): array
    {
        return ['features' => 'array', 'service_expires_at' => 'datetime', 'service_released_at' => 'datetime', 'activated_at' => 'datetime',
            'discount_percent' => 'float', 'auto_invoice' => 'boolean'];
    }

    /** CMS can be used (data export stays possible after the service ended; only "suspended" locks it). */
    public function isAccessible(): bool
    {
        return $this->status === 'active';
    }

    /** "active" | "expiring" | "released" for the TVs of this hotel (rental period / release by Sijalucom). */
    public function serviceStatus(int $warnDays = 14): string
    {
        return match (true) {
            $this->service_released_at !== null => 'released',
            $this->service_expires_at !== null && $this->service_expires_at->isPast() => 'released',
            $this->service_expires_at !== null && $this->service_expires_at->lte(now()->addDays($warnDays)) => 'expiring',
            default => 'active',
        };
    }

    /** A rental ends at the end of that day in Indonesia (WIB), whatever time the date picker sends. */
    protected function serviceExpiresAt(): Attribute
    {
        return Attribute::make(set: fn ($value) => blank($value) ? null
            : Carbon::parse($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10), 'Asia/Jakarta')->endOfDay()->utc());
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(VendorActivity::class);
    }

    /** Monthly rental after discount (custom price, else plan price for the contracted TVs). */
    public function monthlyPrice(): int
    {
        $gross = $this->custom_monthly_price ?? $this->plan?->monthlyFor($this->tv_quota) ?? 0;

        return (int) round($gross * (1 - $this->discount_percent / 100));
    }

    /** Whole days until the rental ends (negative = ended), null = no end date. */
    public function daysLeft(): ?int
    {
        return $this->service_expires_at === null ? null
            : (int) now('Asia/Jakarta')->startOfDay()->diffInDays($this->service_expires_at->setTimezone('Asia/Jakarta')->startOfDay(), false);
    }

    /** Counts toward recurring revenue: CMS active, service running, priced. */
    public function isBillable(): bool
    {
        return $this->status === 'active' && $this->serviceStatus() !== 'released' && $this->monthlyPrice() > 0;
    }

    public function codes(): HasMany
    {
        return $this->hasMany(HotelCode::class);
    }
}
