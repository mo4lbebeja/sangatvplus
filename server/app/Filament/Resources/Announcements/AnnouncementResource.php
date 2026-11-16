<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Models\Announcement;
use App\Models\Channel;
use App\Models\Room;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AnnouncementResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Siaran';

    protected static ?string $modelLabel = 'pengumuman';

    protected static ?string $pluralModelLabel = 'Pengumuman TV';

    protected static ?string $navigationLabel = 'Pengumuman';

    protected static ?int $navigationSort = 5;

    protected static function viewRoles(): array
    {
        return [UserRole::FrontOffice, UserRole::It];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pesan')->icon(Heroicon::OutlinedMegaphone)->columns(2)->schema([
                TextInput::make('title')->label('Judul')->required()->maxLength(120)->columnSpanFull(),
                Textarea::make('message')->label('Isi pesan')->required()->rows(3)->maxLength(600)->columnSpanFull(),
                Radio::make('style')->label('Tampilan di TV')->options(Announcement::STYLES)->default('ticker')->required()
                    ->descriptions([
                        'ticker' => 'Teks berjalan di bawah layar, tamu tetap menonton.',
                        'fullscreen' => 'Menutup layar sampai tamu menekan OK (darurat: sampai pengumuman berakhir).',
                    ]),
                Radio::make('level')->label('Tingkat')->options(Announcement::LEVELS)->default('info')->required()
                    ->descriptions(['emergency' => 'Tidak bisa ditutup tamu sampai berakhir; TV dibawa ke SangaTV.']),
                Select::make('force_channel_id')->label('Pindahkan semua TV ke channel')
                    ->options(fn () => Channel::query()->where('is_active', true)->orderBy('number')->get()->mapWithKeys(fn (Channel $c) => [$c->id => $c->number.' · '.$c->name])->all())
                    ->placeholder('Tidak (tetap di channel tamu)')->searchable()->columnSpanFull()
                    ->helperText('Mis. siaran darurat atau acara hotel. Hanya TV yang sedang menyala; sekali per pengumuman.'),
            ]),
            Section::make('Sasaran & waktu')->icon(Heroicon::OutlinedClock)->columns(2)->schema([
                Radio::make('target')->label('Kirim ke')->inline()->live()->dehydrated(false)
                    ->options(['all' => 'Semua kamar', 'floors' => 'Lantai tertentu', 'rooms' => 'Kamar tertentu'])
                    ->afterStateHydrated(fn (Radio $component, ?Announcement $record) => $component->state(match (true) {
                        ! empty($record?->room_ids) => 'rooms',
                        ! empty($record?->floors) => 'floors',
                        default => 'all',
                    }))
                    ->columnSpanFull(),
                CheckboxList::make('floors')->label('Lantai')->columns(6)->gridDirection('row')->columnSpanFull()
                    ->options(fn () => Room::query()->distinct()->orderBy('floor')->pluck('floor')->mapWithKeys(fn ($f) => [$f => "Lantai {$f}"])->all())
                    ->visible(fn (Get $get) => $get('target') === 'floors')->required(fn (Get $get) => $get('target') === 'floors')
                    ->dehydrateStateUsing(fn ($state, Get $get) => $get('target') === 'floors' ? array_map('intval', (array) $state) : null),
                Select::make('room_ids')->label('Kamar')->multiple()->searchable()->columnSpanFull()
                    ->options(fn () => Room::query()->orderBy('number')->pluck('number', 'id')->map(fn ($n) => "Kamar {$n}")->all())
                    ->visible(fn (Get $get) => $get('target') === 'rooms')->required(fn (Get $get) => $get('target') === 'rooms')
                    ->dehydrateStateUsing(fn ($state, Get $get) => $get('target') === 'rooms' ? array_map('intval', (array) $state) : null),
                DateTimePicker::make('starts_at')->label('Mulai')->seconds(false)->placeholder('Sekarang'),
                DateTimePicker::make('ends_at')->label('Berakhir')->seconds(false)->after('starts_at')
                    ->helperText('Kosongkan = tampil sampai dihentikan.'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
            Section::make('Jadwal harian')->icon(Heroicon::OutlinedArrowPath)->columns(2)
                ->description('Tayang rutin setiap hari pada jam yang sama, isinya tetap. Mulai/Berakhir di atas = periode berlakunya.')
                ->schema([
                    Toggle::make('daily')->label('Ulangi setiap hari pada jam tertentu')->live()->dehydrated(false)->columnSpanFull()
                        ->afterStateHydrated(fn (Toggle $component, ?Announcement $record) => $component->state((bool) $record?->isDaily())),
                    TimePicker::make('daily_from')->label('Tayang mulai jam')->seconds(false)->timezone(config('app.timezone')) // wall-clock hotel time, no conversion
                        ->visible(fn (Get $get) => (bool) $get('daily'))->required(fn (Get $get) => (bool) $get('daily'))
                        ->dehydrateStateUsing(fn ($state, Get $get) => $get('daily') ? $state : null),
                    TimePicker::make('daily_until')->label('Sampai jam')->seconds(false)->timezone(config('app.timezone'))
                        ->helperText('Boleh melewati tengah malam, mis. 22:00–06:00.')
                        ->visible(fn (Get $get) => (bool) $get('daily'))->required(fn (Get $get) => (bool) $get('daily'))
                        ->different('daily_from')
                        ->dehydrateStateUsing(fn ($state, Get $get) => $get('daily') ? $state : null),
                    CheckboxList::make('days')->label('Hari')->options(Announcement::DAYS)->columns(7)->gridDirection('row')->columnSpanFull()
                        ->default(array_keys(Announcement::DAYS))->helperText('Semua dicentang = setiap hari.')
                        ->visible(fn (Get $get) => (bool) $get('daily'))->required(fn (Get $get) => (bool) $get('daily'))
                        ->dehydrateStateUsing(fn ($state, Get $get) => $get('daily') && count((array) $state) < 7 ? array_map('intval', (array) $state) : null),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['forceChannel', 'creator']))
            ->columns([
                TextColumn::make('state')->label('Status')->badge()
                    ->state(fn (Announcement $record) => $record->state())
                    ->formatStateUsing(fn (string $state) => ['live' => 'Tayang', 'scheduled' => 'Terjadwal', 'waiting' => 'Menunggu jam tayang', 'ended' => 'Selesai', 'off' => 'Dihentikan'][$state])
                    ->color(fn (string $state) => ['live' => 'success', 'scheduled' => 'info', 'waiting' => 'info', 'ended' => 'gray', 'off' => 'gray'][$state])
                    ->icon(fn (string $state) => $state === 'live' ? Heroicon::OutlinedSignal : null),
                TextColumn::make('title')->label('Judul')->searchable()->weight('semibold')
                    ->description(fn (Announcement $record) => str($record->message)->limit(70)),
                TextColumn::make('level')->label('Tingkat')->badge()
                    ->formatStateUsing(fn (string $state) => Announcement::LEVELS[$state] ?? $state)
                    ->color(fn (string $state) => ['info' => 'gray', 'warning' => 'warning', 'emergency' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('style')->label('Tampilan')->formatStateUsing(fn (string $state) => Announcement::STYLES[$state] ?? $state)->color('gray'),
                TextColumn::make('target')->label('Sasaran')->color('gray')
                    ->state(fn (Announcement $record) => match (true) {
                        ! empty($record->room_ids) => count($record->room_ids).' kamar',
                        ! empty($record->floors) => 'Lantai '.implode(', ', $record->floors),
                        default => 'Semua kamar',
                    }),
                TextColumn::make('daily_from')->label('Jadwal harian')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->state(fn (Announcement $record) => $record->dailyLabel())->placeholder('—'),
                TextColumn::make('forceChannel.name')->label('Pindah ke')->placeholder('—')->icon(Heroicon::OutlinedTv)->toggleable(),
                TextColumn::make('starts_at')->label('Mulai')->dateTime('d M H:i')->placeholder('Langsung'),
                TextColumn::make('ends_at')->label('Berakhir')->dateTime('d M H:i')->placeholder('Sampai dihentikan'),
                TextColumn::make('creator.name')->label('Oleh')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('stop')->label('Hentikan')->icon(Heroicon::OutlinedStopCircle)->color('danger')->iconButton()->tooltip('Hentikan sekarang')
                    ->visible(fn (Announcement $record) => in_array($record->state(), ['live', 'scheduled', 'waiting'], true) && static::canEdit($record))
                    ->requiresConfirmation()->modalHeading('Hentikan pengumuman?')->modalDescription('Hilang dari semua TV dalam ±1 menit.')
                    ->action(function (Announcement $record) {
                        $record->update(['is_active' => false]);
                        Notification::make()->title('Pengumuman dihentikan')->success()->send();
                    }),
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Announcement::showing()->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return Announcement::showing()->contains('level', 'emergency') ? 'danger' : 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pengumuman sedang tayang';
    }

    public static function getPages(): array
    {
        return ['index' => ManageAnnouncements::route('/')];
    }
}
