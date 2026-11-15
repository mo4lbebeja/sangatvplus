<?php

namespace App\Vendor;

use App\Models\Central\FleetSnapshot;
use App\Models\Central\Hotel;
use Illuminate\Support\Carbon;

/**
 * Uptime from 15-minute fleet snapshots:
 *  - server: share of expected 15-minute slots that have a snapshot (the server was up and running jobs);
 *  - tv: average share of active TVs that were online.
 * Incidents: periods where every TV of a hotel was offline.
 */
class Sla
{
    public const SLOT_MINUTES = 15;

    /** @return array{server: ?float, tv: ?float, samples: int, incidents: list<array{from: Carbon, to: Carbon, minutes: int}>} */
    public static function forPeriod(Hotel $hotel, Carbon $from, Carbon $to): array
    {
        $rows = FleetSnapshot::query()->where('hotel_id', $hotel->id)->whereBetween('taken_at', [$from, $to])->orderBy('taken_at')->get(['taken_at', 'tvs', 'online']);
        if ($rows->isEmpty()) {
            return ['server' => null, 'tv' => null, 'samples' => 0, 'incidents' => []];
        }
        $start = $rows->first()->taken_at->max($from);
        $end = $to->copy()->min(now());
        $expected = max(1, (int) floor($start->diffInMinutes($end) / self::SLOT_MINUTES) + 1);
        $slots = $rows->map(fn ($r) => intdiv((int) $r->taken_at->getTimestamp(), self::SLOT_MINUTES * 60))->unique()->count();
        $withTvs = $rows->where('tvs', '>', 0);

        $incidents = [];
        $open = null;
        foreach ($rows as $r) {
            $down = $r->tvs > 0 && $r->online === 0;
            if ($down && ! $open) {
                $open = $r->taken_at;
            } elseif (! $down && $open) {
                $incidents[] = ['from' => $open, 'to' => $r->taken_at, 'minutes' => (int) $open->diffInMinutes($r->taken_at)];
                $open = null;
            }
        }
        if ($open) {
            $incidents[] = ['from' => $open, 'to' => $rows->last()->taken_at, 'minutes' => (int) $open->diffInMinutes($rows->last()->taken_at)];
        }

        return [
            'server' => round(min(100, $slots / $expected * 100), 2),
            'tv' => $withTvs->isEmpty() ? null : round($withTvs->avg(fn ($r) => $r->online / $r->tvs) * 100, 1),
            'samples' => $rows->count(),
            'incidents' => array_values(array_filter($incidents, fn ($i) => $i['minutes'] >= 30)),
        ];
    }

    public static function forMonth(Hotel $hotel, Carbon $month): array
    {
        $m = $month->copy()->setTimezone(Billing::TZ)->startOfMonth();

        return self::forPeriod($hotel, $m->copy()->utc(), $m->copy()->endOfMonth()->utc());
    }
}
