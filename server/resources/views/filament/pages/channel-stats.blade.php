@php
    $s = $this->stats();
    $totals = $s->totals();
    $top = $s->topChannels(30);
    $hours = $s->byHour();
    $peak = max($hours) > 0 ? array_search(max($hours), $hours) : null;
    $maxHour = max(0.1, max($hours));
    $uptime = $s->uptime();
    $unwatched = $s->unwatched();
    $checked = $uptime->whereNotNull('uptime');
    $avgUptime = $checked->count() ? round($checked->avg('uptime'), 1) : null;
@endphp
<x-filament-panels::page>
    <div class="sg-stats">
        <div class="sg-stats-period">
            @foreach ([1 => 'Hari ini', 7 => '7 hari', 30 => '30 hari', 90 => '90 hari'] as $d => $label)
                <button type="button" wire:click="$set('days', {{ $d }})" @class(['is-on' => $days === $d])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="sg-stats-kpis">
            <div class="sg-card sg-kpi">
                <x-filament::icon icon="heroicon-o-clock" class="sg-kpi-icon" />
                <div><span>Total jam tonton</span><strong>{{ number_format($totals['hours'], 1, ',', '.') }}</strong><small>{{ number_format($totals['sessions'], 0, ',', '.') }} sesi</small></div>
            </div>
            <div class="sg-card sg-kpi">
                <x-filament::icon icon="heroicon-o-trophy" class="sg-kpi-icon" />
                <div><span>Channel teratas</span><strong>{{ $top->first()['channel']->name ?? '—' }}</strong><small>{{ $top->first() ? $top->first()['share'].'% waktu tonton' : 'belum ada data' }}</small></div>
            </div>
            <div class="sg-card sg-kpi">
                <x-filament::icon icon="heroicon-o-fire" class="sg-kpi-icon" />
                <div><span>Jam tersibuk</span><strong>{{ $peak !== null ? sprintf('%02d.00–%02d.00', $peak, ($peak + 1) % 24) : '—' }}</strong><small>{{ $totals['rooms'] }} kamar menonton</small></div>
            </div>
            <div class="sg-card sg-kpi">
                <x-filament::icon icon="heroicon-o-signal" class="sg-kpi-icon" />
                <div><span>Rata-rata uptime</span><strong @class(['is-bad' => $avgUptime !== null && $avgUptime < 95])>{{ $avgUptime !== null ? $avgUptime.'%' : '—' }}</strong><small>{{ $checked->count() }} channel dicek server</small></div>
            </div>
        </div>

        <div class="sg-stats-grid">
            <section class="sg-card sg-stats-card">
                <header><x-filament::icon icon="heroicon-o-chart-bar" /> Channel paling banyak ditonton</header>
                @forelse ($top as $r)
                    <div class="sg-bar-row">
                        <span class="sg-bar-name"><b>{{ str_pad((string) $r['channel']->number, 3, '0', STR_PAD_LEFT) }}</b> {{ $r['channel']->name }}</span>
                        <span class="sg-bar"><i style="width: {{ max(2, $r['share'] / max(1, $top->first()['share']) * 100) }}%"></i></span>
                        <span class="sg-bar-val">{{ number_format($r['hours'], 1, ',', '.') }} jam · {{ $r['rooms'] }} kamar</span>
                    </div>
                @empty
                    <p class="sg-empty">Belum ada data tontonan pada periode ini. TV mengirim data saat tamu menonton (versi aplikasi 0.2.0 ke atas).</p>
                @endforelse
            </section>

            <section class="sg-card sg-stats-card">
                <header><x-filament::icon icon="heroicon-o-clock" /> Jam ramai (waktu hotel)</header>
                <div class="sg-hours">
                    @foreach ($hours as $h => $v)
                        <div class="sg-hour" title="{{ sprintf('%02d.00', $h) }} · {{ $v }} jam">
                            <i style="height: {{ $v > 0 ? max(4, $v / $maxHour * 100) : 0 }}%" @class(['is-peak' => $h === $peak])></i>
                            <span>{{ $h % 3 === 0 ? sprintf('%02d', $h) : '' }}</span>
                        </div>
                    @endforeach
                </div>
                @if ($unwatched->isNotEmpty() && $totals['sessions'] > 0)
                    <div class="sg-unwatched">
                        <b>Tidak ditonton sama sekali ({{ $unwatched->count() }}):</b>
                        {{ $unwatched->map(fn ($c) => $c->number.' '.$c->name)->implode(', ') }}
                        <small>Kandidat dihapus dari paket lisensi.</small>
                    </div>
                @endif
            </section>
        </div>

        <section class="sg-card sg-stats-card">
            <header><x-filament::icon icon="heroicon-o-heart" /> Uptime per channel ({{ $days }} hari)</header>
            <div class="sg-table-wrap">
                <table class="sg-uptime">
                    <thead><tr><th>Channel</th><th>Uptime</th><th>Cek gagal</th><th>Gagal di TV</th><th>Pindah cadangan</th><th>Error terakhir</th></tr></thead>
                    <tbody>
                        @foreach ($uptime as $r)
                            @php($u = $r['uptime'])
                            <tr>
                                <td><b>{{ str_pad((string) $r['channel']->number, 3, '0', STR_PAD_LEFT) }}</b> {{ $r['channel']->name }}</td>
                                <td><span @class(['sg-pill', 'is-ok' => $u !== null && $u >= 99, 'is-warn' => $u !== null && $u < 99 && $u >= 95, 'is-bad' => $u !== null && $u < 95])>{{ $u === null ? 'tidak dicek' : $u.'%' }}</span></td>
                                <td>{{ $r['failed_checks'] }} / {{ $r['checks'] }}</td>
                                <td>{{ $r['tv_failed'] ?: '—' }}</td>
                                <td>{{ $r['tv_failover'] ?: '—' }}</td>
                                <td class="sg-muted">{{ $r['last_error'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
