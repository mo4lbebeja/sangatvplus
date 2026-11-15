@php
    $h = $getRecord();
    $s = \App\Vendor\HotelStats::for($h);
    $days = $h->daysLeft();
    $status = $h->status !== 'active' ? 'Ditangguhkan' : ['active' => 'Aktif', 'expiring' => 'Segera habis', 'released' => 'Mode lepas'][$h->serviceStatus()];
    $open = $h->invoices()->where('status', 'unpaid')->sum('total');
    $paid = $h->invoices()->where('status', 'paid')->sum('total');
@endphp
<div class="vc-grid">
    <div class="vc-card"><div class="vc-label">Status</div><div class="vc-value">{{ $status }}</div>
        <div class="vc-muted">{{ $h->service_expires_at ? 'Sewa sampai '.$h->service_expires_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y').($days !== null ? ' · '.($days < 0 ? 'lewat '.abs($days).' hari' : $days.' hari lagi') : '') : 'Tanpa batas waktu' }}</div></div>
    <div class="vc-card"><div class="vc-label">TV online</div><div class="vc-value">{{ $s['ok'] ? $s['online'].' / '.$s['tvs'] : 'galat' }}</div>
        <div class="vc-muted">{{ $s['rooms'] }} kamar{{ $h->tv_quota ? ' · kuota '.$h->tv_quota.' TV' : '' }}</div>
        @if ($h->tv_quota)<div @class(['vc-progress', 'is-warn' => $s['tvs'] >= $h->tv_quota])><i style="width: {{ min(100, round($s['tvs'] / max(1, $h->tv_quota) * 100)) }}%"></i></div>@endif
    </div>
    <div class="vc-card"><div class="vc-label">Sewa / bulan</div><div class="vc-value">{{ \App\Vendor\Billing::rupiah($h->monthlyPrice()) }}</div>
        <div class="vc-muted">Total dibayar {{ \App\Vendor\Billing::rupiah($paid) }}</div></div>
    <div class="vc-card"><div class="vc-label">Piutang</div><div @class(['vc-value', 'text-danger-600' => $open > 0])>{{ \App\Vendor\Billing::rupiah($open) }}</div>
        <div class="vc-muted">Backup terakhir: {{ $s['backup'] ? \Illuminate\Support\Carbon::createFromTimestamp($s['backup'])->diffForHumans() : 'belum ada' }}</div></div>
</div>
