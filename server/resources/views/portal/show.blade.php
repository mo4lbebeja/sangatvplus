@extends('portal.layout')
@section('title', 'Portal '.$hotel->name)
@php
    $rp = fn ($v) => \App\Vendor\Billing::rupiah($v);
    $svc = $hotel->status !== 'active' ? ['Ditangguhkan', 'bad'] : (['active' => ['Aktif', 'ok'], 'expiring' => ['Segera habis', 'warn'], 'released' => ['Berakhir', 'bad']][$hotel->serviceStatus()]);
    $open = $invoices->where('status', 'unpaid');
@endphp
@section('body')
<div class="band"><div class="wrap">
    <small>Portal klien · SangaTV Plus</small>
    <h1>{{ $hotel->name }}</h1>
    <div style="opacity:.85">Kode {{ $hotel->slug }}{{ $hotel->plan ? ' · Paket '.$hotel->plan->name : '' }}</div>
</div></div>
<div class="main"><div class="wrap">
    <div class="grid">
        <div class="card"><div class="label">Status layanan</div><div class="value"><span class="badge {{ $svc[1] }}" style="font-size:14px">{{ $svc[0] }}</span></div>
            <div class="muted">{{ $hotel->service_expires_at ? 'Berlaku s/d '.$hotel->service_expires_at->setTimezone('Asia/Jakarta')->translatedFormat('d F Y') : 'Tanpa batas waktu' }}</div></div>
        <div class="card"><div class="label">TV aktif</div><div class="value">{{ $stats['tvs'] }}{{ $hotel->tv_quota ? ' / '.$hotel->tv_quota : '' }}</div><div class="muted">{{ $stats['online'] }} online sekarang</div></div>
        <div class="card"><div class="label">Uptime bulan ini</div><div class="value">{{ $sla['server'] !== null ? number_format($sla['server'], 2, ',', '.').'%' : '–' }}</div><div class="muted">TV online rata-rata {{ $sla['tv'] !== null ? number_format($sla['tv'], 1, ',', '.').'%' : '–' }}</div></div>
        <div class="card"><div class="label">Tagihan terbuka</div><div class="value" style="{{ $open->count() ? 'color:#BE123C' : '' }}">{{ $rp($open->sum('total')) }}</div><div class="muted">{{ $open->count() }} tagihan</div></div>
    </div>

    <section class="card"><h2>Tagihan</h2><div class="scroll"><table>
        <thead><tr><th>No.</th><th>Periode / uraian</th><th style="text-align:right">Jumlah</th><th>Jatuh tempo</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($invoices as $inv)
            @php($st = $inv->displayStatus())
            <tr>
                <td><strong>{{ $inv->number }}</strong></td>
                <td>{{ $inv->kind === 'one_off' ? \Illuminate\Support\Str::limit($inv->items[0]['description'] ?? 'Biaya', 50) : $inv->period_start->translatedFormat('d M Y').' – '.$inv->period_end->translatedFormat('d M Y') }}</td>
                <td style="text-align:right;font-weight:700">{{ $rp($inv->total) }}</td>
                <td>{{ $inv->due_at->translatedFormat('d M Y') }}</td>
                <td><span class="badge {{ ['paid' => 'ok', 'overdue' => 'bad', 'unpaid' => 'warn'][$st] ?? 'info' }}">{{ $st === 'overdue' ? 'Lewat tempo' : (\App\Models\Central\Invoice::STATUSES[$st] ?? $st) }}</span></td>
                <td style="white-space:nowrap">
                    @if ($inv->status === 'unpaid' && \App\Vendor\Payments::enabled())<a class="btn btn-primary" href="{{ url("/portal/{$token}/tagihan/{$inv->id}/bayar") }}">Bayar online</a>@endif
                    <a class="btn btn-ghost" href="{{ url("/portal/{$token}/tagihan/{$inv->id}/cetak") }}" target="_blank">Tagihan</a>
                    @if ($inv->status === 'paid')<a class="btn btn-ghost" href="{{ url("/portal/{$token}/tagihan/{$inv->id}/kwitansi") }}" target="_blank">Kwitansi</a>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Belum ada tagihan.</td></tr>
        @endforelse
        </tbody></table></div></section>

    <div class="grid" style="margin-top:12px">
        <section class="card" style="margin:0"><h2>Laporan bulanan</h2>
            @foreach ($months as $m)<a class="btn btn-ghost" href="{{ url("/portal/{$token}/laporan/".$m->format('Y-m')) }}" target="_blank">{{ $m->translatedFormat('F Y') }}</a>@endforeach
        </section>
        <section class="card" style="margin:0"><h2>Dokumen</h2>
            @forelse ($documents as $d)<div style="padding:4px 0"><a href="{{ url("/portal/{$token}/dokumen/{$d->id}") }}" target="_blank" style="color:#4F46E5;font-weight:600">📄 {{ $d->title }}</a> <span class="muted">· {{ \App\Models\Central\HotelDocument::TYPES[$d->type] ?? $d->type }}</span></div>
            @empty<div class="muted">Belum ada dokumen.</div>@endforelse
        </section>
        <section class="card" style="margin:0"><h2>Tiket dukungan</h2>
            @forelse ($tickets as $t)<div style="padding:4px 0">{{ $t->number }} · {{ \Illuminate\Support\Str::limit($t->subject, 38) }} <span class="badge {{ $t->isOpen() ? 'warn' : 'ok' }}">{{ \App\Models\Central\Ticket::STATUSES[$t->status] }}</span></div>
            @empty<div class="muted">Tidak ada tiket. Buat tiket dari CMS → Bantuan Sijalucom.</div>@endforelse
        </section>
    </div>
    @php($bank = \App\Models\Central\VendorSetting::read('bank_accounts'))
    @if ($bank)<section class="card"><h2>Pembayaran transfer</h2><div style="white-space:pre-line;font-weight:600">{{ $bank }}</div><div class="muted">Cantumkan nomor tagihan pada berita transfer.</div></section>@endif
    <div class="foot">{{ \App\Models\Central\VendorSetting::read('company_name') }} · {{ \App\Models\Central\VendorSetting::read('company_email') }} · Tautan ini rahasia, jangan dibagikan.</div>
</div></div>
@endsection
