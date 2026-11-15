<?php

namespace App\Vendor\Resources\Assets\Pages;

use App\Models\Central\Asset;
use App\Vendor\Billing;
use App\Vendor\CsvExport;
use App\Vendor\Resources\Assets\AssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageAssets extends ManageRecords
{
    protected static string $resource = AssetResource::class;

    public function getSubheading(): ?string
    {
        return Asset::query()->where('status', 'installed')->count().' terpasang · '.Asset::query()->where('status', 'in_stock')->count().' di gudang · '
            .Asset::query()->where('status', 'repair')->count().' perbaikan · nilai aset '.Billing::rupiah(Asset::query()->where('status', '!=', 'retired')->sum('price'));
    }

    protected function getHeaderActions(): array
    {
        return [CsvExport::action('inventaris', fn () => Asset::query()->with('hotel'), ['Jenis' => 'type', 'Merek' => 'brand', 'Model' => 'model', 'Seri' => 'serial', 'Status' => 'status', 'Hotel' => 'hotel.name', 'Lokasi' => 'location', 'Garansi' => fn ($a) => $a->warranty_until?->toDateString(), 'Harga' => 'price']), CreateAction::make()->label('Tambah perangkat')->icon(Heroicon::OutlinedPlus)->modalWidth('3xl')->modalIcon(Heroicon::OutlinedArchiveBox)];
    }
}
