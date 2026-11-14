<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\HotelProfile;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TechnicianController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_SECONDS = 900;

    /** Online PIN check with server-side lockout (5 wrong → 15 min). Every attempt is logged. */
    public function verify(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $data = $request->validate(['pin' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $key = 'tech-pin:'.$device->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            Audit::log('technician.pin_locked', $device, [], $device);

            return response()->json([
                'ok' => false,
                'locked' => true,
                'retry_after' => RateLimiter::availableIn($key),
            ], 423);
        }

        $ok = HotelProfile::current()->verifyTechnicianPin($data['pin']);

        if ($ok) {
            RateLimiter::clear($key);
            Audit::log('technician.pin_ok', $device, [], $device);

            return response()->json(['ok' => true]);
        }

        RateLimiter::hit($key, self::LOCK_SECONDS);
        $remaining = RateLimiter::remaining($key, self::MAX_ATTEMPTS);
        Audit::log('technician.pin_failed', $device, ['remaining' => $remaining], $device);

        return response()->json(['ok' => false, 'locked' => $remaining === 0, 'remaining' => $remaining], 422);
    }

    /** Whitelisted technician actions on the TV, recorded in the audit log. */
    public const EVENTS = ['settings_opened', 'launcher_restore_opened', 'launcher_disable_opened', 'home_role_requested', 'default_home_opened', 'device_hardened', 'device_relaxed', 'registration_reset'];

    public function event(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $data = $request->validate([
            'event' => ['required', 'string', 'in:'.implode(',', self::EVENTS)],
            'detail' => ['nullable', 'string', 'max:255'],
        ]);

        Audit::log('technician.'.$data['event'], $device, array_filter(['detail' => $data['detail'] ?? null]), $device);

        return response()->json(['ok' => true]);
    }

    /** Attempts made while offline (verified locally on the TV) are reported here later. */
    public function reportOfflineAttempts(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $data = $request->validate([
            'attempts' => ['required', 'array', 'max:100'],
            'attempts.*.at' => ['required', 'date'],
            'attempts.*.ok' => ['required', 'boolean'],
        ]);

        foreach ($data['attempts'] as $a) {
            Audit::log($a['ok'] ? 'technician.pin_ok_offline' : 'technician.pin_failed_offline', $device, ['at' => $a['at']], $device);
        }

        return response()->json(['ok' => true]);
    }
}
