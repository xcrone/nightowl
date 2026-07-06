<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\ScheduledTask;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScheduledTask>
 */
class ScheduledTaskFactory extends Factory
{
    protected $model = ScheduledTask::class;

    public function definition(): array
    {
        return [
            'app_id' => 'test_app',
            'trace_id' => (string) Str::uuid(),
            'command' => fake()->randomElement([
                'App\\Console\\Tasks\\CleanupOldData',
                'php artisan reports:nightly',
                'php artisan queue:prune-batches',
                'php artisan inspire',
            ]),
            'expression' => fake()->randomElement(['* * * * *', '0 * * * *', '0 0 * * *', '*/15 * * * *']),
            'status' => fake()->randomElement(['processed', 'failed', 'skipped']),
            'exit_code' => 0,
            'duration' => fake()->numberBetween(1000, 5000000),
            'created_at' => now(),
        ];
    }
}
