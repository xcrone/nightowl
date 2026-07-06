<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\OutgoingRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutgoingRequest>
 */
class OutgoingRequestFactory extends Factory
{
    protected $model = OutgoingRequest::class;

    public function definition(): array
    {
        $host = fake()->randomElement([
            'https://api.stripe.com',
            'https://api.mailgun.net',
            'https://webhooks.partner.com',
            'https://inventory.example.com',
        ]);

        return [
            'app_id' => 'test_app',
            'trace_id' => (string) Str::uuid(),
            'host' => $host,
            'method' => fake()->randomElement(['GET', 'POST']),
            'url' => $host.'/v1/resource',
            'status_code' => 200,
            'duration' => fake()->numberBetween(50000, 2000000),
            'created_at' => now(),
        ];
    }
}
