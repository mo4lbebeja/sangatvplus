<?php

namespace App\Vendor\Resources\FieldJobs\Pages;

use App\Models\Central\FieldJob;
use App\Models\Central\VendorActivity;
use App\Vendor\CsvExport;
use App\Vendor\Resources\FieldJobs\FieldJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ManageFieldJobs extends ManageRecords
{
    protected static string $resource = FieldJobResource::class;

    protected ?string $subheading = 'Jadwal instalasi, perbaikan, dan perawatan ke hotel.';

    public function getTabs(): array
    {
        $today = now('Asia/Jakarta');

        return [
            'upcoming' => Tab::make('Akan datang')->icon(Heroicon::OutlinedCalendarDays)->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['scheduled', 'in_progress'])),
            'today' => Tab::make('Hari ini')->icon(Heroicon::OutlinedSun)->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('scheduled_at', [$today->copy()->startOfDay()->utc(), $today->copy()->endOfDay()->utc()])),
            'done' => Tab::make('Selesai')->icon(Heroicon::OutlinedCheckCircle)->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['done', 'cancelled'])->reorder('scheduled_at', 'desc')),
            'all' => Tab::make('Semua')->icon(Heroicon::OutlinedQueueList),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [CsvExport::action('kunjungan', fn () => FieldJob::query()->with('hotel'), ['Jadwal' => fn ($j) => $j->scheduled_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'), 'Hotel' => 'hotel.name', 'Jenis' => 'type', 'Pekerjaan' => 'title', 'Teknisi' => 'technician.name', 'Status' => 'status', 'Hasil' => 'result']), CreateAction::make()->label('Jadwalkan kunjungan')->icon(Heroicon::OutlinedPlus)->modalWidth('3xl')->modalIcon(Heroicon::OutlinedTruck)
            ->after(fn (FieldJob $record) => VendorActivity::record('field.scheduled', "Kunjungan dijadwalkan: {$record->title} (".$record->scheduled_at->setTimezone('Asia/Jakarta')->translatedFormat('d M H:i').')', $record->hotel))];
    }
}
