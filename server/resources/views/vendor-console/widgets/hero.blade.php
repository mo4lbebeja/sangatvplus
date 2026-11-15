<x-filament-widgets::widget>
    <div class="vc-hero">
        <div style="position:relative;z-index:1">
            <h2>{{ $greeting }}{{ $name ? ', '.$name : '' }}</h2>
            <p>{{ $date }} · Ringkasan bisnis SangaTV Plus di semua hotel klien.</p>
        </div>
        <div class="vc-kpis">
            <div class="vc-kpi"><x-filament::icon icon="heroicon-o-banknotes" /><div><span>Pendapatan berulang / bulan</span><strong>{{ $mrr }}</strong></div></div>
            <div class="vc-kpi"><x-filament::icon icon="heroicon-o-building-office-2" /><div><span>Hotel berbayar</span><strong>{{ $hotels }}</strong></div></div>
            <div class="vc-kpi"><x-filament::icon icon="heroicon-o-tv" /><div><span>TV online</span><strong>{{ $online }} / {{ $tvs }}</strong></div></div>
        </div>
    </div>
</x-filament-widgets::widget>
