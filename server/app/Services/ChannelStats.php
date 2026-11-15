<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\ChannelCheck;
use App\Models\ChannelReport;
use App\Models\ChannelView;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Viewing statistics and uptime figures shared by the CMS page and the weekly e-mail. */
class ChannelStats
{
    public function __construct(private int $days = 7, private string $tz = 'Asia/Jakarta') {}

    private function since(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($this->days);
    }

    /** @return array{hours: float, sessions: int, rooms: int} */
    public function totals(): array
    {
        $q = ChannelView::query()->where('started_at', '>=', $this->since());

        return [
            'hours' => round((int) (clone $q)->sum('seconds') / 3600, 1),
            'sessions' => (clone $q)->count(),
            'rooms' => (clone $q)->whereNotNull('room_id')->distinct()->count('room_id'),
        ];
    }

    /** @return Collection<int, array{channel: Channel, hours: float, sessions: int, rooms: int, share: float}> */
    public function topChannels(int $limit = 50): Collection
    {
        $rows = ChannelView::query()
            ->where('started_at', '>=', $this->since())
            ->selectRaw('channel_id, SUM(seconds) as secs, COUNT(*) as sessions, COUNT(DISTINCT room_id) as rooms')
            ->groupBy('channel_id')
            ->orderByDesc('secs')
            ->limit($limit)
            ->get();
        $total = max(1, (int) $rows->sum('secs'));
        $channels = Channel::query()->whereKey($rows->pluck('channel_id'))->get()->keyBy('id');

        return $rows->filter(fn ($r) => $channels->has($r->channel_id))->map(fn ($r) => [
            'channel' => $channels[$r->channel_id],
            'hours' => round($r->secs / 3600, 1),
            'sessions' => (int) $r->sessions,
            'rooms' => (int) $r->rooms,
            'share' => round($r->secs * 100 / $total, 1),
        ])->values();
    }

    /** Active channels nobody watched in the period (candidates to drop from the licence). */
    public function unwatched(): Collection
    {
        $watched = ChannelView::query()->where('started_at', '>=', $this->since())->distinct()->pluck('channel_id');

        return Channel::query()->where('is_active', true)->whereNotIn('id', $watched)->orderBy('number')->get();
    }

    /** Watching hours by hour of day (hotel time), 0–23. @return array<int, float> */
    public function byHour(): array
    {
        $hours = array_fill(0, 24, 0.0);
        ChannelView::query()->where('started_at', '>=', $this->since())->select(['started_at', 'seconds'])
            ->orderBy('id')->chunk(2000, function ($chunk) use (&$hours) {
                foreach ($chunk as $v) {
                    $hours[(int) $v->started_at->setTimezone($this->tz)->format('G')] += $v->seconds / 3600;
                }
            });

        return array_map(fn ($h) => round($h, 1), $hours);
    }

    /**
     * Uptime per active channel from the 10-minute server checks plus TV playback reports.
     *
     * @return Collection<int, array{channel: Channel, uptime: ?float, checks: int, failed_checks: int, tv_failed: int, tv_failover: int, last_error: ?string}>
     */
    public function uptime(): Collection
    {
        $since = $this->since();
        $checks = ChannelCheck::query()->where('checked_at', '>=', $since)->whereIn('status', ['ok', 'error'])
            ->selectRaw("channel_id, COUNT(*) as total, SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed")
            ->groupBy('channel_id')->get()->keyBy('channel_id');
        $reports = ChannelReport::query()->where('created_at', '>=', $since)->whereIn('status', ['failed', 'failover'])
            ->selectRaw('channel_id, status, COUNT(*) as n')->groupBy('channel_id', 'status')->get()
            ->groupBy('channel_id');
        $lastErrors = ChannelCheck::query()->where('checked_at', '>=', $since)->where('status', 'error')
            ->orderByDesc('checked_at')->get(['channel_id', 'message', 'checked_at'])->unique('channel_id')->keyBy('channel_id');

        return Channel::query()->where('is_active', true)->orderBy('number')->get()->map(function (Channel $c) use ($checks, $reports, $lastErrors) {
            $chk = $checks->get($c->id);
            $rep = $reports->get($c->id, collect())->pluck('n', 'status');
            $err = $lastErrors->get($c->id);

            return [
                'channel' => $c,
                'uptime' => $chk && $chk->total > 0 ? round(($chk->total - $chk->failed) * 100 / $chk->total, 1) : null,
                'checks' => (int) ($chk->total ?? 0),
                'failed_checks' => (int) ($chk->failed ?? 0),
                'tv_failed' => (int) ($rep['failed'] ?? 0),
                'tv_failover' => (int) ($rep['failover'] ?? 0),
                'last_error' => $err ? $err->checked_at->setTimezone($this->tz)->format('d M H:i').' · '.$err->message : null,
            ];
        })->sortBy(fn ($r) => $r['uptime'] ?? 101)->values();
    }
}
