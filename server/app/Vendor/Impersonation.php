<?php

namespace App\Vendor;

use App\Models\Central\Hotel;
use App\Models\Central\VendorUser;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\HotelManager;
use Illuminate\Support\Facades\Auth;

/**
 * Sijalucom support: open a hotel's CMS as one of its staff without knowing their password.
 * Recorded in that hotel's audit log (who, when); a banner stays on screen until "Selesai".
 */
class Impersonation
{
    public const SESSION = 'impersonated_by';

    public static function start(Hotel $hotel, int $userId, VendorUser $vendor): User
    {
        $user = app(HotelManager::class)->run($hotel, function () use ($userId, $vendor) {
            $user = User::query()->where('is_active', true)->findOrFail($userId);
            Auth::guard('web')->login($user);
            Audit::log('vendor.masuk_sebagai', $user, ['vendor' => $vendor->email], actor: $vendor);

            return $user;
        });

        session()->put('hotel_id', $hotel->id);
        session()->put(self::SESSION, $vendor->email);
        session()->forget('password_hash_web');

        return $user;
    }

    public static function active(): bool
    {
        return session()->has(self::SESSION);
    }

    /** Back to the vendor panel: log out of the hotel CMS (the vendor login stays). */
    public static function stop(): void
    {
        $tenancy = app(HotelManager::class);
        $hotel = ($id = session('hotel_id')) ? Hotel::query()->find($id) : null;
        $logout = fn () => Auth::guard('web')->logout();
        $hotel && $tenancy->enabled() ? $tenancy->run($hotel, $logout) : $logout();
        session()->forget([self::SESSION, 'hotel_id', 'password_hash_web']);
    }
}
