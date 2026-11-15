@php
    $hotel = app(\App\Tenancy\HotelManager::class)->current();
    $notices = ($hotel && auth()->check()) ? rescue(fn () => \App\Models\Central\Notice::activeFor($hotel), collect(), false) : collect();
    $tones = ['info' => ['#EFF6FF', '#1D4ED8', 'heroicon-o-information-circle'], 'success' => ['#ECFDF5', '#047857', 'heroicon-o-sparkles'],
        'warning' => ['#FFFBEB', '#B45309', 'heroicon-o-exclamation-triangle'], 'maintenance' => ['#FFF1F2', '#BE123C', 'heroicon-o-wrench-screwdriver']];
@endphp
@foreach ($notices as $n)
    @php([$bg, $fg, $icon] = $tones[$n->level] ?? $tones['info'])
    <div style="display:flex;gap:.75rem;align-items:flex-start;padding:.85rem 1rem;border-radius:12px;margin-bottom:1rem;background:{{ $bg }};color:{{ $fg }};border:1px solid {{ $fg }}22">
        <x-filament::icon :icon="$icon" style="width:1.35rem;height:1.35rem;flex:none;margin-top:.05rem" />
        <div style="font-size:.88rem;line-height:1.45"><strong>{{ $n->title }}</strong>@if ($n->body)<br><span style="white-space:pre-line">{{ $n->body }}</span>@endif
            <div style="font-size:.72rem;opacity:.75;margin-top:.2rem">Pengumuman dari Sijalucom{{ $n->ends_at ? ' · sampai '.$n->ends_at->setTimezone('Asia/Jakarta')->translatedFormat('d M Y H:i') : '' }}</div></div>
    </div>
@endforeach
