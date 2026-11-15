@php($rp = fn ($v) => \App\Vendor\Billing::rupiah($v))
<div style="font-family:'Plus Jakarta Sans',ui-sans-serif,system-ui,sans-serif;max-width:720px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;color:#0F172A;font-size:13.5px;line-height:1.5">
    <div style="padding:22px 26px;background:linear-gradient(120deg,#312E81,#4338CA 45%,#6D28D9 75%,#0E7490);color:#fff">
        <div style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;opacity:.8">Laporan layanan SangaTV Plus</div>
        <div style="font-size:22px;font-weight:800;margin-top:4px">{{ $hotel->name }}</div>
        <div style="opacity:.85">{{ $label }}</div>
    </div>
    <table style="width:100%;border-collapse:separate;border-spacing:10px;padding:8px">
        <tr>
            <td style="background:#EEF2FF;border-radius:12px;padding:12px"><div style="font-size:10.5px;color:#4338CA;font-weight:700;text-transform:uppercase">Uptime layanan</div><div style="font-size:20px;font-weight:800">{{ $sla['server'] !== null ? number_format($sla['server'], 2, ',', '.').'%' : '–' }}</div></td>
            <td style="background:#ECFEFF;border-radius:12px;padding:12px"><div style="font-size:10.5px;color:#0E7490;font-weight:700;text-transform:uppercase">TV online rata-rata</div><div style="font-size:20px;font-weight:800">{{ $sla['tv'] !== null ? number_format($sla['tv'], 1, ',', '.').'%' : '–' }}</div></td>
            <td style="background:#ECFDF5;border-radius:12px;padding:12px"><div style="font-size:10.5px;color:#047857;font-weight:700;text-transform:uppercase">Jam tonton</div><div style="font-size:20px;font-weight:800">{{ number_format($usage['hours'], 1, ',', '.') }}</div></td>
            <td style="background:#FFFBEB;border-radius:12px;padding:12px"><div style="font-size:10.5px;color:#B45309;font-weight:700;text-transform:uppercase">TV aktif</div><div style="font-size:20px;font-weight:800">{{ $tvs }}{{ $quota ? ' / '.$quota : '' }}</div></td>
        </tr>
    </table>
    <div style="padding:0 26px 8px">
        <div style="font-weight:700;margin:6px 0">Channel terpopuler</div>
        @forelse ($usage['top'] as $i => $c)
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #E2E8F0"><span>{{ $i + 1 }}. {{ $c['name'] }}</span><span style="color:#64748B">{{ $c['hours'] }} jam</span></div>
        @empty
            <div style="color:#64748B">Belum ada data tontonan.</div>
        @endforelse
        <div style="font-weight:700;margin:14px 0 6px">Ringkasan</div>
        <ul style="margin:0;padding-left:18px;color:#334155">
            <li>Tamu check-in tercatat: {{ $usage['checkins'] }}</li>
            <li>Tiket dukungan: {{ $tickets }} dibuat, {{ $tickets_done }} selesai</li>
            <li>Gangguan (semua TV offline ≥ 30 menit): {{ count($sla['incidents']) }} kali</li>
            @if ($expires)<li>Masa sewa berlaku sampai {{ $expires->setTimezone('Asia/Jakarta')->translatedFormat('d F Y') }}</li>@endif
            @foreach ($invoices as $inv)<li>Tagihan {{ $inv->number }}: {{ $rp($inv->total) }} — {{ $inv->status === 'paid' ? 'lunas' : 'belum dibayar' }}</li>@endforeach
        </ul>
    </div>
    <div style="padding:16px 26px 22px;color:#64748B;font-size:12px">Lihat tagihan, kwitansi, dokumen, dan laporan lengkap di portal klien:<br><a href="{{ $hotel->portalUrl() }}" style="color:#4F46E5">{{ $hotel->portalUrl() }}</a></div>
</div>
