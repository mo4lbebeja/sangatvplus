@php($d = \App\Vendor\HotelStats::details($getRecord()))
@if (! $d['ok'])
    <p class="text-danger-600 text-sm">Database hotel tidak bisa dibaca: {{ $d['error'] ?? '' }}</p>
@else
    <div style="display:flex;gap:1.5rem;margin-bottom:.75rem">
        <div><div class="vc-label">Jam tonton</div><div class="vc-value">{{ number_format($d['watch_hours'], 1, ',', '.') }}</div></div>
        <div><div class="vc-label">Tamu menginap</div><div class="vc-value">{{ $d['guests_in_house'] }}</div></div>
    </div>
    <div class="vc-label" style="margin-bottom:.35rem">Channel terpopuler</div>
    @php($max = max(1, collect($d['top_channels'])->max('hours') ?? 1))
    @forelse ($d['top_channels'] as $c)
        <div style="font-size:.82rem;margin-bottom:.4rem">
            <div style="display:flex;justify-content:space-between"><span>{{ $c['name'] }}</span><span class="vc-muted">{{ $c['hours'] }} jam</span></div>
            <div class="vc-progress"><i style="width: {{ round($c['hours'] / $max * 100) }}%"></i></div>
        </div>
    @empty
        <p class="vc-muted">Belum ada data tontonan.</p>
    @endforelse
@endif
