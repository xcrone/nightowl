<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\NotificationRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationRecord>
 */
class NotificationRecordFactory extends Factory
{
    protected $model = NotificationRecord::class;

    public function definition(): array
    {
        return [
            'app_id' => 'test_app',
            'trace_id' => (string) Str::uuid(),
            'notification' => fake()->randomElement(['App\\Notifications\\OrderShipped', 'App\\Notifications\\AlertTriggered', 'App\\Notifications\\DailyDigest']),
            'channel' => fake()->randomElement(['mail', 'slack', 'database']),
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => (string) fake()->numberBetween(1, 500),
            'duration' => fake()->numberBetween(1000, 150000),
            'failed' => false,
            'queued' => false,
            'created_at' => now(),
        ];
    }
}
