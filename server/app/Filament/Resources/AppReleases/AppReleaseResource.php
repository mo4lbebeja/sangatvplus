<?php

namespace App\Filament\Resources\AppReleases;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\AppReleases\Pages\ManageAppReleases;
use App\Models\AppRelease;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
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
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class AppReleaseResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = AppRelease::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Perangkat';

    protected static ?string $modelLabel = 'rilis';

    protected static ?string $pluralModelLabel = 'Update Aplikasi (OTA)';

    protected static ?int $navigationSort = 30;

    protected static function viewRoles(): array
    {
        return [UserRole::It];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('apk_path')->label('File APK (release, bertanda tangan)')
                ->disk('local')->directory('releases')->visibility('private')
                ->acceptedFileTypes(['application/vnd.android.package-archive', 'application/octet-stream', 'application/zip'])
                ->maxSize(200 * 1024)
                ->required()->disabledOn('edit'),
            Select::make('package_name')->label('Paket aplikasi')->options(AppRelease::PACKAGES)->default(AppRelease::DEFAULT_PACKAGE)->required()->disabledOn('edit')
                ->helperText('TV hanya menerima rilis untuk paket yang terpasang di TV itu.'),
            TextInput::make('version_code')->label('versionCode')->required()->integer()->minValue(1)->disabledOn('edit')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('package_name', $get('package_name'))),
            TextInput::make('version_name')->label('versionName')->required()->maxLength(32),
            Textarea::make('notes')->label('Catatan rilis')->rows(3),
            Toggle::make('is_active')->label('Aktifkan (TV akan mengunduh)')->default(false),
        ]);
    }

    /** sha256 is computed by the server from the stored file, never typed by hand. */
    public static function withChecksum(array $data): array
    {
        $data['sha256'] = hash_file('sha256', Storage::disk('local')->path($data['apk_path']));

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('package_name')->label('Paket')->badge()->color(fn (string $state) => $state === 'com.sangatv.plus' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state) => $state === 'com.sangatv.plus' ? 'Rilis' : 'Staging'),
                TextColumn::make('version_code')->label('Code')->sortable(),
                TextColumn::make('version_name')->label('Versi'),
                TextColumn::make('sha256')->label('SHA-256')->limit(16)->copyable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('created_at')->label('Diunggah')->dateTime('d M Y H:i'),
            ])
            ->defaultSort('version_code', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->after(fn (AppRelease $record) => Storage::disk('local')->delete($record->apk_path)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAppReleases::route('/')];
    }
}
