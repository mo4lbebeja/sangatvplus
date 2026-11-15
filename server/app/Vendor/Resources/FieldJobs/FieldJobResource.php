<?php

namespace App\Vendor\Resources\FieldJobs;

use App\Models\Central\FieldJob;
use App\Models\Central\Hotel;
use App\Models\Central\VendorActivity;
use App\Models\Central\VendorUser;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\Resources\FieldJobs\Pages\ManageFieldJobs;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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

class FieldJobResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'kunjungan';

    protected static ?string $model = FieldJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'kunjungan';

    protected static ?string $pluralModelLabel = 'Kunjungan Teknisi';

    public const ICONS = ['install' => Heroicon::OutlinedWrenchScrewdriver, 'repair' => Heroicon::OutlinedBugAnt, 'maintenance' => Heroicon::OutlinedCog6Tooth,
        'survey' => Heroicon::OutlinedClipboardDocumentList, 'training' => Heroicon::OutlinedAcademicCap];

    protected static function area(): string
    {
        return 'support';
    }

    public static function getNavigationBadge(): ?string
    {
        $n = FieldJob::query()->whereIn('status', ['scheduled', 'in_progress'])->whereDate('scheduled_at', now('Asia/Jakarta')->toDateString())->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Kunjungan hari ini';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('hotel_id')->label('Hotel')->prefixIcon(Heroicon::OutlinedBuildingOffice2)->options(fn () => Hotel::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
            Select::make('type')->label('Jenis')->prefixIcon(Heroicon::OutlinedTag)->options(FieldJob::TYPES)->default('repair')->required(),
            TextInput::make('title')->label('Pekerjaan')->prefixIcon(Heroicon::OutlinedClipboardDocumentCheck)->required()->maxLength(160)->columnSpanFull()->placeholder('mis. Pasang 5 TV Sharp lantai 1'),
            DateTimePicker::make('scheduled_at')->label('Jadwal')->prefixIcon(Heroicon::OutlinedCalendarDays)->native(false)->seconds(false)->timezone('Asia/Jakarta')->required(),
            TextInput::make('duration_minutes')->label('Perkiraan durasi')->prefixIcon(Heroicon::OutlinedClock)->numeric()->suffix('menit')->default(120),
            Select::make('technician_id')->label('Teknisi')->prefixIcon(Heroicon::OutlinedUserCircle)->options(fn () => VendorUser::query()->where('is_active', true)->pluck('name', 'id')),
            Select::make('status')->label('Status')->prefixIcon(Heroicon::OutlinedSignal)->options(FieldJob::STATUSES)->default('scheduled')->required(),
            Textarea::make('notes')->label('Catatan / kebutuhan alat')->rows(2)->columnSpanFull(),
            Textarea::make('result')->label('Hasil pekerjaan')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['hotel', 'technician']))
            ->columns([
                TextColumn::make('scheduled_at')->label('Jadwal')->dateTime('D, d M H:i', 'Asia/Jakarta')->icon(Heroicon::OutlinedCalendarDays)->sortable()
                    ->description(fn (FieldJob $j) => $j->scheduled_at->diffForHumans())
                    ->color(fn (FieldJob $j) => $j->status === 'scheduled' && $j->scheduled_at->isPast() ? 'danger' : null),
                TextColumn::make('title')->label('Pekerjaan')->weight('bold')->wrap()->searchable()->description(fn (FieldJob $j) => $j->hotel?->name),
                TextColumn::make('type')->label('Jenis')->badge()->color('gray')->formatStateUsing(fn ($state) => FieldJob::TYPES[$state] ?? $state)->icon(fn ($state) => self::ICONS[$state] ?? null),
                TextColumn::make('technician.name')->label('Teknisi')->icon(Heroicon::OutlinedUserCircle)->placeholder('Belum ditentukan'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => FieldJob::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['scheduled' => 'info', 'in_progress' => 'warning', 'done' => 'success', 'cancelled' => 'gray'][$state] ?? 'gray')
                    ->icon(fn ($state) => ['scheduled' => Heroicon::OutlinedClock, 'in_progress' => Heroicon::OutlinedWrench, 'done' => Heroicon::OutlinedCheckCircle, 'cancelled' => Heroicon::OutlinedXCircle][$state] ?? null),
            ])
            ->filters([SelectFilter::make('technician_id')->label('Teknisi')->relationship('technician', 'name'), SelectFilter::make('type')->label('Jenis')->options(FieldJob::TYPES)])
            ->defaultSort('scheduled_at')
            ->recordActions([
                Action::make('done')->label('Selesai')->icon(Heroicon::OutlinedCheckCircle)->color('success')->iconButton()->tooltip('Tandai selesai')
                    ->visible(fn (FieldJob $j) => in_array($j->status, ['scheduled', 'in_progress'], true))
                    ->schema([Textarea::make('result')->label('Hasil pekerjaan')->required()->rows(3)])
                    ->action(function (FieldJob $j, array $data) {
                        $j->update(['status' => 'done', 'result' => $data['result'], 'completed_at' => now()]);
                        VendorActivity::record('field.done', "Kunjungan selesai: {$j->title}", $j->hotel);
                    }),
                EditAction::make()->modalWidth('3xl'),
                DeleteAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedTruck)->emptyStateHeading('Belum ada kunjungan')->emptyStateDescription('Jadwalkan instalasi, perbaikan, atau perawatan ke hotel.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageFieldJobs::route('/')];
    }
}
