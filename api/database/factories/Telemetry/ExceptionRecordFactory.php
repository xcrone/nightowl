<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\ExceptionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExceptionRecord>
 */
class ExceptionRecordFactory extends Factory
{
    protected $model = ExceptionRecord::class;

    public function definition(): array
    {
        return [
            'app_id' => 'test_app',
            'trace_id' => (string) Str::uuid(),
            'class' => 'RuntimeException',
            'message' => fake()->sentence(),
            'fingerprint' => Str::random(32),
            'handled' => false,
            'created_at' => now(),
        ];
    }
}
