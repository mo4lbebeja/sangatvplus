<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only active, room-bound TVs holding a "tv" token may use the device API. */
class EnsureActiveDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->user();

        if (! $device instanceof Device
            || $device->status !== DeviceStatus::Active
            || $device->room_id === null
            || ! $device->tokenCan('tv')) {
            abort(403, 'Perangkat tidak diizinkan.');
        }

        return $next($request);
    }
}
