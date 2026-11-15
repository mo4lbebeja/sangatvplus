<?php

namespace App\Vendor\Resources\Leads;

use App\Models\Central\Lead;
use App\Models\Central\Plan;
use App\Models\Central\VendorUser;
use App\Tenancy\HotelManager;
use App\Vendor\Billing;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Onboarding;
use App\Vendor\Resources\Hotels\HotelResource;
use App\Vendor\Resources\Leads\Pages\ManageLeads;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;
use UnitEnum;

class LeadResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'prospek';

    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static string|UnitEnum|null $navigationGroup = 'Klien';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'prospek';

    protected static ?string $pluralModelLabel = 'Prospek';

    protected static ?string $recordTitleAttribute = 'name';

    public const COLORS = ['new' => 'gray', 'contacted' => 'info', 'demo' => 'primary', 'negotiation' => 'warning', 'won' => 'success', 'lost' => 'danger'];

    public const ICONS = ['new' => Heroicon::OutlinedSparkles, 'contacted' => Heroicon::OutlinedPhone, 'demo' => Heroicon::OutlinedPresentationChartBar,
        'negotiation' => Heroicon::OutlinedScale, 'won' => Heroicon::OutlinedTrophy, 'lost' => Heroicon::OutlinedXCircle];

    protected static function area(): string
    {
        return 'support';
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Lead::query()->whereNotIn('stage', ['won', 'lost'])->whereDate('next_follow_up', '<=', now('Asia/Jakarta')->toDateString())->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Perlu di-follow up hari ini';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nama hotel')->prefixIcon(Heroicon::OutlinedBuildingOffice)->required()->maxLength(160),
            TextInput::make('city')->label('Kota')->prefixIcon(Heroicon::OutlinedMapPin)->maxLength(80),
            TextInput::make('contact_name')->label('Kontak')->prefixIcon(Heroicon::OutlinedUser)->maxLength(120),
            TextInput::make('contact_phone')->label('Telepon / WhatsApp')->prefixIcon(Heroicon::OutlinedPhone)->tel()->maxLength(32),
            TextInput::make('contact_email')->label('Email')->prefixIcon(Heroicon::OutlinedEnvelope)->email(),
            TextInput::make('rooms')->label('Jumlah kamar')->prefixIcon(Heroicon::OutlinedHome)->numeric()->minValue(1),
            Select::make('stage')->label('Tahap')->prefixIcon(Heroicon::OutlinedFunnel)->options(Lead::STAGES)->default('new')->required(),
            TextInput::make('estimated_value')->label('Perkiraan nilai / bulan')->prefixIcon(Heroicon::OutlinedBanknotes)->numeric()->prefix('Rp'),
            Select::make('source')->label('Sumber')->prefixIcon(Heroicon::OutlinedMegaphone)->options(HotelResource::SOURCES),
            DatePicker::make('next_follow_up')->label('Follow up berikutnya')->prefixIcon(Heroicon::OutlinedCalendarDays)->native(false)->displayFormat('d M Y'),
            Select::make('owner_id')->label('Ditangani')->prefixIcon(Heroicon::OutlinedUserCircle)->options(fn () => VendorUser::query()->where('is_active', true)->pluck('name', 'id'))
                ->default(fn () => Filament::auth()->id()),
            Textarea::make('notes')->label('Catatan')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Prospek')->weight('bold')->searchable(['name', 'city', 'contact_name'])
                    ->description(fn (Lead $l) => collect([$l->city, $l->rooms ? "{$l->rooms} kamar" : null])->filter()->implode(' · ')),
                TextColumn::make('contact_name')->label('Kontak')->icon(Heroicon::OutlinedUser)->placeholder('–')->description(fn (Lead $l) => $l->contact_phone),
                TextColumn::make('stage')->label('Tahap')->badge()->formatStateUsing(fn ($state) => Lead::STAGES[$state] ?? $state)
                    ->color(fn ($state) => self::COLORS[$state] ?? 'gray')->icon(fn ($state) => self::ICONS[$state] ?? null),
                TextColumn::make('estimated_value')->label('Nilai / bulan')->icon(Heroicon::OutlinedBanknotes)->formatStateUsing(fn ($state) => $state ? Billing::rupiah($state) : '–')->sortable(),
                TextColumn::make('next_follow_up')->label('Follow up')->date('d M Y')->icon(Heroicon::OutlinedCalendarDays)->sortable()->placeholder('–')
                    ->color(fn (Lead $l) => $l->next_follow_up && $l->next_follow_up->lte(now('Asia/Jakarta')->startOfDay()) && ! in_array($l->stage, ['won', 'lost'], true) ? 'danger' : null),
                TextColumn::make('owner.name')->label('Ditangani')->placeholder('–'),
            ])
            ->filters([SelectFilter::make('owner_id')->label('Ditangani')->relationship('owner', 'name'), SelectFilter::make('source')->label('Sumber')->options(HotelResource::SOURCES)])
            ->defaultSort('next_follow_up')
            ->recordActions([
                EditAction::make()->modalWidth('3xl'),
                ActionGroup::make([
                    ...collect(['contacted', 'demo', 'negotiation'])->map(fn ($st) => Action::make('to_'.$st)->label('→ '.Lead::STAGES[$st])->icon(self::ICONS[$st])
                        ->visible(fn (Lead $l) => ! in_array($l->stage, ['won', 'lost', $st], true))->action(fn (Lead $l) => $l->update(['stage' => $st])))->all(),
                    Action::make('convert')->label('Jadikan klien')->icon(Heroicon::OutlinedTrophy)->color('success')
                        ->visible(fn (Lead $l) => $l->stage !== 'won' && HotelResource::allowed('owner'))
                        ->modalIcon(Heroicon::OutlinedTrophy)->modalDescription('Membuat hotel klien baru (database, admin) dari data prospek ini.')
                        ->schema([
                            TextInput::make('slug')->label('Kode Hotel')->required()->maxLength(40)->rule(fn () => fn ($a, $v, $fail) => HotelManager::validSlug(strtolower(trim((string) $v))) ? null : $fail('Kode tidak valid / sudah dipakai.')),
                            TextInput::make('admin_email')->label('Email admin hotel')->email()->required()->default(fn (Lead $l) => $l->contact_email),
                            Select::make('plan_id')->label('Paket')->options(fn () => Plan::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id'))->required(),
                            TextInput::make('tv_quota')->label('Kuota TV')->numeric()->minValue(1)->default(fn (Lead $l) => $l->rooms),
                            Select::make('activation')->label('Mulai')->options(['trial' => 'Uji coba 14 hari', 'unpaid' => 'Aktif + tagihan 1 bulan', 'later' => 'Belum aktif'])->default('trial')->required(),
                        ])
                        ->action(function (Lead $l, array $data) {
                            try {
                                $r = Onboarding::create($data + ['name' => $l->name, 'city' => $l->city, 'contact_name' => $l->contact_name, 'contact_phone' => $l->contact_phone,
                                    'contact_email' => $l->contact_email, 'source' => $l->source, 'months' => 1, 'billing_months' => 1]);
                                $l->update(['stage' => 'won', 'hotel_id' => $r['hotel']->id]);
                                Notification::make()->title("{$l->name} menjadi klien")->body("Kode {$r['hotel']->slug} · {$data['admin_email']} · sandi sementara: {$r['password']}. {$r['note']}")->persistent()->success()->send();
                            } catch (Throwable $e) {
                                Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                            }
                        }),
                    Action::make('lose')->label('Tandai batal')->icon(Heroicon::OutlinedXCircle)->color('danger')
                        ->visible(fn (Lead $l) => ! in_array($l->stage, ['won', 'lost'], true))
                        ->schema([TextInput::make('lost_reason')->label('Alasan')->required()->maxLength(160)->placeholder('mis. harga, sudah pakai vendor lain')])
                        ->action(fn (Lead $l, array $data) => $l->update(['stage' => 'lost', 'lost_reason' => $data['lost_reason']])),
                    DeleteAction::make(),
                ])->tooltip('Aksi'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedFunnel)->emptyStateHeading('Belum ada prospek')->emptyStateDescription('Catat hotel calon klien dan jadwalkan follow up.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageLeads::route('/')];
    }
}
