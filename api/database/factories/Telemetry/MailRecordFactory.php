<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\MailRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MailRecord>
 */
class MailRecordFactory extends Factory
{
    protected $model = MailRecord::class;

    public function definition(): array
    {
        return [
            'app_id' => 'test_app',
            'trace_id' => (string) Str::uuid(),
            'mailer' => 'smtp',
            'mailable' => fake()->randomElement(['App\\Mail\\OrderConfirmation', 'App\\Mail\\WelcomeEmail', 'App\\Mail\\DailyReport']),
            'subject' => fake()->sentence(3),
            'recipients' => fake()->safeEmail(),
            'duration' => fake()->numberBetween(1000, 200000),
            'failed' => false,
            'queued' => false,
            'created_at' => now(),
        ];
    }
}
