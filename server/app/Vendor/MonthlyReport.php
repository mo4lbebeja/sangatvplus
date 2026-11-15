<?php

namespace App\Vendor;

use App\Models\Central\Hotel;
use App\Models\Central\Ticket;
use App\Models\ChannelView;
use App\Models\Guest;
use App\Tenancy\HotelManager;
use Illuminate\Support\Carbon;

/** Monthly service report of one hotel (email on the 1st, client portal, console). */
class MonthlyReport
{
    public static function build(Hotel $hotel, Carbon $month): array
    {
        $m = $month->copy()->setTimezone(Billing::TZ)->startOfMonth();
        [$from, $to] = [$m->copy()->utc(), $m->copy()->endOfMonth()->utc()];
        $usage = rescue(fn () => app(HotelManager::class)->run($hotel, fn () => [
            'hours' => round(ChannelView::query()->whereBetween('started_at', [$from, $to])->sum('seconds') / 3600, 1),
            'top' => ChannelView::query()->whereBetween('started_at', [$from, $to])->selectRaw('channel_id, sum(seconds) as s')->groupBy('channel_id')
                ->orderByDesc('s')->limit(5)->with('channel:id,name')->get()->map(fn ($v) => ['name' => $v->channel?->name ?? '?', 'hours' => round($v->s / 3600, 1)])->all(),
            'checkins' => Guest::query()->whereBetween('checked_in_at', [$from, $to])->count(),
        ]), ['hours' => 0, 'top' => [], 'checkins' => 0], false);
        $stats = HotelStats::for($hotel);

        return [
            'hotel' => $hotel, 'month' => $m, 'label' => $m->translatedFormat('F Y'),
            'sla' => Sla::forPeriod($hotel, $from, $to),
            'tvs' => $stats['tvs'], 'quota' => $hotel->tv_quota,
            'usage' => $usage,
            'tickets' => Ticket::query()->where('hotel_id', $hotel->id)->whereBetween('created_at', [$from, $to])->count(),
            'tickets_done' => Ticket::query()->where('hotel_id', $hotel->id)->whereBetween('resolved_at', [$from, $to])->count(),
            'invoices' => $hotel->invoices()->whereBetween('issued_at', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->get(),
            'expires' => $hotel->service_expires_at,
        ];
    }
}
