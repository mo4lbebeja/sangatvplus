<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected ?string $subheading = 'Tampil di TV kamar dalam ±1 menit (saat sinkron berikutnya).';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat pengumuman')->icon(Heroicon::OutlinedMegaphone)->slideOver()
                ->mutateDataUsing(fn (array $data) => $data + ['created_by' => Auth::id()]),
        ];
    }
}
