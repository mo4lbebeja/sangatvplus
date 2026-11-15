<?php

namespace App\Filament\Resources\PmsIntegrations\Pages;

use App\Filament\Resources\PmsIntegrations\PmsIntegrationResource;
use App\Models\PmsIntegration;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ManagePmsIntegrations extends ManageRecords
{
    protected static string $resource = PmsIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat kunci API')->icon(Heroicon::OutlinedPlus)
                ->using(function (array $data) {
                    $secret = PmsIntegration::newSecret();
                    $p = PmsIntegration::create([...$data, 'key_id' => PmsIntegration::newKeyId(), 'secret' => $secret, 'created_by' => Auth::id()]);
                    PmsIntegrationResource::notifySecret($p, $secret, 'Kunci API dibuat');

                    return $p;
                })
                ->successNotification(null),
        ];
    }
}
