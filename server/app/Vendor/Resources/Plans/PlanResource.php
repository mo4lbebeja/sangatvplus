<?php

namespace App\Vendor\Resources\Plans;

use App\Models\Central\Plan;
use App\Vendor\Billing;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\Plans\Pages\ManagePlans;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PlanResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'paket';

    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Klien';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'paket';

    protected static ?string $pluralModelLabel = 'Paket & Harga';

    protected static function area(): string
    {
        return 'finance';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nama paket')->required()->maxLength(80),
            TextInput::make('code')->label('Kode')->required()->maxLength(32)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('base_price')->label('Harga dasar / bulan')->numeric()->prefix('Rp')->minValue(0)->required()->default(0),
            TextInput::make('included_tvs')->label('TV termasuk harga dasar')->numeric()->minValue(0)->default(0)->required(),
            TextInput::make('price_per_tv')->label('Harga per TV tambahan / bulan')->numeric()->prefix('Rp')->minValue(0)->default(0)->required(),
            TextInput::make('sort')->label('Urutan')->numeric()->default(0),
            Textarea::make('description')->label('Deskripsi singkat')->rows(2)->columnSpanFull(),
            CheckboxList::make('features')->label('Fitur termasuk')->options(Plan::FEATURES)->columns(2)->columnSpanFull(),
            Toggle::make('is_active')->label('Bisa dipilih untuk hotel baru')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Paket')->weight('bold')->description(fn (Plan $p) => $p->description),
                TextColumn::make('base_price')->label('Harga dasar')->formatStateUsing(fn ($state, Plan $p) => Billing::rupiah($state).($p->included_tvs ? " ({$p->included_tvs} TV)" : '')),
                TextColumn::make('price_per_tv')->label('Per TV tambahan')->formatStateUsing(fn ($state) => Billing::rupiah($state)),
                TextColumn::make('example')->label('Contoh 20 TV')->state(fn (Plan $p) => Billing::rupiah($p->monthlyFor(20)).'/bln')->color('gray'),
                TextColumn::make('features')->label('Fitur')->badge()->formatStateUsing(fn ($state) => Plan::FEATURES[$state] ?? $state)->limitList(3)->expandableLimitedList(),
                TextColumn::make('hotels_count')->label('Hotel')->counts('hotels'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()->visible(fn (Plan $p) => $p->hotels()->doesntExist())])
            ->emptyStateHeading('Belum ada paket')->emptyStateDescription('Buat paket, mis. Basic (TV + launcher) dan Premium (+ PMS & statistik).');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePlans::route('/')];
    }
}
