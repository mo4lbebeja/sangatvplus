<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A hotel renting SangaTV Plus (central database). Its data lives in its own database ($database). */
class Hotel extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['features' => 'array', 'service_expires_at' => 'datetime', 'service_released_at' => 'datetime'];
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

    public function codes(): HasMany
    {
        return $this->hasMany(HotelCode::class);
    }
}
