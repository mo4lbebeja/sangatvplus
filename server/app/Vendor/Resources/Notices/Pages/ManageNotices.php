<?php

namespace App\Vendor\Resources\Notices\Pages;

use App\Models\Central\Notice;
use App\Models\Central\VendorActivity;
use App\Vendor\Resources\Notices\NoticeResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageNotices extends ManageRecords
{
    protected static string $resource = NoticeResource::class;

    protected ?string $subheading = 'Tampil sebagai banner di CMS hotel (semua atau hotel tertentu), sesuai periode tayang.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Pengumuman baru')->icon(Heroicon::OutlinedPlus)->modalIcon(Heroicon::OutlinedMegaphone)
                ->mutateDataUsing(fn (array $data) => $data + ['created_by' => Filament::auth()->id()])
                ->after(fn (Notice $record) => VendorActivity::record('notice.published', "Pengumuman: {$record->title}")),
        ];
    }
}
