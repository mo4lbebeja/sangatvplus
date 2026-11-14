<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Device> */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'device_uid' => Str::random(16),
            'model' => 'Sharp 4T-C50FJ1X',
            'status' => DeviceStatus::Active,
            'provisioned_at' => now(),
        ];
    }
}
