<?php

namespace App\Filament\Pages\Auth;

use App\Models\Central\Hotel;
use App\Tenancy\HotelManager;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Two-step login (multi-hotel, like kulakku): 1) Kode Hotel — remembered in a device cookie,
 * 2) e-mail + password (+ two-factor) of that hotel's staff. Single-hotel installs skip step 1.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if ($this->needsHotel()) {
            $this->form->fill(['hotel_code' => request()->cookie(config('tenancy.remember_cookie'))]);
        }
    }

    protected function needsHotel(): bool
    {
        return app(HotelManager::class)->enabled() && ! session()->has('hotel_id');
    }

    public function form(Schema $schema): Schema
    {
        if (! $this->needsHotel()) {
            return parent::form($schema);
        }

        return $schema->components([
            TextInput::make('hotel_code')->label('Kode Hotel')->required()->maxLength(40)->autofocus()
                ->prefixIcon(Heroicon::OutlinedBuildingOffice2)->placeholder('mis. napolin')
                ->helperText('Diberikan oleh Sijalucom. Cukup sekali, perangkat ini akan mengingatnya.'),
        ]);
    }

    public function authenticate(): ?LoginResponse
    {
        if (! $this->needsHotel()) {
            return parent::authenticate();
        }

        $code = (string) ($this->form->getState()['hotel_code'] ?? '');
        $tenancy = app(HotelManager::class);
        $hotel = $tenancy->findBySlug($code);

        if (! $hotel) {
            throw ValidationException::withMessages(['data.hotel_code' => 'Kode hotel tidak ditemukan.']);
        }
        if (! $hotel->isAccessible()) {
            throw ValidationException::withMessages(['data.hotel_code' => 'Layanan hotel ini sedang ditangguhkan. Hubungi Sijalucom.']);
        }

        session()->put('hotel_id', $hotel->id);
        $tenancy->rememberCookie($hotel);

        // Step 2 in the same component: rebuild the form for e-mail + password.
        $this->redirect(filament()->getLoginUrl(), navigate: false);

        return null;
    }

    public function changeHotelAction(): Action
    {
        return Action::make('changeHotel')
            ->label('Ganti hotel')
            ->link()
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->action(function () {
                session()->forget('hotel_id');
                $this->redirect(filament()->getLoginUrl(), navigate: false);
            });
    }

    public function getHeading(): string|Htmlable|null
    {
        if (! app(HotelManager::class)->enabled()) {
            return parent::getHeading();
        }

        return $this->needsHotel() ? 'Masuk ke SangaTV Plus' : $this->hotelName();
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! app(HotelManager::class)->enabled()) {
            return parent::getSubheading();
        }
        if ($this->needsHotel()) {
            return 'Masukkan Kode Hotel Anda.';
        }

        return $this->changeHotelAction();
    }

    private function hotelName(): string
    {
        $id = session('hotel_id');

        return (string) Cache::store()->remember("central:hotel-name:{$id}", 60, fn () => Hotel::query()->whereKey($id)->value('name')) ?: 'SangaTV Plus';
    }
}
