<?php

namespace App\Vendor\Widgets;

use App\Vendor\Billing;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Pendapatan 12 bulan';

    protected ?string $description = 'Tagihan lunas per bulan (Rp)';

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    protected ?string $maxHeight = '210px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $data = Billing::revenueByMonth(12);

        return [
            'datasets' => [[
                'label' => 'Pendapatan',
                'data' => array_values($data),
                'backgroundColor' => 'rgba(79, 70, 229, 0.85)',
                'borderRadius' => 6,
                'maxBarThickness' => 34,
            ]],
            'labels' => array_map(fn ($k) => Carbon::createFromFormat('Y-m', $k)->translatedFormat('M y'), array_keys($data)),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => 'Rp ' + Number(c.parsed.y).toLocaleString('id-ID') } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(100,116,139,.12)' }, ticks: { callback: (v) => v >= 1e6 ? (v / 1e6).toLocaleString('id-ID') + ' jt' : (v >= 1e3 ? (v / 1e3) + ' rb' : v) } },
                    x: { grid: { display: false } },
                },
            }
        JS);
    }
}
