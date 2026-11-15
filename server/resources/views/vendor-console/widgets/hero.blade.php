<x-filament-widgets::widget>
    <div class="vc-hero">
        <div>
            <h2>{{ $greeting }}{{ $name ? ', '.$name : '' }} 👋</h2>
            <p>{{ $date }} · Ringkasan bisnis SangaTV Plus di semua hotel klien.</p>
        </div>
        <div class="vc-kpis">
            <div class="vc-kpi"><span>Pendapatan berulang / bulan</span><strong>{{ $mrr }}</strong></div>
            <div class="vc-kpi"><span>Hotel berbayar</span><strong>{{ $hotels }}</strong></div>
            <div class="vc-kpi"><span>TV online</span><strong>{{ $online }} / {{ $tvs }}</strong></div>
        </div>
    </div>
</x-filament-widgets::widget>
