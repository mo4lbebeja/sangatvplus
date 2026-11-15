@php
    $s = \App\Models\Central\VendorSetting::allSettings();
    $h = $invoice->hotel;
    $paid = $invoice->paid_at?->setTimezone('Asia/Jakarta');
@endphp
<div style="font-family:'Plus Jakarta Sans',ui-sans-serif,system-ui,sans-serif;color:#0F172A;background:#fff;max-width:820px;margin:0 auto;padding:36px 44px;border-radius:14px;font-size:13px;line-height:1.55;position:relative;overflow:hidden">
    <div style="position:absolute;right:-40px;top:40px;transform:rotate(18deg);border:4px solid #05966933;color:#05966944;font-size:54px;font-weight:900;padding:4px 30px;letter-spacing:.1em">LUNAS</div>
    <table style="width:100%;border-collapse:collapse"><tr>
        <td><strong style="font-size:17px">{{ $s['company_name'] }}</strong><br><span style="color:#64748B;font-size:11.5px;white-space:pre-line">{{ trim($s['company_address']."\n".collect([$s['company_phone'], $s['company_email']])->filter()->implode(' · ')) }}</span></td>
        <td style="text-align:right;vertical-align:top"><div style="font-size:26px;font-weight:800;color:#059669;letter-spacing:.04em">KWITANSI</div>
            <div style="font-weight:700">No. {{ str_replace(['INV', 'inv'], 'KW', $invoice->number) }}</div><div style="color:#64748B;font-size:11.5px">untuk tagihan {{ $invoice->number }}</div></td>
    </tr></table>
    <table style="width:100%;border-collapse:collapse;margin-top:26px;font-size:13.5px">
        <tr><td style="width:190px;padding:9px 0;color:#64748B;border-bottom:1px dashed #CBD5E1">Telah terima dari</td><td style="padding:9px 0;font-weight:700;border-bottom:1px dashed #CBD5E1">{{ $h->billing_name ?: $h->name }}</td></tr>
        <tr><td style="padding:9px 0;color:#64748B;border-bottom:1px dashed #CBD5E1">Uang sejumlah</td><td style="padding:9px 0;font-style:italic;border-bottom:1px dashed #CBD5E1;text-transform:capitalize">{{ \App\Vendor\Billing::terbilang($invoice->total) }}</td></tr>
        <tr><td style="padding:9px 0;color:#64748B;border-bottom:1px dashed #CBD5E1">Untuk pembayaran</td><td style="padding:9px 0;border-bottom:1px dashed #CBD5E1">{{ collect($invoice->items)->pluck('description')->implode('; ') }}</td></tr>
        <tr><td style="padding:9px 0;color:#64748B;border-bottom:1px dashed #CBD5E1">Metode</td><td style="padding:9px 0;border-bottom:1px dashed #CBD5E1">{{ \App\Models\Central\Invoice::METHODS[$invoice->payment_method] ?? $invoice->payment_method }}{{ $invoice->payment_ref ? ' · ref '.$invoice->payment_ref : '' }}</td></tr>
    </table>
    <table style="width:100%;border-collapse:collapse;margin-top:26px"><tr>
        <td style="vertical-align:bottom"><div style="display:inline-block;padding:10px 18px;border-radius:12px;background:#ECFDF5;border:2px solid #059669;font-size:22px;font-weight:800;color:#047857">{{ \App\Vendor\Billing::rupiah($invoice->total) }}</div></td>
        <td style="text-align:center;width:260px">{{ $paid?->translatedFormat('d F Y') }}<br><span style="color:#64748B;font-size:11.5px">{{ $s['company_name'] }}</span><div style="height:60px"></div><div style="border-top:1px solid #0F172A;padding-top:4px;font-size:11.5px;color:#64748B">Tanda tangan & cap</div></td>
    </tr></table>
    @if ($invoice->tax_number)<div style="margin-top:14px;color:#64748B;font-size:11.5px">No. seri faktur pajak: {{ $invoice->tax_number }}</div>@endif
</div>
