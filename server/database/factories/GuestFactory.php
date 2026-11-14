<?php

namespace Database\Factories;

use App\Enums\GuestStatus;
use App\Models\Guest;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guest> */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'salutation' => fake()->randomElement(['Bapak', 'Ibu']),
            'name' => fake()->name(),
            'language' => 'id',
            'status' => GuestStatus::InHouse,
            'checked_in_at' => now(),
        ];
    }
}
