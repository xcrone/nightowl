<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\NightowlUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NightowlUser>
 */
class NightowlUserFactory extends Factory
{
    protected $model = NightowlUser::class;

    public function definition(): array
    {
        return [
            'app_id' => 'test_app',
            'user_id' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'timestamp' => (string) now()->timestamp,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
