@php
    $h = $getRecord();
    $s = \App\Vendor\HotelStats::for($h);
    $pct = $h->tv_quota ? min(100, round($s['tvs'] / max(1, $h->tv_quota) * 100)) : null;
@endphp
<div style="min-width:9rem;padding:.35rem 0">
    @if (! $s['ok'])
        <span class="text-danger-600" style="font-size:.82rem;display:inline-flex;gap:.3rem;align-items:center"><x-filament::icon icon="heroicon-o-exclamation-triangle" class="vc-ic" style="color:#E11D48" />Database galat</span>
    @else
        <div style="display:flex;align-items:center;gap:.45rem;font-size:.85rem">
            <x-filament::icon icon="heroicon-o-tv" class="vc-ic" />
            <strong>{{ $s['tvs'] }}</strong><span class="vc-muted">{{ $h->tv_quota ? '/ '.$h->tv_quota.' TV' : 'TV' }}</span>
            <span style="margin-left:auto;display:inline-flex;align-items:center;font-size:.75rem" class="vc-muted"><span class="vc-dot {{ $s['online'] ? 'on' : 'off' }}"></span>{{ $s['online'] }} online</span>
        </div>
        @if ($pct !== null)
            <div @class(['vc-progress', 'is-warn' => $pct >= 90])><i style="width: {{ $pct }}%"></i></div>
        @endif
    @endif
</div>
