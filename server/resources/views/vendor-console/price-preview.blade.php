<div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;padding:1rem 1.15rem;border-radius:14px;background:linear-gradient(135deg,#EEF2FF,#ECFEFF);border:1px solid rgba(99,102,241,.18)">
    <div style="display:flex;gap:.75rem;align-items:center">
        <span style="display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:12px;background:#4F46E5;color:#fff"><x-filament::icon icon="heroicon-o-calculator" style="width:1.3rem;height:1.3rem" /></span>
        <div>
            <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.08em;color:#6366F1;font-weight:700">Harga sewa</div>
            <div style="font-size:1.3rem;font-weight:800;letter-spacing:-.02em;color:#1E1B4B">{{ \App\Vendor\Billing::rupiah($net) }} <span style="font-size:.85rem;font-weight:600;color:#64748B">/ bulan</span></div>
        </div>
    </div>
    <div style="text-align:right;font-size:.82rem;color:#475569">
        Per tagihan ({{ $months }} bulan): <strong style="color:#1E1B4B">{{ \App\Vendor\Billing::rupiah($net * $months) }}</strong>
        @if ($gross !== $net)<br><span style="color:#059669">hemat {{ \App\Vendor\Billing::rupiah(($gross - $net) * $months) }} dari diskon</span>@endif
    </div>
</div>
