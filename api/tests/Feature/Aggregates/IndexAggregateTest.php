<?php

namespace Tests\Feature\Aggregates;

use App\Models\Telemetry\CacheEvent;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/apps/{app}/aggregate/{resource} (App\Actions\Aggregates\IndexAggregate).
 * Relocated from tests/Feature/Apps/AggregateApiTest.php in Batch 7
 * (AggregateController -> Actions migration) — same assertions/URLs, since
 * the route and JSON shape are unchanged.
 */
class IndexAggregateTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['nightowl_requests', 'nightowl_cache_events'] as $t) {
            DB::connection('nightowl')->table($t)->delete();
        }

        $this->seedApp('agg_app');
    }

    public function test_requests_aggregate_groups_per_route_with_status_buckets(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->count(3)->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'status_code' => 200]);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'status_code' => 500]);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/login', 'status_code' => 200]);

        $response = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests');

        $response->assertOk();
        $rows = collect($response->json('data'));
        $orders = $rows->firstWhere('route_path', '/api/orders');

        $this->assertSame(4, $orders['total']);
        $this->assertSame(3, $orders['c2xx']);
        $this->assertSame(1, $orders['c5xx']);
        $this->assertArrayHasKey('p95', $orders);
        // default sort is -total, so the busier route comes first.
        $this->assertSame('/api/orders', $rows->first()['route_path']);
    }

    public function test_aggregate_is_scoped_to_the_app(): void
    {
        $user = User::factory()->create();
        $this->seedApp('other_app');

        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/mine']);
        RequestRecord::factory()->create(['app_id' => 'other_app', 'route_path' => '/theirs']);

        $paths = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests')->json('data'))
            ->pluck('route_path')->all();

        $this->assertContains('/mine', $paths);
        $this->assertNotContains('/theirs', $paths);
    }

    public function test_cache_aggregate_computes_hit_rate(): void
    {
        $user = User::factory()->create();

        CacheEvent::factory()->count(3)->create(['app_id' => 'agg_app', 'key' => 'k', 'store' => 'redis', 'event_type' => 'hit']);
        CacheEvent::factory()->create(['app_id' => 'agg_app', 'key' => 'k', 'store' => 'redis', 'event_type' => 'missed']);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/cache')->json('data'))
            ->firstWhere('key', 'k');

        $this->assertSame(3, $row['hits']);
        $this->assertSame(1, $row['misses']);
        $this->assertEqualsWithDelta(75.0, $row['hit_rate'], 0.001);
    }

    public function test_unknown_aggregate_resource_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/nope')->assertNotFound();
    }

    /**
     * Locks in the Batch 7 opportunistic fix: AggregateController's inline
     * ?q= ILIKE search is replaced by the shared App\Support\SearchTerm
     * (same helper TelemetryController/IndexTelemetryResource use), rather
     * than a second, cruder copy.
     */
    public function test_grouped_aggregate_search_matches_route_path(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders']);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/login']);

        $paths = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests?q=orders')->json('data'))
            ->pluck('route_path')->all();

        $this->assertSame(['/api/orders'], $paths);
    }

    /**
     * Bespoke `users` aggregate search (matched in PHP against user_id +
     * email, not SQL ILIKE) also now goes through SearchTerm::fromRequest()
     * for its trimming/length-cap, rather than its own inline trim().
     */
    public function test_users_aggregate_search_matches_user_id(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->create(['app_id' => 'agg_app', 'user_id' => 'user-alpha']);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'user_id' => 'user-beta']);

        $ids = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/users?q=alpha')->json('data'))
            ->pluck('user_id')->all();

        $this->assertSame(['user-alpha'], $ids);
    }
}
