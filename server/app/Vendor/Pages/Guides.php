<?php

namespace App\Vendor\Pages;

use App\Models\Central\Hotel;
use App\Models\HotelProfile;
use App\Tenancy\HotelManager;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Guides\PmsGuide;
use App\Vendor\Guides\TechnicianSteps;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use UnitEnum;

/** Sijalucom-only guides (tabs): TV installation over ADB, and PMS integration checklist per hotel. */
class Guides extends Page
{
    use VendorAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Panduan';

    protected static ?string $title = 'Panduan';

    protected static ?string $slug = 'panduan';

    protected string $view = 'vendor-console.pages.guides';

    protected ?string $subheading = 'Hanya untuk tim Sijalucom: pemasangan TV dan integrasi PMS hotel.';

    #[Url]
    public string $tab = 'teknisi';

    #[Url]
    public string $ip = '';

    #[Url(as: 'hotel')]
    public ?int $hotelId = null;

    /** @var array<string, array{done: bool, note: string}> */
    public array $checklist = [];

    protected static function area(): string
    {
        return 'support';
    }

    public function mount(): void
    {
        $this->ip = filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $this->ip : '';
        $this->hotelId ??= Hotel::query()->orderBy('name')->value('id');
        $this->loadChecklist();
    }

    public function hotels(): array
    {
        return Hotel::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function hotel(): ?Hotel
    {
        return $this->hotelId ? Hotel::query()->find($this->hotelId) : null;
    }

    public function updatedHotelId(): void
    {
        $this->loadChecklist();
    }

    private function loadChecklist(): void
    {
        $saved = ($h = $this->hotel()) ? (array) rescue(fn () => app(HotelManager::class)->run($h, fn () => HotelProfile::current()->pms_checklist), [], false) : [];
        $this->checklist = [];
        foreach (array_keys(PmsGuide::questions()) as $k) {
            $this->checklist[$k] = ['done' => (bool) ($saved[$k]['done'] ?? false), 'note' => (string) ($saved[$k]['note'] ?? '')];
        }
    }

    /** Auto-save every change into the chosen hotel's profile. */
    public function updatedChecklist(): void
    {
        $hotel = $this->hotel();
        if (! $hotel) {
            return;
        }
        $clean = [];
        foreach (array_keys(PmsGuide::questions()) as $k) {
            $clean[$k] = ['done' => (bool) ($this->checklist[$k]['done'] ?? false), 'note' => Str::limit((string) ($this->checklist[$k]['note'] ?? ''), 1000, '')];
        }
        app(HotelManager::class)->run($hotel, function () use ($clean) {
            $p = HotelProfile::current();
            $p->pms_checklist = $clean;
            $p->save();
        });
        Notification::make()->title('Checklist tersimpan')->success()->duration(1500)->send();
    }

    public function doneCount(): int
    {
        return collect($this->checklist)->where('done', true)->count();
    }

    public function sections(): array
    {
        return TechnicianSteps::sections();
    }

    public function docHtml(): HtmlString
    {
        return new HtmlString(Str::markdown(PmsGuide::vendorMarkdown($this->hotel()?->name ?? 'Hotel'), ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }
}
