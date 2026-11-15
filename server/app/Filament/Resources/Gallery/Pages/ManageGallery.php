<?php

namespace App\Filament\Resources\Gallery\Pages;

use App\Filament\Resources\Gallery\GalleryResource;
use App\Models\GalleryPhoto;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageGallery extends ManageRecords
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkUpload')
                ->label('Unggah banyak foto')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->visible(fn () => GalleryResource::canCreate())
                ->modalHeading('Unggah banyak foto sekaligus')
                ->modalDescription('Pilih beberapa foto sekaligus. Semua masuk ke album yang dipilih; judul bisa diisi belakangan.')
                ->modalSubmitActionLabel('Unggah')
                ->schema([
                    GalleryResource::albumField(),
                    FileUpload::make('photos')->label('Foto')->required()->multiple()->maxFiles(30)->image()->reorderable()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->disk('public')->directory('gallery')->maxSize(8 * 1024)
                        ->panelLayout('grid'),
                ])
                ->action(function (array $data) {
                    $next = (int) GalleryPhoto::query()->max('sort_order');
                    foreach (array_values((array) $data['photos']) as $path) {
                        GalleryPhoto::create(['album' => $data['album'] ?? null, 'image_path' => $path, 'sort_order' => $next += 10]);
                    }
                    Notification::make()->title(count((array) $data['photos']).' foto ditambahkan')->body('TV menampilkan galeri baru dalam ±1 menit.')->success()->send();
                }),
            CreateAction::make()->label('Tambah foto')->icon(Heroicon::OutlinedPlus),
        ];
    }
}
