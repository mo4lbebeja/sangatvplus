<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** Message pushed to room TVs (ticker or full screen), optionally switching them to a channel. */
class Announcement extends Model
{
    use Auditable;

    public const STYLES = ['ticker' => 'Teks berjalan', 'fullscreen' => 'Layar penuh'];

    public const LEVELS = ['info' => 'Informasi', 'warning' => 'Penting', 'emergency' => 'Darurat'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'floors' => 'array',
            'room_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function forceChannel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'force_channel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function targetsAll(): bool
    {
        return empty($this->floors) && empty($this->room_ids);
    }

    public function targets(Room $room): bool
    {
        return $this->targetsAll()
            || in_array($room->floor, array_map('intval', (array) $this->floors), true)
            || in_array($room->id, array_map('intval', (array) $this->room_ids), true);
    }

    /** "live" | "scheduled" | "ended" | "off" */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'off',
            $this->ends_at && $this->ends_at->isPast() => 'ended',
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            default => 'live',
        };
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function payloadFor(Room $room): Collection
    {
        return static::query()->live()->with('forceChannel')->orderByDesc('id')->get()
            ->filter(fn (self $a) => $a->targets($room))
            ->values()
            ->map(fn (self $a) => [
                'id' => $a->id,
                // Changes whenever the announcement is edited, so TVs show it again.
                'version' => $a->id.'-'.$a->updated_at?->timestamp,
                'title' => $a->title,
                'message' => $a->message,
                'style' => $a->style,
                'level' => $a->level,
                'force_channel_number' => $a->forceChannel?->is_active ? $a->forceChannel->number : null,
                'ends_at' => $a->ends_at?->toIso8601String(),
            ]);
    }
}
