<?php

namespace App\Vendor\Resources\Hotels;

use App\Models\Central\Hotel;
use App\Models\Central\Plan;
use App\Vendor\Billing;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\Hotels\Pages\ManageHotels;
use App\Vendor\Resources\Hotels\Pages\ViewHotel;
use App\Vendor\Resources\Hotels\RelationManagers\ActivitiesRelationManager;
use App\Vendor\Resources\Hotels\RelationManagers\InvoicesRelationManager;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class HotelResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'hotel';

    protected static ?string $model = Hotel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Klien';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'hotel';

    protected static ?string $pluralModelLabel = 'Hotel Klien';

    protected static ?string $recordTitleAttribute = 'name';

    protected static function area(): string
    {
        return 'any';
    }

    public static function allowed(?string $area = null): bool
    {
        $user = static::vendor();

        return $user !== null && ($area === null || $area === 'any' ? true : $user->hasAccess($area));
    }

    public const SOURCES = ['referral' => 'Rekomendasi', 'sales' => 'Kunjungan sales', 'website' => 'Website / medsos', 'partner' => 'Mitra / vendor PMS', 'event' => 'Pameran / acara', 'other' => 'Lainnya'];

    public static function getNavigationBadge(): ?string
    {
        $n = Hotel::query()->whereNotNull('service_expires_at')->whereNull('service_released_at')->where('service_expires_at', '<=', now()->addDays(14))->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Plan, quota and price fields (create wizard + edit). */
    public static function subscriptionFields(): array
    {
        return [
            Select::make('plan_id')->label('Paket')->prefixIcon(Heroicon::OutlinedRectangleStack)
                ->options(fn () => Plan::query()->where('is_active', true)->orderBy('sort')->get()
                    ->mapWithKeys(fn (Plan $p) => [$p->id => "{$p->name} · ".Billing::rupiah($p->base_price).'/bln']))
                ->live()->required(fn (Get $get) => blank($get('custom_monthly_price'))),
            TextInput::make('tv_quota')->label('Kuota TV (kontrak)')->prefixIcon(Heroicon::OutlinedTv)->numeric()->minValue(1)->suffix('TV')->live(onBlur: true)
                ->helperText('Kosong = tanpa batas.'),
            Select::make('billing_months')->label('Siklus tagihan')->prefixIcon(Heroicon::OutlinedArrowPath)->options(Billing::CYCLES)->default(1)->required()->live(),
            TextInput::make('discount_percent')->label('Diskon')->prefixIcon(Heroicon::OutlinedReceiptPercent)->numeric()->suffix('%')->minValue(0)->maxValue(100)->default(0)->live(onBlur: true),
            TextInput::make('custom_monthly_price')->label('Harga khusus / bulan (opsional)')->prefixIcon(Heroicon::OutlinedBanknotes)->numeric()->prefix('Rp')->minValue(0)->live(onBlur: true)
                ->helperText('Mengganti harga paket.'),
            Toggle::make('auto_invoice')->label('Tagihan otomatis sebelum sewa habis')->default(true)->inline(false)->onIcon(Heroicon::Bolt),
            Placeholder::make('price_preview')->hiddenLabel()->columnSpanFull()
                ->content(function (Get $get) {
                    $plan = ($id = $get('plan_id')) ? Plan::query()->find($id) : null;
                    $gross = filled($get('custom_monthly_price')) ? (int) $get('custom_monthly_price') : ($plan?->monthlyFor((int) $get('tv_quota')) ?? 0);
                    $net = (int) round($gross * (1 - ((float) $get('discount_percent')) / 100));
                    $months = (int) ($get('billing_months') ?: 1);

                    return new HtmlString(view('vendor-console.price-preview', ['net' => $net, 'gross' => $gross, 'months' => $months, 'plan' => $plan])->render());
                }),
        ];
    }

    public static function contactFields(): array
    {
        return [
            TextInput::make('city')->label('Kota')->prefixIcon(Heroicon::OutlinedMapPin)->maxLength(80),
            TextInput::make('contact_name')->label('Nama PIC hotel')->prefixIcon(Heroicon::OutlinedUser)->maxLength(120),
            TextInput::make('contact_phone')->label('Telepon / WhatsApp PIC')->prefixIcon(Heroicon::OutlinedPhone)->tel()->maxLength(32),
            TextInput::make('contact_email')->label('Email PIC')->prefixIcon(Heroicon::OutlinedEnvelope)->email()->maxLength(160),
            Select::make('source')->label('Sumber klien')->prefixIcon(Heroicon::OutlinedMegaphone)->options(self::SOURCES),
        ];
    }

    public static function billingFields(): array
    {
        return [
            TextInput::make('billing_name')->label('Nama di tagihan (badan usaha)')->prefixIcon(Heroicon::OutlinedBuildingLibrary)->maxLength(160),
            TextInput::make('billing_email')->label('Email penagihan')->prefixIcon(Heroicon::OutlinedAtSymbol)->email()->maxLength(160)
                ->helperText('Tagihan otomatis dikirim ke sini.'),
            TextInput::make('npwp')->label('NPWP')->prefixIcon(Heroicon::OutlinedIdentification)->maxLength(32),
            Textarea::make('billing_address')->label('Alamat penagihan')->rows(3)->columnSpanFull(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->contained(false)->tabs([
                Tab::make('Identitas & kontak')->icon(Heroicon::OutlinedBuildingOffice2)->columns(2)->schema([
                    TextInput::make('name')->label('Nama hotel')->prefixIcon(Heroicon::OutlinedBuildingOffice)->required()->maxLength(120),
                    ...static::contactFields(),
                ]),
                Tab::make('Langganan')->icon(Heroicon::OutlinedCreditCard)->columns(2)->schema(static::subscriptionFields())
                    ->visible(fn () => static::allowed('finance')),
                Tab::make('Penagihan')->icon(Heroicon::OutlinedDocumentText)->columns(2)->schema(static::billingFields())
                    ->visible(fn () => static::allowed('finance')),
                Tab::make('Catatan')->icon(Heroicon::OutlinedPencilSquare)->schema([
                    Textarea::make('notes')->label('Catatan internal Sijalucom (tidak terlihat hotel)')->rows(6),
                ]),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('header')->hiddenLabel()->view('vendor-console.hotel-header')->columnSpanFull(),
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make('Langganan')->icon(Heroicon::OutlinedCreditCard)->columns(2)->columnSpan(1)->schema([
                    TextEntry::make('plan.name')->label('Paket')->icon(Heroicon::OutlinedRectangleStack)->iconColor('primary')->placeholder(fn (Hotel $h) => $h->custom_monthly_price !== null ? 'Harga khusus' : '–'),
                    TextEntry::make('tv_quota')->label('Kuota TV')->icon(Heroicon::OutlinedTv)->iconColor('primary')->placeholder('tanpa batas'),
                    TextEntry::make('monthly')->label('Harga / bulan')->icon(Heroicon::OutlinedBanknotes)->iconColor('success')->weight('bold')->state(fn (Hotel $h) => Billing::rupiah($h->monthlyPrice()))
                        ->helperText(fn (Hotel $h) => $h->discount_percent > 0 ? "diskon {$h->discount_percent}%" : null),
                    TextEntry::make('billing_months')->label('Siklus')->icon(Heroicon::OutlinedArrowPath)->iconColor('primary')->formatStateUsing(fn ($state) => Billing::CYCLES[$state] ?? "{$state} bulan"),
                    TextEntry::make('activated_at')->label('Aktif sejak')->icon(Heroicon::OutlinedRocketLaunch)->iconColor('primary')->date('d M Y', Billing::TZ)->placeholder('belum diaktifkan'),
                    TextEntry::make('auto_invoice')->label('Tagihan otomatis')->icon(Heroicon::OutlinedBolt)->iconColor('warning')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
                ]),
                Section::make('Kontak & penagihan')->icon(Heroicon::OutlinedUser)->columns(2)->columnSpan(1)->schema([
                    TextEntry::make('contact_name')->label('PIC')->icon(Heroicon::OutlinedUser)->iconColor('primary')->placeholder('–'),
                    TextEntry::make('contact_phone')->label('Telepon')->icon(Heroicon::OutlinedPhone)->iconColor('primary')->placeholder('–')->copyable(),
                    TextEntry::make('contact_email')->label('Email')->icon(Heroicon::OutlinedEnvelope)->iconColor('primary')->placeholder('–')->copyable(),
                    TextEntry::make('city')->label('Kota')->icon(Heroicon::OutlinedMapPin)->iconColor('primary')->placeholder('–'),
                    TextEntry::make('billing_name')->label('Nama tagihan')->icon(Heroicon::OutlinedBuildingLibrary)->iconColor('primary')->placeholder('–'),
                    TextEntry::make('npwp')->label('NPWP')->icon(Heroicon::OutlinedIdentification)->iconColor('primary')->placeholder('–'),
                ]),
                Section::make('Penggunaan 30 hari')->icon(Heroicon::OutlinedChartBar)->columnSpan(1)->schema([
                    ViewEntry::make('usage')->hiddenLabel()->view('vendor-console.hotel-usage'),
                ]),
            ]),
            Section::make('TV terdaftar')->icon(Heroicon::OutlinedTv)->columnSpanFull()->collapsible()->schema([
                ViewEntry::make('devices')->hiddenLabel()->view('vendor-console.hotel-devices'),
            ]),
            Section::make('Catatan internal')->columnSpanFull()->collapsed()->visible(fn (Hotel $h) => filled($h->notes))->schema([
                TextEntry::make('notes')->hiddenLabel(),
            ]),
        ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'city', 'contact_name'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('plan'))
            ->columns([
                ViewColumn::make('name')->label('Hotel')->view('vendor-console.columns.hotel')->searchable(['name', 'slug', 'city', 'contact_name'])->sortable(),
                TextColumn::make('plan.name')->label('Paket')->badge()->icon(Heroicon::OutlinedRectangleStack)
                    ->placeholder('Belum ada')->state(fn (Hotel $h) => $h->custom_monthly_price !== null ? 'Khusus' : $h->plan?->name)
                    ->color(fn (Hotel $h) => match ($h->custom_monthly_price !== null ? 'custom' : $h->plan?->code) {
                        'premium' => 'primary', 'standard' => 'info', 'custom' => 'warning', default => 'gray'
                    }),
                TextColumn::make('monthly')->label('Sewa / bulan')->icon(Heroicon::OutlinedBanknotes)->iconColor('success')->weight('semibold')
                    ->state(fn (Hotel $h) => $h->monthlyPrice())->formatStateUsing(fn ($state) => $state ? Billing::rupiah($state) : '–')
                    ->description(fn (Hotel $h) => $h->monthlyPrice() ? 'per '.(Billing::CYCLES[$h->billing_months] ?? "{$h->billing_months} bulan") : null)
                    ->visible(fn () => static::allowed('finance')),
                ViewColumn::make('service_expires_at')->label('Masa sewa')->view('vendor-console.columns.rental')->sortable(),
                ViewColumn::make('tvs')->label('TV')->view('vendor-console.columns.tvs'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Hotel $h) => $h->status !== 'active' ? 'suspended' : $h->serviceStatus())
                    ->formatStateUsing(fn (string $state) => ['active' => 'Aktif', 'expiring' => 'Segera habis', 'released' => 'Mode lepas', 'suspended' => 'Ditangguhkan'][$state])
                    ->icon(fn (string $state) => ['active' => Heroicon::OutlinedCheckCircle, 'expiring' => Heroicon::OutlinedClock, 'released' => Heroicon::OutlinedPower, 'suspended' => Heroicon::OutlinedNoSymbol][$state])
                    ->color(fn (string $state) => ['active' => 'success', 'expiring' => 'warning', 'released' => 'gray', 'suspended' => 'danger'][$state]),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status CMS')->options(['active' => 'Aktif', 'suspended' => 'Ditangguhkan']),
                SelectFilter::make('plan_id')->label('Paket')->relationship('plan', 'name'),
            ])
            ->defaultSort('name')
            ->recordUrl(fn (Hotel $h) => static::getUrl('view', ['record' => $h]))
            ->recordActions([
                EditAction::make()->modalWidth('3xl')->modalIcon(Heroicon::OutlinedPencilSquare),
                ActionGroup::make(HotelActions::all())->tooltip('Aksi lain'),
            ]);
    }

    public static function getRelations(): array
    {
        return [InvoicesRelationManager::class, RelationManagers\TicketsRelationManager::class, RelationManagers\DocumentsRelationManager::class, RelationManagers\FieldJobsRelationManager::class, RelationManagers\AssetsRelationManager::class, ActivitiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ManageHotels::route('/'), 'view' => ViewHotel::route('/{record}')];
    }
}
