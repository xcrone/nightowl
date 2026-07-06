<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\JobRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobRecord>
 */
class JobRecordFactory extends Factory
{
    protected $model = JobRecord::class;

    public function definition(): array
    {
        return [
            'app_id' => 'test_app',
            'trace_id' => (string) Str::uuid(),
            'job_class' => 'App\\Jobs\\SendInvoice',
            'queue' => 'default',
            'status' => 'processed',
            'attempts' => 1,
            'duration' => fake()->numberBetween(1000, 500000),
            'created_at' => now(),
        ];
    }
}
