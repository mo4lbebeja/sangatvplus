<?php

namespace App\Vendor\Widgets;

use App\Models\Central\Hotel;
use App\Vendor\HotelStats;
use App\Vendor\Resources\Hotels\HotelResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

class FleetHealth extends TableWidget
{
    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Kesehatan armada TV')
            ->description('TV online, versi & backup per hotel')
            ->query(Hotel::query()->where('status', 'active')->orderBy('name'))
            ->columns([
                TextColumn::make('name')->label('Hotel')->weight('semibold')
                    ->description(fn (Hotel $h) => collect(HotelStats::for($h)['versions'])->keys()->map(fn ($v) => "v{$v}")->implode(' · ') ?: 'belum ada TV'),
                TextColumn::make('online')->label('Online')->badge()
                    ->state(fn (Hotel $h) => ($s = HotelStats::for($h))['ok'] ? "{$s['online']}/{$s['tvs']}" : 'galat')
                    ->color(fn (Hotel $h) => ($s = HotelStats::for($h))['ok'] && $s['tvs'] > 0 ? ($s['online'] === $s['tvs'] ? 'success' : ($s['online'] === 0 ? 'danger' : 'warning')) : 'gray'),
                TextColumn::make('backup')->label('Backup')
                    ->state(fn (Hotel $h) => ($t = HotelStats::for($h)['backup']) ? Carbon::createFromTimestamp($t)->diffForHumans(short: true) : 'belum')
                    ->color(fn (Hotel $h) => ($t = HotelStats::for($h)['backup']) && $t > now()->subHours(36)->getTimestamp() ? 'success' : 'danger'),
            ])
            ->recordUrl(fn (Hotel $h) => HotelResource::getUrl('view', ['record' => $h]))
            ->paginated([5]);
    }
}
