<?php

namespace App\Vendor\Resources\VendorUsers\Pages;

use App\Models\Central\VendorUser;
use App\Vendor\Resources\VendorUsers\VendorUserResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageVendorUsers extends ManageRecords
{
    protected static string $resource = VendorUserResource::class;

    protected ?string $subheading = 'Pemilik: semua akses · Keuangan: tagihan, paket & laporan · Dukungan: hotel, TV & masuk CMS hotel.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah anggota')
                ->using(function (array $data) {
                    $password = Str::password(16, symbols: false);
                    $user = VendorUser::query()->create($data + ['password' => $password]);
                    Notification::make()->title('Akun dibuat')->body('Login: '.url('/sijalucom')." · {$user->email} · kata sandi sementara: {$password} (wajib pasang 2FA saat login pertama)")->persistent()->success()->send();

                    return $user;
                })->successNotification(null),
        ];
    }
}
