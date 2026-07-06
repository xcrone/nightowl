<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\CommandRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommandRecord>
 */
class CommandRecordFactory extends Factory
{
    protected $model = CommandRecord::class;

    public function definition(): array
    {
        $command = fake()->randomElement([
            'queue:work --queue=default --tries=3',
            'migrate --force',
            'config:cache',
            'nightowl:prune --hours=6',
        ]);

        return [
            'app_id' => 'test_app',
            'trace_id' => (string) Str::uuid(),
            'class' => 'App\\Console\\Commands\\Example',
            'name' => Str::before($command, ' '),
            'command' => $command,
            'exit_code' => 0,
            'duration' => fake()->numberBetween(10000, 120000000),
            'created_at' => now(),
        ];
    }
}
