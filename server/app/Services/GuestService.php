<?php

namespace App\Services;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\DeviceStatus;
use App\Enums\GuestStatus;
use App\Models\AppWhitelist;
use App\Models\Guest;
use App\Models\Room;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GuestService
{
    /** Check in a guest; any previous in-house guest of the room is checked out first. */
    public function checkIn(Room $room, array $data): Guest
    {
        return DB::transaction(function () use ($room, $data) {
            $room->guests()->where('status', GuestStatus::InHouse)->get()
                ->each(fn (Guest $g) => $this->checkOut($g));

            return $room->guests()->create([
                ...$data,
                'status' => GuestStatus::InHouse,
                'checked_in_at' => $data['checked_in_at'] ?? now(),
            ]);
        });
    }

    /**
     * Check out: mark the guest and queue a "clear third-party app data"
     * command for every active TV in the room (guest privacy).
     */
    public function checkOut(Guest $guest): int
    {
        if ($guest->status === GuestStatus::CheckedOut) {
            return 0;
        }

        return DB::transaction(function () use ($guest) {
            $guest->update(['status' => GuestStatus::CheckedOut, 'checked_out_at' => now()]);

            $packages = AppWhitelist::query()
                ->where('clear_on_checkout', true)
                ->pluck('package_name')
                ->all();

            $devices = $guest->room->devices()->where('status', DeviceStatus::Active)->get();

            foreach ($devices as $device) {
                $device->commands()->create([
                    'type' => CommandType::ClearAppData,
                    'payload' => ['packages' => $packages, 'reason' => 'checkout', 'guest_id' => $guest->id],
                    'status' => CommandStatus::Pending,
                    'created_by' => Auth::id(),
                ]);
            }

            Audit::log('guest.checked_out', $guest, ['room' => $guest->room->number, 'devices' => $devices->count()]);

            return $devices->count();
        });
    }
}
