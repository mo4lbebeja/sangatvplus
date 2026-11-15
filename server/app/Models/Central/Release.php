<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A TV app version published by Sijalucom; ReleaseSync copies it into the hotels' databases. */
class Release extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'version_code' => 'integer', 'hotel_ids' => 'array'];
    }

    /** One active release per package (the hotel copies follow via AppRelease's own rule). */
    protected static function booted(): void
    {
        static::saved(function (Release $r) {
            if ($r->is_active) {
                static::query()->whereKeyNot($r->getKey())->where('package_name', $r->package_name)->where('is_active', true)->update(['is_active' => false]);
            }
        });
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(VendorUser::class, 'published_by');
    }

    public function targets(Hotel $hotel): bool
    {
        return empty($this->hotel_ids) || in_array($hotel->id, array_map('intval', $this->hotel_ids), true);
    }
}
