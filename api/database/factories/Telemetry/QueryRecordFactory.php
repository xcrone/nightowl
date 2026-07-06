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
        $sql = fake()->randomElement([
            'select * from "users" where "id" = ?',
            'select * from "orders" where "user_id" = ? order by "created_at" desc',
            'insert into "orders" ("total", "user_id") values (?, ?)',
            'update "products" set "stock" = ? where "id" = ?',
            'select count(*) from "invoices" where "status" = ?',
        ]);

        return [
            'app_id' => 'test_app',
            'trace_id' => (string) Str::uuid(),
            'sql_query' => $sql,
            // Group by query shape — the aggregate/queries page rolls up per
            // group_hash (real agent data sets this; keep factory data in sync).
            'group_hash' => md5($sql),
            'connection' => fake()->randomElement(['pgsql', 'nightowl']),
            'connection_type' => fake()->randomElement(['read', 'write']),
            'duration' => fake()->numberBetween(100, 50000),
            'created_at' => now(),
        ];
    }
}
