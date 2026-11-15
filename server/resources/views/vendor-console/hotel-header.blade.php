@php
    $h = $getRecord();
    $s = \App\Vendor\HotelStats::for($h);
    $days = $h->daysLeft();
    $svc = $h->status !== 'active' ? 'suspended' : $h->serviceStatus();
    $status = ['active' => 'Aktif', 'expiring' => 'Segera habis', 'released' => 'Mode lepas', 'suspended' => 'Ditangguhkan'][$svc];
    $tone = ['active' => 'emerald', 'expiring' => 'amber', 'released' => 'slate', 'suspended' => 'rose'][$svc];
    $open = $h->invoices()->where('status', 'unpaid')->sum('total');
    $paid = $h->invoices()->where('status', 'paid')->sum('total');
    $rp = fn ($v) => \App\Vendor\Billing::rupiah($v);
@endphp
<div class="vc-grid">
    <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-{{ $tone }}"><x-filament::icon icon="heroicon-o-shield-check" /></span>
        <div style="min-width:0"><div class="vc-label">Status</div><div class="vc-value">{{ $status }}</div>
        <div class="vc-muted">{{ $h->service_expires_at ? 'Sampai '.$h->service_expires_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y').($days !== null ? ' · '.($days < 0 ? 'lewat '.abs($days).' hari' : $days.' hari lagi') : '') : 'Tanpa batas waktu' }}</div></div></div>
    <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-indigo"><x-filament::icon icon="heroicon-o-tv" /></span>
        <div style="flex:1;min-width:0"><div class="vc-label">TV online</div><div class="vc-value">{{ $s['ok'] ? $s['online'].' / '.$s['tvs'] : 'galat' }}</div>
        <div class="vc-muted">{{ $s['rooms'] }} kamar{{ $h->tv_quota ? ' · kuota '.$h->tv_quota.' TV' : '' }}</div>
        @if ($h->tv_quota)<div @class(['vc-progress', 'is-warn' => $s['tvs'] >= $h->tv_quota])><i style="width: {{ min(100, round($s['tvs'] / max(1, $h->tv_quota) * 100)) }}%"></i></div>@endif</div></div>
    <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-emerald"><x-filament::icon icon="heroicon-o-banknotes" /></span>
        <div><div class="vc-label">Sewa / bulan</div><div class="vc-value">{{ $rp($h->monthlyPrice()) }}</div><div class="vc-muted">Total dibayar {{ $rp($paid) }}</div></div></div>
    <div class="vc-card vc-stat"><span class="vc-stat-icon vc-tone-{{ $open > 0 ? 'rose' : 'sky' }}"><x-filament::icon icon="heroicon-o-document-text" /></span>
        <div><div class="vc-label">Piutang</div><div @class(['vc-value', 'text-danger-600' => $open > 0])>{{ $rp($open) }}</div>
        <div class="vc-muted">Backup: {{ $s['backup'] ? \Illuminate\Support\Carbon::createFromTimestamp($s['backup'])->diffForHumans() : 'belum ada' }}</div></div></div>
</div>
