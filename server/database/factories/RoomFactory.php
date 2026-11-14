<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        $floor = fake()->numberBetween(1, 4);

        return [
            'number' => (string) ($floor * 100 + fake()->unique()->numberBetween(1, 99)),
            'floor' => $floor,
            'type' => 'Deluxe',
            'is_active' => true,
        ];
    }
}
