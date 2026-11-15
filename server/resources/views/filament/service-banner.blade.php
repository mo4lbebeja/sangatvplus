@php
    $hotel = \App\Models\HotelProfile::current();
    $status = $hotel->serviceStatus();
    $until = $hotel->service_expires_at?->setTimezone($hotel->timezone ?: 'Asia/Jakarta');
@endphp
@if (auth()->check() && $status !== 'active')
    <div @class(['sg-service-banner', 'is-released' => $status === 'released'])>
        <x-filament::icon :icon="$status === 'released' ? 'heroicon-o-power' : 'heroicon-o-clock'" class="sg-service-banner-icon" />
        <div>
            @if ($status === 'released')
                <strong>Layanan SangaTV Plus dilepas.</strong>
                Semua TV sudah kembali ke layar utama bawaan TV. Data masih bisa diekspor di Profil Hotel.
            @else
                <strong>Layanan SangaTV Plus berakhir {{ $until->translatedFormat('d F Y') }}</strong>
                ({{ $until->diffForHumans() }}). Setelah itu TV kembali ke layar utama bawaan TV. Hubungi Sijalucom untuk perpanjangan.
            @endif
        </div>
    </div>
@endif
