<?php

namespace App\Vendor\Resources\Releases;

use App\Models\AppRelease;
use App\Models\Central\Hotel;
use App\Models\Central\Release;
use App\Models\Central\VendorActivity;
use App\Vendor\Concerns\VendorAccess;
use App\Vendor\ReleaseSync;
use App\Vendor\Resources\Releases\Pages\ManageReleases;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class ReleaseResource extends Resource
{
    use VendorAccess;

    protected static ?string $slug = 'rilis';

    protected static ?string $model = Release::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'rilis';

    protected static ?string $pluralModelLabel = 'Rilis Aplikasi (OTA)';

    protected static function area(): string
    {
        return 'support';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            FileUpload::make('apk_path')->label('File APK (release, bertanda tangan)')
                ->disk('local')->directory('releases')->visibility('private')
                ->acceptedFileTypes(['application/vnd.android.package-archive', 'application/octet-stream', 'application/zip'])
                ->maxSize(200 * 1024)->required()->disabledOn('edit')->columnSpanFull(),
            Select::make('package_name')->label('Paket aplikasi')->prefixIcon(Heroicon::OutlinedCube)->options(AppRelease::PACKAGES)->default('com.sangatv.plus')->required()->disabledOn('edit')
                ->helperText('TV hanya menerima rilis untuk paket yang terpasang.'),
            TextInput::make('version_code')->label('versionCode')->prefixIcon(Heroicon::OutlinedHashtag)->required()->integer()->minValue(1)->disabledOn('edit')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('package_name', $get('package_name'))),
            TextInput::make('version_name')->label('versionName')->prefixIcon(Heroicon::OutlinedTag)->required()->maxLength(32)->placeholder('mis. 0.4.1'),
            Select::make('hotel_ids')->label('Hotel tujuan')->multiple()->options(fn () => Hotel::query()->orderBy('name')->pluck('name', 'id'))
                ->placeholder('Semua hotel')->helperText('Kosong = semua hotel. Pilih beberapa hotel untuk uji bertahap.'),
            Textarea::make('notes')->label('Catatan rilis')->rows(3)->columnSpanFull(),
            Toggle::make('is_active')->label('Aktifkan (TV mengunduh saat cek update)')->default(true),
        ]);
    }

    public static function withChecksum(array $data): array
    {
        if (filled($data['apk_path'] ?? null)) {
            $data['sha256'] = hash_file('sha256', Storage::disk('local')->path($data['apk_path']));
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version_name')->label('Versi')->weight('bold')->icon(Heroicon::OutlinedRocketLaunch)->iconColor('primary')
                    ->description(fn (Release $r) => "code {$r->version_code} · ".$r->created_at->translatedFormat('d M Y H:i')),
                TextColumn::make('package_name')->label('Paket')->badge()->color(fn (string $state) => $state === 'com.sangatv.plus' ? 'success' : 'warning')
                    ->icon(Heroicon::OutlinedCube)->formatStateUsing(fn (string $state) => $state === 'com.sangatv.plus' ? 'Rilis' : 'Staging'),
                TextColumn::make('target')->label('Hotel tujuan')->icon(Heroicon::OutlinedBuildingOffice2)
                    ->state(fn (Release $r) => empty($r->hotel_ids) ? 'Semua hotel' : Hotel::query()->whereIn('id', $r->hotel_ids)->pluck('name')->implode(', ')),
                TextColumn::make('installed')->label('TV terpasang')->icon(Heroicon::OutlinedTv)->state(fn (Release $r) => ReleaseSync::installed($r).' TV'),
                TextColumn::make('notes')->label('Catatan')->limit(60)->wrap()->placeholder('–'),
                TextColumn::make('sha256')->label('SHA-256')->limit(12)->copyable()->fontFamily('mono')->color('gray'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('version_code', 'desc')
            ->recordActions([
                Action::make('push')->label('Kirim ke TV')->icon(Heroicon::OutlinedPaperAirplane)->color('primary')->iconButton()->tooltip('Minta TV update sekarang')
                    ->visible(fn (Release $r) => $r->is_active)
                    ->requiresConfirmation()
                    ->modalDescription('TV yang menyala di hotel tujuan mengunduh versi ini dalam ±1 menit. TV "Tahan update" dilewati; TV tanpa Device Owner menampilkan dialog pasang.')
                    ->action(function (Release $r) {
                        [$sent, $held] = ReleaseSync::push($r);
                        VendorActivity::record('tv.command', "Update {$r->version_name} dikirim ke {$sent} TV");
                        Notification::make()->title("Perintah update dikirim ke {$sent} TV")->body($held ? "{$held} TV ditahan, dilewati." : null)->success()->send();
                    }),
                EditAction::make()->after(fn (Release $r) => static::afterSave($r)),
                DeleteAction::make()->before(fn (Release $r) => ReleaseSync::remove($r)),
            ])
            ->emptyStateIcon(Heroicon::OutlinedRocketLaunch)
            ->emptyStateHeading('Belum ada rilis')
            ->emptyStateDescription('Unggah APK di sini atau jalankan tv-app/scripts/rilis-ota.sh dari laptop.');
    }

    public static function afterSave(Release $release): void
    {
        foreach (Release::query()->where('package_name', $release->package_name)->get() as $r) {
            ReleaseSync::sync($r); // the newly active one + the ones it retired
        }
        VendorActivity::record('release.published', "Rilis {$release->version_name} (code {$release->version_code}) ".($release->is_active ? 'aktif' : 'nonaktif'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageReleases::route('/')];
    }
}
