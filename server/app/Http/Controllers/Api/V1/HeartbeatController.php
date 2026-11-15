<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommandStatus;
use App\Http\Controllers\Controller;
use App\Models\AppWhitelist;
use App\Models\Channel;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Facility;
use App\Models\HotelProfile;
use App\Models\Slide;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    /** Periodic check-in: report status, receive pending commands. */
    public function beat(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $data = $request->validate([
            'app_version' => ['nullable', 'string', 'max:32'],
            'app_version_code' => ['nullable', 'integer', 'min:0'],
            'kiosk_enabled' => ['nullable', 'boolean'],
            'device_owner' => ['nullable', 'boolean'],
            'is_default_home' => ['nullable', 'boolean'],
            'can_draw_overlays' => ['nullable', 'boolean'],
            'can_install_updates' => ['nullable', 'boolean'],
            'stock_launcher_active' => ['nullable', 'boolean'],
            'screen_on' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('screen_on', $data) && $data['screen_on'] !== null && $data['screen_on'] !== $device->screen_on) {
            $device->screen_changed_at = now();
        }
        $device->fill(array_filter($data, fn ($v) => $v !== null));
        $device->last_seen_at = now();
        $device->last_ip = $request->ip();
        $device->save();

        $commands = $device->commands()->where('status', CommandStatus::Pending)->orderBy('id')->get();
        if ($commands->isNotEmpty()) {
            DeviceCommand::query()->whereKey($commands->modelKeys())
                ->update(['status' => CommandStatus::Delivered, 'delivered_at' => now()]);
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'content_version' => $this->contentVersion($device),
            'commands' => $commands->map(fn (DeviceCommand $c) => [
                'id' => $c->id,
                'type' => $c->type->value,
                'payload' => $c->payload,
            ]),
        ]);
    }

    public function ack(Request $request, DeviceCommand $command): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        abort_unless($command->device_id === $device->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:done,failed'],
            'result' => ['nullable', 'array'],
        ]);

        $command->update([
            'status' => CommandStatus::from($data['status']),
            'result' => $data['result'] ?? null,
            'completed_at' => now(),
        ]);

        Audit::log('device.command_'.$data['status'], $command, ['type' => $command->type->value], $device);

        return response()->json(['ok' => true]);
    }

    /** Cheap fingerprint of everything the launcher shows; TV refetches when it changes. */
    private function contentVersion(Device $device): string
    {
        $parts = [
            HotelProfile::query()->max('updated_at'),
            Slide::query()->max('updated_at'), Slide::query()->count(),
            Channel::query()->max('updated_at'), Channel::query()->count(),
            Facility::query()->max('updated_at'), Facility::query()->count(),
            AppWhitelist::query()->max('updated_at'), AppWhitelist::query()->count(),
            $device->room?->guests()->max('updated_at'),
        ];

        return substr(sha1(implode('|', array_map('strval', $parts))), 0, 16);
    }
}
