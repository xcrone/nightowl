<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\CacheEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CacheEvent>
 */
class CacheEventFactory extends Factory
{
    protected $model = CacheEvent::class;

    public function definition(): array
    {
        return [
            'app_id' => 'test_app',
            'trace_id' => (string) Str::uuid(),
            'event_type' => fake()->randomElement(['hit', 'missed', 'write', 'forget']),
            'key' => fake()->randomElement(['product:catalog', 'users:42', 'settings']),
            'store' => 'redis',
            'duration' => fake()->numberBetween(50, 5000),
            'created_at' => now(),
        ];
    }
}
