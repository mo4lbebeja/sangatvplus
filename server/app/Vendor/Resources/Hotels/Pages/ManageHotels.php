<?php

namespace App\Vendor\Resources\Hotels\Pages;

use App\Models\Central\Hotel;
use App\Tenancy\HotelManager;
use App\Vendor\Resources\Hotels\HotelResource;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Carbon;
use Throwable;

class ManageHotels extends ManageRecords
{
    protected static string $resource = HotelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Hotel baru')
                ->modalDescription('Membuat database hotel sendiri, profil hotel, dan akun admin pertama. Kata sandi sementara ditampilkan sekali.')
                ->schema([
                    TextInput::make('slug')->label('Kode Hotel')->required()->maxLength(40)
                        ->helperText('Diketik staf sebelum login, mis. napolin. Huruf kecil, angka, tanda minus.')
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (! HotelManager::validSlug(strtolower(trim((string) $value)))) {
                                $fail('Kode hotel hanya huruf kecil, angka, dan tanda minus (3–40 karakter), bukan kata cadangan.');
                            } elseif (Hotel::query()->where('slug', strtolower(trim((string) $value)))->exists()) {
                                $fail('Kode hotel sudah dipakai.');
                            }
                        }),
                    TextInput::make('name')->label('Nama hotel')->required()->maxLength(120),
                    TextInput::make('admin_email')->label('Email admin hotel')->email()->required(),
                    TextInput::make('contact_name')->label('Kontak hotel')->maxLength(120),
                    DatePicker::make('service_expires_at')->label('Masa sewa sampai')->native(false)->displayFormat('d M Y'),
                ])
                ->using(function (array $data) {
                    try {
                        $r = app(HotelManager::class)->provision($data['slug'], $data['name'], $data['admin_email']);
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('Hotel gagal dibuat')->body($e->getMessage())->danger()->persistent()->send();
                        throw new Halt;
                    }
                    $r['hotel']->update([
                        'contact_name' => $data['contact_name'] ?? null,
                        'service_expires_at' => filled($data['service_expires_at'] ?? null) ? Carbon::parse($data['service_expires_at'])->endOfDay() : null,
                    ]);
                    Notification::make()->title("Hotel {$r['hotel']->name} siap")
                        ->body("Login CMS: Kode Hotel {$r['hotel']->slug} · {$data['admin_email']} · kata sandi sementara: {$r['password']} (catat sekarang; admin wajib aktifkan verifikasi dua langkah).")
                        ->success()->persistent()->send();

                    return $r['hotel'];
                })
                ->successNotification(null),
        ];
    }
}
