@php
    $s = \App\Models\Central\VendorSetting::allSettings();
    $h = $invoice->hotel;
    $rp = fn ($v) => \App\Vendor\Billing::rupiah($v);
    $status = $invoice->displayStatus();
    $stamp = ['paid' => ['LUNAS', '#059669'], 'overdue' => ['LEWAT TEMPO', '#E11D48'], 'unpaid' => ['BELUM DIBAYAR', '#D97706'], 'void' => ['DIBATALKAN', '#64748B'], 'draft' => ['DRAF', '#64748B']][$status];
@endphp
<div style="font-family:'Plus Jakarta Sans',ui-sans-serif,system-ui,sans-serif;color:#0F172A;background:#fff;max-width:820px;margin:0 auto;padding:40px 44px;border-radius:14px;position:relative;font-size:13px;line-height:1.5">
    <table style="width:100%;border-collapse:collapse"><tr>
        <td style="vertical-align:top">
            <div style="display:inline-block;width:42px;height:42px;line-height:42px;text-align:center;border-radius:11px;background:#4F46E5;color:#fff;font-weight:800;font-size:18px;vertical-align:middle">S</div>
            <span style="display:inline-block;vertical-align:middle;margin-left:10px">
                <strong style="font-size:17px">{{ $s['company_name'] }}</strong><br>
                <span style="color:#64748B;font-size:11.5px">SangaTV Plus — Sistem TV Hotel</span>
            </span>
            <div style="color:#475569;font-size:11.5px;margin-top:10px;white-space:pre-line">{{ trim($s['company_address']."\n".collect([$s['company_phone'], $s['company_email']])->filter()->implode(' · ').($s['company_npwp'] ? "\nNPWP ".$s['company_npwp'] : '')) }}</div>
        </td>
        <td style="vertical-align:top;text-align:right">
            <div style="font-size:26px;font-weight:800;letter-spacing:-.02em;color:#4F46E5">TAGIHAN</div>
            <div style="font-weight:700;font-size:14px">{{ $invoice->number }}</div>
            <div style="display:inline-block;margin-top:8px;padding:3px 10px;border:2px solid {{ $stamp[1] }};color:{{ $stamp[1] }};border-radius:6px;font-weight:800;font-size:11px;letter-spacing:.08em">{{ $stamp[0] }}</div>
        </td>
    </tr></table>

    <table style="width:100%;border-collapse:collapse;margin-top:28px"><tr>
        <td style="vertical-align:top;width:55%;padding-right:16px">
            <div style="font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:#64748B;font-weight:700">Ditagihkan kepada</div>
            <div style="font-weight:700;font-size:14px;margin-top:4px">{{ $h->billing_name ?: $h->name }}</div>
            <div style="color:#475569;white-space:pre-line">{{ trim(($h->billing_name ? $h->name."\n" : '').($h->billing_address ?: $h->city)) }}</div>
            @if ($h->npwp)<div style="color:#475569">NPWP {{ $h->npwp }}</div>@endif
            <div style="color:#475569">{{ collect([$h->contact_name, $h->billing_email ?: $h->contact_email])->filter()->implode(' · ') }}</div>
        </td>
        <td style="vertical-align:top">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                <tr><td style="color:#64748B;padding:2px 0">Tanggal terbit</td><td style="text-align:right;font-weight:600">{{ $invoice->issued_at->translatedFormat('d F Y') }}</td></tr>
                <tr><td style="color:#64748B;padding:2px 0">Jatuh tempo</td><td style="text-align:right;font-weight:600">{{ $invoice->due_at->translatedFormat('d F Y') }}</td></tr>
                <tr><td style="color:#64748B;padding:2px 0">Periode sewa</td><td style="text-align:right;font-weight:600">{{ $invoice->period_start->translatedFormat('d M Y') }} – {{ $invoice->period_end->translatedFormat('d M Y') }}</td></tr>
                <tr><td style="color:#64748B;padding:2px 0">Kode hotel</td><td style="text-align:right;font-weight:600">{{ $h->slug }}</td></tr>
                @if ($invoice->paid_at)
                    <tr><td style="color:#64748B;padding:2px 0">Dibayar</td><td style="text-align:right;font-weight:600;color:#059669">{{ $invoice->paid_at->setTimezone('Asia/Jakarta')->translatedFormat('d F Y') }}</td></tr>
                @endif
            </table>
        </td>
    </tr></table>

    <table style="width:100%;border-collapse:collapse;margin-top:26px">
        <thead><tr style="background:#F1F5F9">
            <th style="text-align:left;padding:10px 12px;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:#475569;border-radius:8px 0 0 8px">Uraian</th>
            <th style="text-align:center;padding:10px 12px;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:#475569">Jumlah</th>
            <th style="text-align:right;padding:10px 12px;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:#475569">Harga</th>
            <th style="text-align:right;padding:10px 12px;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:#475569;border-radius:0 8px 8px 0">Total</th>
        </tr></thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td style="padding:12px;border-bottom:1px solid #E2E8F0">{{ $item['description'] }}</td>
                <td style="padding:12px;border-bottom:1px solid #E2E8F0;text-align:center">{{ $item['qty'] }} {{ $item['unit'] ?? '' }}</td>
                <td style="padding:12px;border-bottom:1px solid #E2E8F0;text-align:right">{{ $rp($item['price']) }}</td>
                <td style="padding:12px;border-bottom:1px solid #E2E8F0;text-align:right;font-weight:600">{{ $rp($item['amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table style="width:100%;border-collapse:collapse;margin-top:14px"><tr>
        <td style="vertical-align:top;width:55%;padding-right:16px">
            @if (filled($s['bank_accounts']) && $invoice->status !== 'paid')
                <div style="font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:#64748B;font-weight:700">Pembayaran ke</div>
                <div style="white-space:pre-line;margin-top:4px;font-weight:600">{{ $s['bank_accounts'] }}</div>
                <div style="color:#64748B;font-size:11.5px;margin-top:4px">Cantumkan nomor tagihan {{ $invoice->number }} pada berita transfer.</div>
            @endif
            @if ($invoice->notes)
                <div style="margin-top:10px;color:#475569;white-space:pre-line"><strong>Catatan:</strong> {{ $invoice->notes }}</div>
            @endif
        </td>
        <td style="vertical-align:top">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <tr><td style="padding:4px 0;color:#64748B">Subtotal</td><td style="text-align:right">{{ $rp($invoice->subtotal) }}</td></tr>
                @if ($invoice->discount > 0)<tr><td style="padding:4px 0;color:#64748B">Diskon</td><td style="text-align:right;color:#059669">− {{ $rp($invoice->discount) }}</td></tr>@endif
                @if ($invoice->tax > 0)<tr><td style="padding:4px 0;color:#64748B">PPN {{ rtrim(rtrim(number_format($invoice->tax_percent, 2, ',', '.'), '0'), ',') }}%</td><td style="text-align:right">{{ $rp($invoice->tax) }}</td></tr>@endif
                <tr><td style="padding:10px 0 0;font-weight:800;font-size:15px;border-top:2px solid #0F172A">Total</td><td style="padding:10px 0 0;text-align:right;font-weight:800;font-size:17px;color:#4F46E5;border-top:2px solid #0F172A">{{ $rp($invoice->total) }}</td></tr>
            </table>
        </td>
    </tr></table>

    @if (filled($s['invoice_footer']))
        <div style="margin-top:34px;padding-top:14px;border-top:1px dashed #CBD5E1;color:#64748B;font-size:11.5px;text-align:center">{{ $s['invoice_footer'] }}</div>
    @endif
</div>
