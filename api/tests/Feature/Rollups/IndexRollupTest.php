<?php

namespace Tests\Feature\Rollups;

use App\Models\Telemetry\CacheRollup;
use App\Models\Telemetry\QueryRollup;
use App\Models\Telemetry\RequestRollup;
use App\Models\Telemetry\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use NightOwl\Support\QueryHistogram;
use Tests\TestCase;

/**
 * GET /api/rollups/{type} (App\Actions\Rollups\IndexRollup). The
 * test_query_rollups_aggregate_and_estimate_percentiles case is relocated
 * from tests/Feature/Telemetry/NewScopeApiTest.php (that file's other halves
 * — NightowlUser/AlertChannel/Setting/Issues — already moved to their own
 * domains in earlier batches; this was its last remaining case). The rest
 * are new Batch 7 coverage for gaps flagged in the migration plan:
 * environment filter, today's lack of app-scoping (documented, not changed),
 * and the non-histogram (cache-events) path.
 */
class IndexRollupTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'nightowl_users', 'nightowl_alert_channels', 'nightowl_settings',
            'nightowl_query_rollups', 'nightowl_request_rollups', 'nightowl_cache_rollups',
            'nightowl_issues', 'nightowl_issue_activity', 'nightowl_issue_comments',
        ] as $table) {
            DB::connection('nightowl')->table($table)->delete();
        }
    }

    public function test_query_rollups_aggregate_and_estimate_percentiles(): void
    {
        $admin = User::factory()->create();
        Setting::query()->delete();
        // Three separate per-minute buckets for the same query, matching how
        // real rollups accumulate — bucket_start must differ or they'd
        // collide on the composite primary key.
        QueryRollup::factory()
            ->count(3)
            ->sequence(fn ($sequence) => ['bucket_start' => now()->startOfMinute()->subMinutes($sequence->index)])
            ->create(['group_hash' => 'abc']);

        $response = $this->actingAs($admin)->getJson('/api/rollups/queries');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(3, $data[0]['call_count']);
        $this->assertArrayHasKey('p95', $data[0]);
    }

    public function test_environment_filter_excludes_other_environments(): void
    {
        $admin = User::factory()->create();

        QueryRollup::factory()->create(['group_hash' => 'staging-hash', 'environment' => 'staging']);
        QueryRollup::factory()->create(['group_hash' => 'prod-hash', 'environment' => 'production']);

        $response = $this->actingAs($admin)->getJson('/api/rollups/queries?environment=production');

        $response->assertOk();
        $hashes = collect($response->json('data'))->pluck('group_hash')->all();
        $this->assertSame(['prod-hash'], $hashes);
    }

    /**
     * Documents today's actual behavior: `/api/rollups/{type}` is a legacy,
     * non-app-scoped endpoint (unlike its per-app `IndexAggregate`
     * equivalent) — it was never given an app_id filter even though the
     * underlying rollup tables carry an app_id column. This is not new
     * scoping logic, just coverage for the existing gap per the migration
     * plan's explicit call-out.
     */
    public function test_rollups_are_not_scoped_by_app(): void
    {
        $admin = User::factory()->create();

        QueryRollup::factory()->create(['group_hash' => 'mine-hash', 'app_id' => 'app_a']);
        QueryRollup::factory()->create(['group_hash' => 'theirs-hash', 'app_id' => 'app_b']);

        $response = $this->actingAs($admin)->getJson('/api/rollups/queries');

        $response->assertOk();
        $hashes = collect($response->json('data'))->pluck('group_hash')->all();
        sort($hashes);
        $this->assertSame(['mine-hash', 'theirs-hash'], $hashes);
    }

    /** cache-events is the one rollup type with no histogram (grouped by key+store, not group_hash). */
    public function test_cache_rollups_have_no_histogram_percentiles(): void
    {
        $admin = User::factory()->create();

        CacheRollup::query()->forceCreate([
            'key' => 'user:1', 'store' => 'redis', 'bucket_start' => now()->startOfMinute(), 'environment' => 'production',
            'call_count' => 5, 'hits' => 4, 'misses' => 1,
            'total_duration' => 500, 'min_duration' => 50, 'max_duration' => 150,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/rollups/cache-events');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(5, $data[0]['call_count']);
        $this->assertArrayNotHasKey('p50', $data[0]);
        $this->assertArrayNotHasKey('p95', $data[0]);
        $this->assertArrayNotHasKey('p99', $data[0]);
        $this->assertArrayNotHasKey('label', $data[0]); // cache-events has no 'label' column configured
    }

    /** requests is a histogram type, like queries, but a distinct rollup table/model. */
    public function test_request_rollups_aggregate_with_histogram(): void
    {
        $admin = User::factory()->create();

        $duration = 25000;
        $bins = array_fill(0, QueryHistogram::binCount(), 0);
        $bins[QueryHistogram::binIndex($duration)] = 2;

        RequestRollup::query()->forceCreate(array_merge([
            'group_hash' => 'req-hash', 'bucket_start' => now()->startOfMinute(), 'environment' => 'production',
            'call_count' => 2, 'total_duration' => $duration * 2, 'min_duration' => $duration, 'max_duration' => $duration,
            'route_path' => '/api/orders',
        ], array_combine(
            array_map(fn ($i) => sprintf('hist_%02d', $i), array_keys($bins)),
            $bins
        )));

        $response = $this->actingAs($admin)->getJson('/api/rollups/requests');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(2, $data[0]['call_count']);
        $this->assertSame('/api/orders', $data[0]['label']);
        $this->assertArrayHasKey('p95', $data[0]);
    }
}
