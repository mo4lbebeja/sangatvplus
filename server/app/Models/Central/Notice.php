<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/** Announcement from Sijalucom shown as a banner in hotel CMS (all hotels or selected ones). */
class Notice extends Model
{
    protected $connection = 'central';

    protected $guarded = ['id'];

    public const LEVELS = ['info' => 'Informasi', 'success' => 'Kabar baik', 'warning' => 'Penting', 'maintenance' => 'Jadwal maintenance'];

    protected function casts(): array
    {
        return ['hotel_ids' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    /** @return Collection<int, Notice> active notices for one hotel */
    public static function activeFor(Hotel $hotel): Collection
    {
        return self::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->latest()->get()
            ->filter(fn (Notice $n) => empty($n->hotel_ids) || in_array($hotel->id, array_map('intval', $n->hotel_ids), true))
            ->values();
    }
}
