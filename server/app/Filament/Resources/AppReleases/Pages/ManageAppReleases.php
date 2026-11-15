<?php

namespace App\Filament\Resources\AppReleases\Pages;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\DeviceStatus;
use App\Filament\Resources\AppReleases\AppReleaseResource;
use App\Models\AppRelease;
use App\Models\Device;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageAppReleases extends ManageRecords
{
    protected static string $resource = AppReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // TVs check every 6 h on their own; this asks every active TV to check right away.
            Action::make('pushUpdate')
                ->label('Minta semua TV update sekarang')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn () => AppReleaseResource::canCreate())
                ->requiresConfirmation()
                ->modalHeading('Kirim perintah update ke semua TV?')
                ->modalDescription(fn () => ($r = AppRelease::query()->where('is_active', true)->orderByDesc('version_code')->first())
                    ? "TV yang menyala akan mengunduh versi {$r->version_name} (code {$r->version_code}) dalam ±1 menit. TV tanpa Device Owner menampilkan dialog konfirmasi pasang."
                    : 'Belum ada rilis aktif.')
                ->action(function () {
                    $devices = Device::query()->where('status', DeviceStatus::Active)->get();
                    foreach ($devices as $d) {
                        $d->commands()->create(['type' => CommandType::CheckUpdate, 'status' => CommandStatus::Pending]);
                    }
                    Audit::log('app_release.push_update', null, ['devices' => $devices->count()]);
                    Notification::make()->title("Perintah update dikirim ke {$devices->count()} TV")->success()->send();
                }),
            CreateAction::make()->label('Unggah rilis')->mutateDataUsing(fn (array $data) => AppReleaseResource::withChecksum($data)),
        ];
    }
}
