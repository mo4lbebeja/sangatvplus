<?php

namespace Database\Factories;

use App\Enums\SourceType;
use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Channel> */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(100, 999);

        return [
            'number' => $n,
            'name' => 'Channel '.$n,
            'source_type' => SourceType::LocalMulticast,
            'url' => 'udp://239.1.1.'.($n % 250).':1234',
            'sort_order' => $n,
            'is_active' => true,
        ];
    }
}
