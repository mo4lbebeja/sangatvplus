<?php

namespace App\Vendor\Resources\Assets;

use App\Models\Central\Asset;
use App\Models\Central\Hotel;
use App\Vendor\Billing;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\Assets\Pages\ManageAssets;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AssetResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'inventaris';

    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'perangkat';

    protected static ?string $pluralModelLabel = 'Inventaris Perangkat';

    public const ICONS = ['tv' => Heroicon::OutlinedTv, 'stb' => Heroicon::OutlinedCpuChip, 'remote' => Heroicon::OutlinedDevicePhoneMobile, 'router' => Heroicon::OutlinedWifi, 'other' => Heroicon::OutlinedCube];

    protected static function area(): string
    {
        return 'support';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('type')->label('Jenis')->prefixIcon(Heroicon::OutlinedCube)->options(Asset::TYPES)->default('tv')->required(),
            TextInput::make('serial')->label('Nomor seri')->prefixIcon(Heroicon::OutlinedHashtag)->maxLength(80)->unique(ignoreRecord: true),
            TextInput::make('brand')->label('Merek')->prefixIcon(Heroicon::OutlinedTag)->maxLength(60),
            TextInput::make('model')->label('Model')->prefixIcon(Heroicon::OutlinedTag)->maxLength(80),
            Select::make('status')->label('Status')->prefixIcon(Heroicon::OutlinedSignal)->options(Asset::STATUSES)->default('in_stock')->required(),
            Select::make('hotel_id')->label('Lokasi hotel')->prefixIcon(Heroicon::OutlinedBuildingOffice2)->options(fn () => Hotel::query()->orderBy('name')->pluck('name', 'id'))->placeholder('Gudang Sijalucom')->searchable(),
            TextInput::make('location')->label('Kamar / rak')->prefixIcon(Heroicon::OutlinedMapPin)->maxLength(80),
            TextInput::make('price')->label('Harga beli')->prefixIcon(Heroicon::OutlinedBanknotes)->numeric()->prefix('Rp'),
            DatePicker::make('purchased_at')->label('Tanggal beli')->native(false)->displayFormat('d M Y'),
            DatePicker::make('warranty_until')->label('Garansi sampai')->native(false)->displayFormat('d M Y'),
            Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('hotel'))
            ->columns([
                TextColumn::make('model')->label('Perangkat')->weight('bold')->searchable(['brand', 'model', 'serial'])
                    ->state(fn (Asset $a) => trim(($a->brand ?? '').' '.($a->model ?? '')) ?: (Asset::TYPES[$a->type] ?? $a->type))
                    ->icon(fn (Asset $a) => self::ICONS[$a->type] ?? null)->iconColor('primary')->description(fn (Asset $a) => $a->serial ? "SN {$a->serial}" : null),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => Asset::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['in_stock' => 'info', 'installed' => 'success', 'repair' => 'warning', 'retired' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('hotel.name')->label('Lokasi')->icon(Heroicon::OutlinedMapPin)->placeholder('Gudang')->description(fn (Asset $a) => $a->location),
                TextColumn::make('warranty_until')->label('Garansi')->date('d M Y')->placeholder('–')->color(fn (Asset $a) => $a->warranty_until?->isPast() ? 'danger' : null),
                TextColumn::make('price')->label('Harga')->formatStateUsing(fn ($state) => $state ? Billing::rupiah($state) : '–'),
            ])
            ->filters([SelectFilter::make('status')->options(Asset::STATUSES), SelectFilter::make('type')->label('Jenis')->options(Asset::TYPES), SelectFilter::make('hotel_id')->label('Hotel')->relationship('hotel', 'name')])
            ->recordActions([EditAction::make()->modalWidth('3xl'), DeleteAction::make()])
            ->emptyStateIcon(Heroicon::OutlinedArchiveBox)->emptyStateHeading('Belum ada perangkat')->emptyStateDescription('Catat TV/STB milik Sijalucom yang disewakan atau di gudang.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageAssets::route('/')];
    }
}
