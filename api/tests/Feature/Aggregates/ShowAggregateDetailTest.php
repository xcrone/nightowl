<?php

namespace Tests\Feature\Aggregates;

use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\QueryRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use App\Support\AggregateKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/apps/{app}/aggregate/{resource}/{key} — the per-key drill-down
 * (App\Actions\Aggregates\ShowAggregateDetail), docs/pages/aggregate-detail.md.
 */
class ShowAggregateDetailTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['nightowl_requests', 'nightowl_jobs', 'nightowl_queries'] as $t) {
            DB::connection('nightowl')->table($t)->delete();
        }

        $this->seedApp('agg_app');
    }

    private function key(string $raw): string
    {
        return AggregateKey::encode($raw);
    }

    public function test_requests_detail_returns_panels_percentiles_and_occurrences(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->count(3)->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'method' => 'POST', 'status_code' => 200, 'duration' => 100]);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'status_code' => 500, 'duration' => 900]);
        // A different route must not leak into this key.
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/login', 'status_code' => 200, 'duration' => 50]);

        $res = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests/'.$this->key('/api/orders'));

        $res->assertOk();
        $this->assertSame('/api/orders', $res->json('label'));
        $this->assertSame('POST', $res->json('meta.method'));

        // Panels: nested, scoped to just this key.
        $this->assertSame(4, $res->json('panels.requests.total'));
        $this->assertSame(3, $res->json('panels.requests.c2xx'));
        $this->assertSame(1, $res->json('panels.requests.c5xx'));

        // P50/P95/P99 breakdown.
        $this->assertNotNull($res->json('percentiles.p50'));
        $this->assertNotNull($res->json('percentiles.p95'));
        $this->assertNotNull($res->json('percentiles.p99'));

        // Paginated occurrences — only the 4 /api/orders rows.
        $this->assertSame(4, $res->json('occurrences.total'));
        $paths = collect($res->json('occurrences.data'))->pluck('route_path')->unique()->all();
        $this->assertSame(['/api/orders'], $paths);
    }

    public function test_outcome_chip_filters_occurrences_by_count_bucket(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->count(3)->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'status_code' => 200]);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'status_code' => 500]);

        $res = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests/'.$this->key('/api/orders').'?outcome=c5xx');

        $res->assertOk();
        $this->assertSame(1, $res->json('occurrences.total'));
        $this->assertSame(500, $res->json('occurrences.data.0.status_code'));
    }

    public function test_bucket_chip_filters_occurrences_by_duration_threshold(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'duration' => 100]);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'duration' => 200]);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'duration' => 5000]);

        // ?bucket=p99 keeps only the slowest row(s) at/above the p99 threshold.
        $res = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests/'.$this->key('/api/orders').'?bucket=p99');

        $res->assertOk();
        $durations = collect($res->json('occurrences.data'))->pluck('duration')->all();
        $this->assertContains(5000, $durations);
        $this->assertNotContains(100, $durations);
    }

    public function test_detail_is_scoped_to_the_app(): void
    {
        $user = User::factory()->create();
        $this->seedApp('other_app');

        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/shared', 'status_code' => 200]);
        RequestRecord::factory()->create(['app_id' => 'other_app', 'route_path' => '/shared', 'status_code' => 200]);

        $res = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests/'.$this->key('/shared'));

        $res->assertOk();
        $this->assertSame(1, $res->json('occurrences.total'));
    }

    public function test_queries_detail_adds_info_and_sql(): void
    {
        $user = User::factory()->create();

        QueryRecord::factory()->count(2)->create([
            'app_id' => 'agg_app', 'group_hash' => 'gh-orders',
            'sql_query' => 'select * from "orders" where "id" = ?',
            'connection' => 'pgsql', 'connection_type' => 'read',
            'environment' => 'production', 'duration' => 250,
        ]);

        $res = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/queries/'.$this->key('gh-orders'));

        $res->assertOk();
        $this->assertSame('select * from "orders" where "id" = ?', $res->json('sql'));
        $this->assertSame('read', $res->json('meta.rw'));
        $this->assertSame(2, $res->json('info.calls'));
        $this->assertSame(500, $res->json('info.total_time'));
        $this->assertContains('production', $res->json('info.environments'));
        $this->assertArrayHasKey('p95', $res->json('info'));
    }

    public function test_jobs_detail_outcome_chip_uses_status_bucket(): void
    {
        $user = User::factory()->create();

        JobRecord::factory()->count(2)->create(['app_id' => 'agg_app', 'job_class' => 'App\\Jobs\\Pay', 'status' => 'processed']);
        JobRecord::factory()->create(['app_id' => 'agg_app', 'job_class' => 'App\\Jobs\\Pay', 'status' => 'failed']);

        $res = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/jobs/'.$this->key('App\\Jobs\\Pay').'?outcome=failed');

        $res->assertOk();
        $this->assertSame(3, $res->json('panels.attempts.total'));
        $this->assertSame(1, $res->json('occurrences.total'));
        $this->assertSame('failed', $res->json('occurrences.data.0.status'));
    }

    public function test_per_page_zero_is_floored_and_does_not_500(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'status_code' => 200, 'duration' => 100]);

        $this->actingAs($user)
            ->getJson('/api/apps/agg_app/aggregate/requests/'.$this->key('/api/orders').'?per_page=0')
            ->assertOk();
    }

    public function test_non_detail_aggregate_is_not_found(): void
    {
        $user = User::factory()->create();

        // cache/users/exceptions are not detail-enabled (route constraint 404s).
        $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/cache/'.$this->key('k'))->assertNotFound();
        $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/exceptions/'.$this->key('X'))->assertNotFound();
    }
}
