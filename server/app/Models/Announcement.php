<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
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

    public const DAYS = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'floors' => 'array',
            'days' => 'array',
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

    /** "live" | "scheduled" | "waiting" (daily, outside today's hours) | "ended" | "off" */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'off',
            $this->ends_at && $this->ends_at->isPast() => 'ended',
            $this->starts_at && $this->starts_at->isFuture() => 'scheduled',
            $this->isDaily() && ! $this->dailyWindow() => 'waiting',
            default => 'live',
        };
    }

    public function isDaily(): bool
    {
        return filled($this->daily_from) && filled($this->daily_until);
    }

    /** Daily hours as "07:00–09:00 · Sen, Sel" (or "setiap hari"). */
    public function dailyLabel(): ?string
    {
        if (! $this->isDaily()) {
            return null;
        }
        $days = array_map('intval', (array) $this->days);
        $when = (empty($days) || count($days) === 7) ? 'setiap hari' : implode(', ', array_map(fn (int $d) => self::DAYS[$d] ?? $d, $days));

        return substr($this->daily_from, 0, 5).'–'.substr($this->daily_until, 0, 5).' · '.$when;
    }

    /**
     * Today's showing window (start, end) that contains $now, in hotel time, or null outside it.
     * "22:00–06:00" crosses midnight; the weekday filter applies to the day the window starts.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function dailyWindow(?CarbonImmutable $now = null): ?array
    {
        if (! $this->isDaily()) {
            return null;
        }
        $now = ($now ?? CarbonImmutable::now())->setTimezone(static::timezone());
        $days = array_map('intval', (array) $this->days);
        $from = substr($this->daily_from, 0, 5);
        $until = substr($this->daily_until, 0, 5);

        foreach ([$now, $now->subDay()] as $day) {
            $start = $day->setTimeFromTimeString($from);
            $end = $day->setTimeFromTimeString($until);
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay();
            }
            $dayOk = empty($days) || in_array($start->dayOfWeekIso, $days, true);
            if ($dayOk && $now->greaterThanOrEqualTo($start) && $now->lessThan($end)) {
                return [$start, $end];
            }
        }

        return null;
    }

    /** On TV right now: active, inside the date range and (for daily ones) inside today's hours. */
    public function showingNow(): bool
    {
        return $this->state() === 'live';
    }

    /** Hotel time zone (not cached: one process may serve several hotels). */
    public static function timezone(): string
    {
        return HotelProfile::query()->value('timezone') ?: 'Asia/Jakarta';
    }

    /** Live announcements (active, in range, inside today's hours). @return Collection<int, self> */
    public static function showing(): Collection
    {
        return static::query()->live()->orderByDesc('id')->get()->filter(fn (self $a) => $a->showingNow())->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function payloadFor(Room $room): Collection
    {
        return static::query()->live()->with('forceChannel')->orderByDesc('id')->get()
            ->filter(fn (self $a) => $a->targets($room) && $a->showingNow())
            ->values()
            ->map(fn (self $a) => [
                'id' => $a->id,
                // Changes whenever the announcement is edited (and every day for daily ones), so TVs show it again.
                'version' => $a->id.'-'.$a->updated_at?->timestamp.(($w = $a->dailyWindow()) ? '-'.$w[0]->format('Ymd') : ''),
                'title' => $a->title,
                'message' => $a->message,
                'style' => $a->style,
                'level' => $a->level,
                'force_channel_number' => $a->forceChannel?->is_active ? $a->forceChannel->number : null,
                // Daily: the TV takes it down at today's end time even between heartbeats.
                'ends_at' => collect([$a->ends_at, $a->dailyWindow()[1] ?? null])->filter()->min()?->toIso8601String(),
            ]);
    }
}
