<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\ProvisioningCode;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProvisionController extends Controller
{
    /**
     * Exchange a one-time provisioning code (issued in the CMS for a room)
     * for a device token. Re-provisioning the same TV revokes its old tokens.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16'],
            'device_uid' => ['required', 'string', 'max:128'],
            'model' => ['nullable', 'string', 'max:255'],
            'android_version' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'app_version_code' => ['nullable', 'integer', 'min:0'],
            'device_owner' => ['nullable', 'boolean'],
        ]);

        $code = ProvisioningCode::query()
            ->with('room')
            ->where('code_hash', ProvisioningCode::hashCode($data['code']))
            ->first();

        if (! $code || ! $code->isUsable()) {
            Audit::log('device.provision_failed', null, ['device_uid' => $data['device_uid'], 'reason' => $code ? 'expired_or_used' : 'unknown_code']);

            return response()->json(['message' => 'Kode pendaftaran tidak valid atau kedaluwarsa.'], 422);
        }

        [$device, $token] = DB::transaction(function () use ($code, $data) {
            $device = Device::query()->firstOrNew(['device_uid' => $data['device_uid']]);
            $device->fill([
                'room_id' => $code->room_id,
                'model' => $data['model'] ?? null,
                'android_version' => $data['android_version'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'app_version_code' => $data['app_version_code'] ?? null,
                'device_owner' => (bool) ($data['device_owner'] ?? false),
                'status' => DeviceStatus::Active,
                'provisioned_at' => now(),
            ])->save();

            $device->tokens()->delete();
            $token = $device->createToken('tv-room-'.$code->room->number, ['tv'])->plainTextToken;

            $code->update(['used_at' => now(), 'device_id' => $device->id]);

            return [$device, $token];
        });

        Audit::log('device.provisioned', $device, ['room' => $code->room->number], $device);

        return response()->json([
            'token' => $token,
            'device' => ['id' => $device->id, 'room' => $code->room->number],
        ], 201);
    }
}
