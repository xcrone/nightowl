<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\RequestRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RequestRecord>
 */
class RequestRecordFactory extends Factory
{
    protected $model = RequestRecord::class;

    public function definition(): array
    {
        return [
            'trace_id' => (string) Str::uuid(),
            'method' => 'GET',
            'url' => fake()->url(),
            'route_name' => fake()->word(),
            'status_code' => 200,
            'duration' => fake()->numberBetween(1000, 500000),
            'exceptions' => 0,
            'queries' => 0,
            'created_at' => now(),
        ];
    }
}
