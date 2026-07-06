<?php

namespace Tests\Feature\Apps;

use App\Models\Telemetry\CacheEvent;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AggregateApiTest extends TestCase
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
}
