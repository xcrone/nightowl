<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    public function definition(): array
    {
        return [
            'type' => 'exception',
            'status' => 'open',
            'priority' => 'medium',
            'exception_class' => 'RuntimeException',
            'exception_message' => fake()->sentence(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'occurrences_count' => 1,
            'users_count' => 1,
        ];
    }
}
