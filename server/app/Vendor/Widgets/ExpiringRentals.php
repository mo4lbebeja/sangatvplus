<?php

namespace App\Vendor\Widgets;

use App\Models\Central\Hotel;
use App\Vendor\Billing;
use App\Vendor\Resources\Hotels\HotelResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ExpiringRentals extends TableWidget
{
    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sewa segera habis')
            ->description('Berakhir ≤ 30 hari atau sudah lewat')
            ->query(Hotel::query()->with('plan')->whereNotNull('service_expires_at')->whereNull('service_released_at')
                ->where('service_expires_at', '<=', now()->addDays(30))->orderBy('service_expires_at'))
            ->columns([
                TextColumn::make('name')->label('Hotel')->weight('semibold')->description(fn (Hotel $h) => Billing::rupiah($h->monthlyPrice()).'/bln'),
                TextColumn::make('days')->label('Sisa')->badge()
                    ->state(fn (Hotel $h) => ($d = $h->daysLeft()) < 0 ? 'lewat '.abs($d).' hr' : ($d === 0 ? 'hari ini' : "{$d} hari"))
                    ->color(fn (Hotel $h) => $h->daysLeft() <= 7 ? 'danger' : 'warning'),
            ])
            ->recordUrl(fn (Hotel $h) => HotelResource::getUrl('view', ['record' => $h]))
            ->emptyStateHeading('Tidak ada sewa yang segera habis')->emptyStateDescription(null)
            ->paginated([5]);
    }
}
