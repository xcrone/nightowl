<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\LogRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LogRecord>
 */
class LogRecordFactory extends Factory
{
    protected $model = LogRecord::class;

    public function definition(): array
    {
        return [
            'app_id' => 'test_app',
            'trace_id' => (string) Str::uuid(),
            'level' => 'info',
            'message' => fake()->sentence(),
            'channel' => 'stack',
            // nightowl_logs.created_at is a plain string column (not a
            // timestamp), populated by the agent's drain writer verbatim.
            'created_at' => now()->toIso8601String(),
        ];
    }
}
