<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\QueryRollup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use NightOwl\Support\QueryHistogram;

/**
 * @extends Factory<QueryRollup>
 */
class QueryRollupFactory extends Factory
{
    protected $model = QueryRollup::class;

    public function definition(): array
    {
        $duration = fake()->numberBetween(1000, 50000);

        $bins = array_fill(0, QueryHistogram::binCount(), 0);
        $bins[QueryHistogram::binIndex($duration)] = 1;

        $attributes = [
            'group_hash' => Str::random(32),
            'bucket_start' => now()->startOfMinute(),
            'environment' => 'testing',
            'connection' => 'pgsql',
            'call_count' => 1,
            'total_duration' => $duration,
            'min_duration' => $duration,
            'max_duration' => $duration,
            'sql_query' => 'select * from example',
        ];

        foreach ($bins as $i => $count) {
            $attributes[sprintf('hist_%02d', $i)] = $count;
        }

        return $attributes;
    }
}
