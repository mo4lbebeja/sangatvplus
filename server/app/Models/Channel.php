<?php

namespace App\Models;

use App\Enums\SourceType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use Auditable, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'drm' => 'array',
            'is_active' => 'boolean',
            'number' => 'integer',
            'health_checked_at' => 'datetime',
            'room_types' => 'array',
            'is_adult' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ChannelCategory::class, 'channel_category_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ChannelReport::class);
    }

    /** TV problem reports (failover/failed) in the last 24 h. */
    public function recentProblemCount(): int
    {
        return $this->reports()->whereIn('status', ['failover', 'failed'])->where('created_at', '>', now()->subDay())->count();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ChannelProvider::class, 'channel_provider_id');
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(EpgProgramme::class);
    }

    /**
     * Channels a room may watch: active, in the room type's package (no package = every room),
     * and adult channels only once the hotel has set an adult PIN (the TV then asks for it).
     */
    public function scopeVisibleTo(Builder $query, ?Room $room): Builder
    {
        $query->where('is_active', true)
            ->where(function (Builder $query) use ($room) {
                $query->whereNull('room_types')->orWhereJsonLength('room_types', 0);
                if (filled($room?->type)) {
                    $query->orWhereJsonContains('room_types', $room->type);
                }
            });

        if (! HotelProfile::current()->adult_pin) {
            $query->where('is_adult', false);
        }

        return $query;
    }
}
