<!doctype html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f4f7fa;font-family:Arial,Helvetica,sans-serif;color:#0b2234">
<div style="max-width:720px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;border:1px solid #e3e9ef">
    <h1 style="margin:0 0 4px;font-size:20px">Laporan channel TV · {{ $hotel->name }}</h1>
    <p style="margin:0 0 20px;color:#5b7083;font-size:13px">{{ $days }} hari terakhir, dibuat {{ now()->setTimezone($hotel->timezone ?: 'Asia/Jakarta')->format('d M Y H:i') }}</p>

    <h2 style="font-size:15px;margin:0 0 8px">Ketersediaan (uptime) per channel</h2>
    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px">
        <tr style="background:#f0f4f8;text-align:left"><th>Channel</th><th>Uptime</th><th>Cek gagal</th><th>Gagal di TV</th><th>Pindah cadangan</th><th>Error terakhir</th></tr>
        @foreach ($uptime as $r)
            @php($u = $r['uptime'])
            <tr style="border-bottom:1px solid #eef2f6">
                <td>{{ str_pad((string) $r['channel']->number, 3, '0', STR_PAD_LEFT) }} {{ $r['channel']->name }}</td>
                <td style="font-weight:bold;color:{{ $u === null ? '#8899a6' : ($u >= 99 ? '#0f9d58' : ($u >= 95 ? '#b7791f' : '#c53030')) }}">{{ $u === null ? 'tidak dicek' : $u.'%' }}</td>
                <td>{{ $r['failed_checks'] }} / {{ $r['checks'] }}</td>
                <td>{{ $r['tv_failed'] }}</td>
                <td>{{ $r['tv_failover'] }}</td>
                <td style="color:#5b7083">{{ $r['last_error'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>

    <h2 style="font-size:15px;margin:24px 0 8px">Paling banyak ditonton</h2>
    <p style="margin:0 0 8px;font-size:13px;color:#5b7083">Total {{ $totals['hours'] }} jam, {{ $totals['sessions'] }} sesi, {{ $totals['rooms'] }} kamar.</p>
    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px">
        <tr style="background:#f0f4f8;text-align:left"><th>Channel</th><th>Jam tonton</th><th>Kamar</th><th>Porsi</th></tr>
        @forelse ($top as $r)
            <tr style="border-bottom:1px solid #eef2f6"><td>{{ $r['channel']->name }}</td><td>{{ $r['hours'] }}</td><td>{{ $r['rooms'] }}</td><td>{{ $r['share'] }}%</td></tr>
        @empty
            <tr><td colspan="4" style="color:#8899a6">Belum ada data tontonan.</td></tr>
        @endforelse
    </table>
    <p style="margin:24px 0 0;font-size:12px;color:#8899a6">Uptime dihitung dari pemeriksaan server tiap 10 menit. Channel multicast lokal dipantau lewat laporan TV.</p>
</div>
</body>
</html>
