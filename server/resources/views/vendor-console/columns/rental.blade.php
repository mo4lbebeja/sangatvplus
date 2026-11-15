@php
    $h = $getRecord();
    $days = $h->daysLeft();
    $start = $h->activated_at;
    $end = $h->service_expires_at;
    $pct = ($start && $end && $end->gt($start)) ? max(0, min(100, round(now()->diffInSeconds($start, true) / max(1, $end->diffInSeconds($start, true)) * 100))) : null;
@endphp
<div style="min-width:10rem;padding:.35rem 0">
    @if ($h->service_released_at)
        <span class="vc-muted">Layanan dilepas {{ $h->service_released_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y') }}</span>
    @elseif (! $end)
        <span class="vc-muted">Tanpa batas</span>
    @else
        <div style="display:flex;justify-content:space-between;gap:.5rem;font-size:.82rem">
            <strong>{{ $end->setTimezone('Asia/Jakarta')->translatedFormat('d M Y') }}</strong>
            <span @class(['text-danger-600' => $days <= 7, 'text-warning-600' => $days > 7 && $days <= 30, 'vc-muted' => $days > 30])>
                {{ $days < 0 ? 'lewat '.abs($days).' hr' : ($days === 0 ? 'hari ini' : $days.' hr lagi') }}
            </span>
        </div>
        @if ($pct !== null)
            <div @class(['vc-progress', 'is-danger' => $days <= 7, 'is-warn' => $days > 7 && $days <= 30])><i style="width: {{ $pct }}%"></i></div>
        @endif
    @endif
</div>
