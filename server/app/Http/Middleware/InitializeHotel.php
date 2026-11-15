<?php

namespace App\Http\Middleware;

use App\Models\Central\Hotel;
use App\Tenancy\HotelManager;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * CMS (after the session starts, before authentication): open the database of the hotel chosen with
 * "Kode Hotel" on the login page. No hotel yet → only the login page is reachable.
 */
class InitializeHotel
{
    public function __construct(private HotelManager $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenancy->enabled()) {
            return $next($request);
        }

        $id = $request->session()->get('hotel_id');
        $attributes = $id ? Cache::store()->remember("central:hotel:{$id}", 60, fn () => Hotel::query()->find($id)?->getAttributes()) : null;
        $hotel = $attributes ? (new Hotel)->newFromBuilder($attributes) : null;

        if ($hotel && ! $hotel->isAccessible()) {
            $request->session()->forget('hotel_id');
            Filament::auth()->logout();
            $hotel = null;
        }

        if ($hotel) {
            $this->tenancy->initialize($hotel);

            return $next($request);
        }

        // Without a hotel only the login page (step 1: Kode Hotel) and its Livewire calls may run.
        if ($request->routeIs('filament.admin.auth.login') || $request->is('livewire/*', 'livewire-*/*')) {
            return $next($request);
        }

        return redirect()->route('filament.admin.auth.login');
    }
}
