<?php

namespace App\Vendor\Resources\Releases\Pages;

use App\Models\Central\Release;
use App\Vendor\Resources\Releases\ReleaseResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageReleases extends ManageRecords
{
    protected static string $resource = ReleaseResource::class;

    protected ?string $subheading = 'Hanya Sijalucom yang mengunggah rilis. Hotel otomatis menerima versi aktif; TV mengunduhnya saat cek update.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Unggah rilis')->icon(Heroicon::OutlinedArrowUpTray)->modalIcon(Heroicon::OutlinedRocketLaunch)->modalWidth('2xl')
                ->mutateDataUsing(fn (array $data) => ReleaseResource::withChecksum($data) + ['published_by' => Filament::auth()->id()])
                ->after(fn (Release $record) => ReleaseResource::afterSave($record)),
        ];
    }
}
