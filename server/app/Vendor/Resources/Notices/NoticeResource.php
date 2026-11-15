<?php

namespace App\Vendor\Resources\Notices;

use App\Models\Central\Hotel;
use App\Models\Central\Notice;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\Notices\Pages\ManageNotices;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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

class NoticeResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'pengumuman';

    protected static ?string $model = Notice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Klien';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'pengumuman';

    protected static ?string $pluralModelLabel = 'Pengumuman';

    protected static function area(): string
    {
        return 'support';
    }

    public static function levelIcon(string $level): Heroicon
    {
        return ['info' => Heroicon::OutlinedInformationCircle, 'success' => Heroicon::OutlinedSparkles, 'warning' => Heroicon::OutlinedExclamationTriangle, 'maintenance' => Heroicon::OutlinedWrenchScrewdriver][$level] ?? Heroicon::OutlinedMegaphone;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->label('Judul')->prefixIcon(Heroicon::OutlinedMegaphone)->required()->maxLength(160)->columnSpanFull(),
            Textarea::make('body')->label('Isi')->rows(4)->columnSpanFull(),
            Select::make('level')->label('Jenis')->options(Notice::LEVELS)->default('info')->required()->prefixIcon(Heroicon::OutlinedSwatch),
            Select::make('hotel_ids')->label('Hotel tujuan')->multiple()->options(fn () => Hotel::query()->orderBy('name')->pluck('name', 'id'))
                ->placeholder('Semua hotel')->helperText('Kosong = semua hotel.'),
            DateTimePicker::make('starts_at')->label('Tampil mulai')->native(false)->seconds(false)->timezone('Asia/Jakarta')->prefixIcon(Heroicon::OutlinedCalendar),
            DateTimePicker::make('ends_at')->label('Tampil sampai')->native(false)->seconds(false)->timezone('Asia/Jakarta')->prefixIcon(Heroicon::OutlinedCalendar),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Pengumuman')->weight('bold')->wrap()
                    ->icon(fn (Notice $n) => static::levelIcon($n->level))->iconColor(fn (Notice $n) => ['info' => 'info', 'success' => 'success', 'warning' => 'warning', 'maintenance' => 'danger'][$n->level] ?? 'gray')
                    ->description(fn (Notice $n) => str($n->body)->limit(90)),
                TextColumn::make('level')->label('Jenis')->badge()->formatStateUsing(fn ($state) => Notice::LEVELS[$state] ?? $state)
                    ->color(fn ($state) => ['info' => 'info', 'success' => 'success', 'warning' => 'warning', 'maintenance' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('target')->label('Tujuan')->icon(Heroicon::OutlinedBuildingOffice2)
                    ->state(fn (Notice $n) => empty($n->hotel_ids) ? 'Semua hotel' : count($n->hotel_ids).' hotel'),
                TextColumn::make('period')->label('Periode')->icon(Heroicon::OutlinedCalendarDays)
                    ->state(fn (Notice $n) => ($n->starts_at?->setTimezone('Asia/Jakarta')->translatedFormat('d M H:i') ?? 'sekarang').' → '.($n->ends_at?->setTimezone('Asia/Jakarta')->translatedFormat('d M H:i') ?? 'seterusnya')),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateIcon(Heroicon::OutlinedMegaphone)
            ->emptyStateHeading('Belum ada pengumuman')
            ->emptyStateDescription('Contoh: jadwal maintenance server, fitur baru, atau pengingat pembayaran.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageNotices::route('/')];
    }
}
