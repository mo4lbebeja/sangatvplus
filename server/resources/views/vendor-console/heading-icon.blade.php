@php
    $name = (string) request()->route()?->getName();
    $map = [
        'pages.dashboard' => ['heroicon-o-squares-2x2', 'indigo'],
        'resources.hotel' => ['heroicon-o-building-office-2', 'indigo'],
        'resources.paket' => ['heroicon-o-rectangle-stack', 'violet'],
        'resources.tagihan' => ['heroicon-o-document-currency-dollar', 'emerald'],
        'resources.tiket' => ['heroicon-o-lifebuoy', 'rose'],
        'resources.pengumuman' => ['heroicon-o-megaphone', 'amber'],
        'resources.rilis' => ['heroicon-o-rocket-launch', 'violet'],
        'pages.panduan' => ['heroicon-o-book-open', 'cyan'],
        'pages.laporan' => ['heroicon-o-presentation-chart-line', 'sky'],
        'pages.pengaturan' => ['heroicon-o-cog-6-tooth', 'slate'],
        'resources.tim' => ['heroicon-o-user-group', 'cyan'],
        'resources.aktivitas' => ['heroicon-o-clipboard-document-list', 'slate'],
        'auth.profile' => ['heroicon-o-user-circle', 'indigo'],
    ];
    $hit = collect($map)->first(fn ($v, $k) => str_contains($name, '.'.$k));
@endphp
@if ($hit)
    <span class="vc-head-icon vc-tone-{{ $hit[1] }}"><x-filament::icon :icon="$hit[0]" /></span>
@endif
