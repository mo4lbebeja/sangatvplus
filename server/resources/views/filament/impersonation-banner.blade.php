@if (auth()->check() && \App\Vendor\Impersonation::active())
    <div style="position:sticky;top:0;z-index:50;display:flex;gap:.75rem;align-items:center;justify-content:center;flex-wrap:wrap;padding:.5rem 1rem;background:#4f46e5;color:#fff;font-size:.875rem">
        <span>Mode dukungan Sijalucom ({{ session(\App\Vendor\Impersonation::SESSION) }}): Anda masuk sebagai <strong>{{ auth()->user()->name }}</strong> — semua perubahan tercatat.</span>
        <a href="{{ route('vendor.impersonation.stop') }}" style="background:#fff;color:#4f46e5;border-radius:.375rem;padding:.2rem .7rem;font-weight:600;text-decoration:none">Selesai</a>
    </div>
@endif
