<?php

namespace App\Vendor\Resources\Leads\Pages;

use App\Models\Central\Lead;
use App\Vendor\Billing;
use App\Vendor\CsvExport;
use App\Vendor\Resources\Leads\LeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ManageLeads extends ManageRecords
{
    protected static string $resource = LeadResource::class;

    public function getSubheading(): ?string
    {
        $open = Lead::query()->whereNotIn('stage', ['won', 'lost']);
        $won = Lead::query()->where('stage', 'won')->count();
        $closed = $won + Lead::query()->where('stage', 'lost')->count();

        return 'Pipeline: '.(clone $open)->count().' prospek aktif · nilai '.Billing::rupiah((clone $open)->sum('estimated_value')).'/bulan · konversi '
            .($closed ? round($won / $closed * 100) : 0).'%';
    }

    public function getTabs(): array
    {
        $count = fn (array $stages) => Lead::query()->whereIn('stage', $stages)->count();

        return [
            'open' => Tab::make('Aktif')->icon(Heroicon::OutlinedFunnel)->badge($count(['new', 'contacted', 'demo', 'negotiation']))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('stage', ['won', 'lost'])),
            'won' => Tab::make('Jadi klien')->icon(Heroicon::OutlinedTrophy)->badge($count(['won']))->modifyQueryUsing(fn (Builder $query) => $query->where('stage', 'won')),
            'lost' => Tab::make('Batal')->icon(Heroicon::OutlinedXCircle)->badge($count(['lost']))->modifyQueryUsing(fn (Builder $query) => $query->where('stage', 'lost')),
            'all' => Tab::make('Semua')->icon(Heroicon::OutlinedQueueList),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CsvExport::action('prospek', fn () => Lead::query()->with('owner'), [
                'Nama' => 'name', 'Kota' => 'city', 'Kontak' => 'contact_name', 'Telepon' => 'contact_phone', 'Email' => 'contact_email', 'Kamar' => 'rooms',
                'Tahap' => fn ($l) => Lead::STAGES[$l->stage] ?? $l->stage, 'Nilai/bulan' => 'estimated_value', 'Sumber' => 'source',
                'Follow up' => fn ($l) => $l->next_follow_up?->toDateString(), 'Ditangani' => 'owner.name', 'Alasan batal' => 'lost_reason',
            ]), CreateAction::make()->label('Prospek baru')->icon(Heroicon::OutlinedPlus)->modalWidth('3xl')->modalIcon(Heroicon::OutlinedFunnel)];
    }
}
