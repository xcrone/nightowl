<?php

namespace Database\Factories\Telemetry;

use App\Models\Telemetry\QueryRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QueryRecord>
 */
class QueryRecordFactory extends Factory
{
    protected $model = QueryRecord::class;

    public function definition(): array
    {
        return [
            'trace_id' => (string) Str::uuid(),
            'sql_query' => 'select * from "users" where "id" = ?',
            'connection' => 'pgsql',
            'duration' => fake()->numberBetween(100, 50000),
            'created_at' => now(),
        ];
    }
}
