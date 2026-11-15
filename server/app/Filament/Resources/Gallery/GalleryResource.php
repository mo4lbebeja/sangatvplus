<?php

namespace App\Filament\Resources\Gallery;

use App\Enums\UserRole;
use App\Filament\Concerns\RestrictedByRole;
use App\Filament\Resources\Gallery\Pages\ManageGallery;
use App\Models\GalleryPhoto;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class GalleryResource extends Resource
{
    use RestrictedByRole;

    protected static ?string $model = GalleryPhoto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?string $modelLabel = 'foto';

    protected static ?string $pluralModelLabel = 'Galeri';

    protected static ?string $slug = 'galeri';

    protected static ?int $navigationSort = 15;

    protected static function viewRoles(): array
    {
        return [UserRole::FrontOffice];
    }

    /** Albums already used, offered as choices (free text allowed). */
    public static function albums(): array
    {
        $used = GalleryPhoto::query()->whereNotNull('album')->distinct()->orderBy('album')->pluck('album')->all();

        return array_combine($used, $used) + ['Kamar' => 'Kamar', 'Kolam Renang' => 'Kolam Renang', 'Restoran' => 'Restoran', 'Lobi' => 'Lobi', 'Fasilitas' => 'Fasilitas', 'Sekitar Hotel' => 'Sekitar Hotel'];
    }

    public static function albumField(): Select
    {
        return Select::make('album')->label('Album')->options(fn () => static::albums())->searchable()
            ->createOptionForm([TextInput::make('album')->label('Album baru')->required()->maxLength(64)])
            ->createOptionUsing(fn (array $data) => $data['album'])
            ->prefixIcon(Heroicon::OutlinedFolder);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            FileUpload::make('image_path')->label('Foto')->required()->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->disk('public')->directory('gallery')->maxSize(8 * 1024)
                ->imagePreviewHeight('160')
                ->helperText('JPG/PNG/WebP hingga 8 MB, otomatis dikecilkan. Foto mendatar (landscape) tampil paling bagus di TV.')
                ->columnSpanFull(),
            static::albumField(),
            TextInput::make('title')->label('Judul')->maxLength(80),
            Textarea::make('caption')->label('Keterangan')->rows(2)->maxLength(500)->columnSpanFull(),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            Toggle::make('is_active')->label('Tampil di TV')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['sm' => 2, 'md' => 3, 'xl' => 5])
            ->columns([
                Stack::make([
                    ImageColumn::make('thumb_path')->label('')->disk('public')
                        ->getStateUsing(fn (GalleryPhoto $r) => $r->thumb_path ?: $r->image_path)
                        ->imageHeight(130)->extraImgAttributes(['class' => 'sg-gallery-thumb', 'loading' => 'lazy']),
                    TextColumn::make('title')->weight('semibold')->placeholder('Tanpa judul')->limit(30),
                    TextColumn::make('album')->badge()->color('gray')->icon(Heroicon::OutlinedFolder)->placeholder('—'),
                ])->space(2),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginationPageOptions([20, 40, 80])->defaultPaginationPageOption(20)
            ->filters([SelectFilter::make('album')->options(fn () => GalleryPhoto::query()->whereNotNull('album')->distinct()->pluck('album', 'album')->all())])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGallery::route('/')];
    }
}
