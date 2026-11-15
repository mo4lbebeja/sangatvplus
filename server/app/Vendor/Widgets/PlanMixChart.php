<?php

namespace App\Vendor\Widgets;

use App\Models\Central\Hotel;
use Filament\Widgets\ChartWidget;

class PlanMixChart extends ChartWidget
{
    protected ?string $heading = 'Komposisi pendapatan';

    protected ?string $description = 'MRR per paket';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $mix = Hotel::query()->with('plan')->get()->filter->isBillable()
            ->groupBy(fn (Hotel $h) => $h->custom_monthly_price !== null ? 'Harga khusus' : ($h->plan?->name ?? 'Tanpa paket'))
            ->map(fn ($g) => $g->sum(fn (Hotel $h) => $h->monthlyPrice()));

        return [
            'datasets' => [['data' => $mix->values()->all(), 'backgroundColor' => ['#4F46E5', '#06B6D4', '#10B981', '#F59E0B', '#F43F5E', '#8B5CF6'], 'borderWidth' => 0]],
            'labels' => $mix->keys()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return ['cutout' => '68%', 'plugins' => ['legend' => ['position' => 'bottom']], 'scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }
}
