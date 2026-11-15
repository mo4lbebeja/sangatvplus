<?php

namespace App\Filament\Resources\Slides;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\Slides\Pages\ManageSlides;
use App\Models\Slide;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class SlideResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = Slide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?string $modelLabel = 'slide';

    protected static ?string $pluralModelLabel = 'Slide Beranda';

    protected static ?int $navigationSort = 10;

    protected static function viewRoles(): array
    {
        return [UserRole::FrontOffice];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            FileUpload::make('image_path')->label('Gambar latar')->required()
                ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->disk('public')->directory('slides')->maxSize(8 * 1024)
                ->imageEditor()->imageEditorAspectRatioOptions(['16:9'])
                ->helperText('JPG/PNG/WebP hingga 8 MB. Otomatis dipotong & dikecilkan ke 1920×1080 agar ringan di TV.')
                ->columnSpanFull(),
            TextInput::make('kicker')->label('Kicker (teks kecil)')->maxLength(60),
            TextInput::make('title')->label('Judul')->required()->maxLength(60),
            Textarea::make('description')->label('Deskripsi')->rows(2)->maxLength(200)->columnSpanFull(),
            DateTimePicker::make('starts_at')->label('Tampil mulai'),
            DateTimePicker::make('ends_at')->label('Tampil sampai')->after('starts_at'),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            Toggle::make('is_apps_background')->label('Jadikan latar halaman menu TV (Aplikasi, Info Hotel, Room Service, Fasilitas)')->inline(false)->columnSpanFull()
                ->helperText('Gambar slide ini dipakai sebagai latar halaman Aplikasi dan Info Hotel (termasuk Room Service, Fasilitas, Galeri, Wi-Fi), dengan lapisan gelap agar isi tetap jelas. Hanya satu slide; mengaktifkan di sini mematikan yang lain.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')->label('')->disk('public')->imageHeight(30)->imageWidth(54),
                TextColumn::make('kicker')->label('Kicker')->toggleable(),
                TextColumn::make('title')->label('Judul')->searchable()->weight('semibold'),
                TextColumn::make('description')->label('Deskripsi')->limit(50)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('starts_at')->label('Mulai')->dateTime('d M Y')->placeholder('Selalu')->icon(Heroicon::OutlinedCalendar)->color('gray'),
                TextColumn::make('ends_at')->label('Sampai')->dateTime('d M Y')->placeholder('—'),
                ToggleColumn::make('is_active')->label('Aktif'),
                ToggleColumn::make('is_apps_background')->label('Latar Menu')
                    ->tooltip('Latar halaman Aplikasi & Info Hotel di TV (hanya satu slide)'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSlides::route('/')];
    }
}
