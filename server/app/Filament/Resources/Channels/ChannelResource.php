<?php

namespace App\Filament\Resources\Channels;

use App\Enums\SourceType;
use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\Channels\Pages\ManageChannels;
use App\Models\Channel;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ChannelResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = Channel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Siaran';

    protected static ?string $modelLabel = 'channel';

    protected static ?string $pluralModelLabel = 'Channel';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function viewRoles(): array
    {
        return [UserRole::It];
    }

    private static function sourceType(Get $get): ?SourceType
    {
        $v = $get('source_type');

        return $v instanceof SourceType ? $v : SourceType::tryFrom((string) $v);
    }

    /** udp:// or rtp:// for multicast, http(s) for everything else. */
    private static function urlRule(Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get) {
            if (blank($value)) {
                return;
            }
            $scheme = strtolower((string) parse_url((string) $value, PHP_URL_SCHEME));
            $type = self::sourceType($get);
            $allowed = $type === SourceType::LocalMulticast ? ['udp', 'rtp', 'http', 'https'] : ['http', 'https'];
            if (! in_array($scheme, $allowed, true)) {
                $fail('URL harus diawali '.implode('://, ', $allowed).'://');
            }
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Channel')->icon(Heroicon::OutlinedTv)->description('Identitas dan urutan di daftar channel TV.')->columns(2)->schema([
                TextInput::make('number')->label('Nomor')->required()->integer()->minValue(1)->maxValue(9999)->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nama')->required()->maxLength(255),
                Select::make('channel_category_id')->label('Kategori')->relationship('category', 'name')->preload(),
                TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                FileUpload::make('logo_path')->label('Logo')->image()->disk('public')->directory('channel-logos')->maxSize(512),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ]),
            Section::make('Sumber siaran')->icon(Heroicon::OutlinedSignal)->description('Utama dan cadangan (failover otomatis di TV).')->columns(2)->schema([
                Select::make('source_type')->label('Jenis sumber')->options(SourceType::class)->required()->live(),
                Select::make('channel_provider_id')->label('Penyedia')->relationship('provider', 'name')->preload()
                    ->visible(fn (Get $get) => self::sourceType($get)?->isProvider() ?? false),
                TextInput::make('url')->label('URL utama')->required()->maxLength(2048)->columnSpanFull()
                    ->placeholder('udp://239.1.1.101:1234 atau https://…/index.m3u8')
                    ->rule(fn (Get $get) => self::urlRule($get)),
                TextInput::make('backup_url')->label('URL cadangan (failover)')->maxLength(2048)->columnSpanFull()
                    ->rule(fn (Get $get) => self::urlRule($get)),
            ]),
            Section::make('DRM')->icon(Heroicon::OutlinedLockClosed)->description('Hanya untuk channel penyedia yang memakai Widevine.')->collapsed()->columns(2)
                ->visible(fn (Get $get) => self::sourceType($get)?->isProvider() ?? false)
                ->schema([
                    Select::make('drm.scheme')->label('Skema')->options(['widevine' => 'Widevine']),
                    TextInput::make('drm.license_url')->label('License URL')->url()->maxLength(2048),
                    KeyValue::make('drm.headers')->label('Header lisensi')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'provider']))
            ->columns([
                TextColumn::make('number')->label('No')->sortable()->searchable()->weight('bold')->color('primary')->formatStateUsing(fn ($state) => str_pad((string) $state, 3, '0', STR_PAD_LEFT)),
                ImageColumn::make('logo_path')->label('')->disk('public')->height(28),
                TextColumn::make('name')->label('Nama')->searchable()->weight('semibold'),
                TextColumn::make('provider.name')->label('Penyedia')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.name')->label('Kategori')->badge()->color('gray')->icon(Heroicon::OutlinedTag),
                TextColumn::make('source_type')->label('Sumber')->badge()
                    ->color(fn (SourceType $state) => $state->isProvider() ? 'warning' : 'info'),
                TextColumn::make('url')->label('URL')->limit(40)->icon(Heroicon::OutlinedLink)->fontFamily('mono')->size('xs')->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->defaultSort('number')
            ->filters([
                SelectFilter::make('channel_category_id')->label('Kategori')->relationship('category', 'name'),
                SelectFilter::make('source_type')->label('Sumber')->options(SourceType::class),
            ])
            ->recordActions([EditAction::make()->slideOver(), DeleteAction::make()]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Channel::query()->where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Channel aktif';
    }

    public static function getPages(): array
    {
        return ['index' => ManageChannels::route('/')];
    }
}
