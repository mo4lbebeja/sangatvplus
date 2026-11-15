<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $invoice->number }} — {{ $invoice->hotel->name }}</title>
<link rel="stylesheet" href="{{ asset('css/sangatv-fonts.css') }}">
<style>
    body { margin: 0; background: #E2E8F0; padding: 24px 12px; }
    .bar { max-width: 820px; margin: 0 auto 14px; display: flex; justify-content: flex-end; gap: 8px; font-family: system-ui, sans-serif; }
    .bar button { background: #4F46E5; color: #fff; border: 0; border-radius: 8px; padding: 9px 16px; font-weight: 600; cursor: pointer; }
    @media print { body { background: #fff; padding: 0; } .bar { display: none; } @page { size: A4; margin: 12mm; } }
</style></head>
<body>
<div class="bar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
@include('vendor-console.invoice-document', ['invoice' => $invoice])
</body></html>
