<x-filament-panels::page>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        @foreach ($years as $y)
            <x-filament::button size="xs" :color="$y === $year ? 'primary' : 'gray'" wire:click="$set('year', {{ $y }})">{{ $y }}</x-filament::button>
        @endforeach
    </div>

    <div class="vc-grid">
        <div class="vc-card"><div class="vc-label">Pendapatan {{ $year }}</div><div class="vc-value">{{ $rp($total) }}</div><div class="vc-muted">tagihan lunas</div></div>
        <div class="vc-card"><div class="vc-label">MRR saat ini</div><div class="vc-value">{{ $rp($mrr) }}</div><div class="vc-muted">ARR ≈ {{ $rp($mrr * 12) }}</div></div>
        <div class="vc-card"><div class="vc-label">Rata-rata / hotel</div><div class="vc-value">{{ $rp($arpa) }}</div><div class="vc-muted">per bulan (ARPA)</div></div>
        <div class="vc-card"><div class="vc-label">Piutang</div><div @class(['vc-value', 'text-danger-600' => $overdue > 0])>{{ $rp($outstanding) }}</div><div class="vc-muted">{{ $rp($overdue) }} lewat jatuh tempo</div></div>
    </div>

    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr))">
        <div class="vc-card">
            <div class="vc-label" style="margin-bottom:.75rem">Pendapatan per bulan · {{ $year }}</div>
            <table class="vc-table">
                <thead><tr><th>Bulan</th><th style="width:45%"></th><th style="text-align:right">Tagihan</th><th style="text-align:right">Jumlah</th></tr></thead>
                <tbody>
                @foreach ($months as $m)
                    <tr>
                        <td>{{ $m['label'] }}</td>
                        <td><div class="vc-progress" style="margin:0"><i style="width: {{ round($m['total'] / $max * 100) }}%"></i></div></td>
                        <td style="text-align:right" class="vc-muted">{{ $m['count'] ?: '–' }}</td>
                        <td style="text-align:right;font-weight:600">{{ $m['total'] ? $rp($m['total']) : '–' }}</td>
                    </tr>
                @endforeach
                <tr><td colspan="3"><strong>Total</strong></td><td style="text-align:right"><strong>{{ $rp($total) }}</strong></td></tr>
                </tbody>
            </table>
        </div>

        <div class="vc-card">
            <div class="vc-label" style="margin-bottom:.75rem">Pendapatan per hotel · {{ $year }}</div>
            <table class="vc-table">
                <thead><tr><th>Hotel</th><th style="text-align:right">Bulan sewa</th><th style="text-align:right">Jumlah</th><th style="text-align:right">Porsi</th></tr></thead>
                <tbody>
                @forelse ($byHotel as $row)
                    <tr>
                        <td><strong>{{ $row['hotel']->name }}</strong><div class="vc-muted">{{ $row['invoices'] }} tagihan</div></td>
                        <td style="text-align:right">{{ $row['months'] }}</td>
                        <td style="text-align:right;font-weight:600">{{ $rp($row['total']) }}</td>
                        <td style="text-align:right" class="vc-muted">{{ $total ? round($row['total'] / $total * 100) : 0 }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="vc-muted">Belum ada pembayaran di {{ $year }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="vc-card">
        <div class="vc-label" style="margin-bottom:.75rem">Penggunaan per hotel · 30 hari terakhir</div>
        <div style="overflow-x:auto">
            <table class="vc-table">
                <thead><tr><th>Hotel</th><th>Paket</th><th style="text-align:right">TV aktif</th><th style="text-align:right">Online sekarang</th><th style="text-align:right">Jam tonton</th><th>Channel terpopuler</th><th style="text-align:right">Tamu menginap</th></tr></thead>
                <tbody>
                @foreach ($usage as $u)
                    <tr>
                        <td><strong>{{ $u['hotel']->name }}</strong><div class="vc-muted">{{ $u['hotel']->slug }}</div></td>
                        <td>{{ $u['hotel']->custom_monthly_price !== null ? 'Khusus' : ($u['hotel']->plan?->name ?? '–') }}</td>
                        <td style="text-align:right">{{ $u['tvs'] }}{{ $u['quota'] ? ' / '.$u['quota'] : '' }}</td>
                        <td style="text-align:right">{{ $u['tvs'] ? round($u['online'] / $u['tvs'] * 100).'%' : '–' }}</td>
                        <td style="text-align:right">{{ number_format($u['hours'], 1, ',', '.') }}</td>
                        <td>{{ $u['top'] }}</td>
                        <td style="text-align:right">{{ $u['guests'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
