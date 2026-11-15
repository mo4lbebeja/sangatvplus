<?php

namespace App\Vendor\Pages;

use App\Models\Central\Hotel;
use App\Models\Central\Invoice;
use App\Vendor\Billing;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\HotelStats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/** Business reports: revenue per month and per hotel, receivables, TV usage per hotel. */
class Reports extends Page
{
    use VendorAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Analisa';

    protected static ?string $navigationLabel = 'Laporan Bisnis';

    protected static ?string $title = 'Laporan Bisnis';

    protected static ?string $slug = 'laporan';

    protected string $view = 'vendor-console.pages.reports';

    #[Url]
    public ?int $year = null;

    protected static function area(): string
    {
        return 'finance';
    }

    public function mount(): void
    {
        $this->year ??= (int) now(Billing::TZ)->year;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')->label('Ekspor tagihan '.$this->year.' (CSV)')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->url(fn () => route('vendor.report.invoices', ['tahun' => $this->year]), shouldOpenInNewTab: true),
        ];
    }

    protected function getViewData(): array
    {
        $tz = Billing::TZ;
        $paid = Invoice::query()->with('hotel')->where('status', 'paid')->get()
            ->filter(fn (Invoice $i) => (int) $i->paid_at->setTimezone($tz)->year === $this->year);

        $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => ['label' => now($tz)->setDate($this->year, $m, 1)->translatedFormat('F'), 'total' => 0, 'count' => 0]])->all();
        foreach ($paid as $i) {
            $m = (int) $i->paid_at->setTimezone($tz)->month;
            $months[$m]['total'] += $i->total;
            $months[$m]['count']++;
        }

        $total = $paid->sum('total');
        $byHotel = $paid->groupBy('hotel_id')->map(fn ($g) => [
            'hotel' => $g->first()->hotel, 'total' => $g->sum('total'), 'invoices' => $g->count(), 'months' => $g->sum('months'),
        ])->sortByDesc('total')->values();

        $hotels = Hotel::query()->with('plan')->orderBy('name')->get();
        $usage = $hotels->where('status', 'active')->map(function (Hotel $h) {
            $s = HotelStats::for($h);
            $d = HotelStats::details($h);

            return ['hotel' => $h, 'tvs' => $s['tvs'], 'online' => $s['online'], 'quota' => $h->tv_quota, 'hours' => $d['watch_hours'],
                'top' => $d['top_channels'][0]['name'] ?? '–', 'guests' => $d['guests_in_house'], 'ok' => $s['ok']];
        });

        $years = range((int) now($tz)->year, max(2026, (int) now($tz)->year - 4));

        return [
            'years' => $years,
            'months' => $months,
            'max' => max(1, collect($months)->max('total')),
            'total' => $total,
            'byHotel' => $byHotel,
            'usage' => $usage,
            'mrr' => Billing::mrr($hotels),
            'outstanding' => Invoice::query()->where('status', 'unpaid')->sum('total'),
            'overdue' => Invoice::query()->overdue()->sum('total'),
            'arpa' => $hotels->filter->isBillable()->count() ? (int) round(Billing::mrr($hotels) / $hotels->filter->isBillable()->count()) : 0,
            'rp' => fn ($v) => Billing::rupiah($v),
        ];
    }
}
