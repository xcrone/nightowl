<?php

namespace Tests\Feature\Timeseries;

use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/apps/{app}/timeseries/{metric} (App\Actions\Timeseries\ShowTimeseries).
 * Relocated from tests/Feature/Apps/DashboardApiTest.php (that file's
 * Dashboard-summary test moved to AppDashboardApiTest.php in Batch 4; this,
 * its Timeseries half, moves here in Batch 7 alongside the rest of
 * TimeseriesController's -> Actions migration).
 */
class ShowTimeseriesTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['nightowl_requests', 'nightowl_exceptions', 'nightowl_jobs'] as $t) {
            DB::connection('nightowl')->table($t)->delete();
        }

        $this->seedApp('dash_app');
    }

    public function test_timeseries_returns_buckets(): void
    {
        $user = User::factory()->create();
        RequestRecord::factory()->count(3)->create(['app_id' => 'dash_app']);

        $this->actingAs($user)->getJson('/api/apps/dash_app/timeseries/requests')
            ->assertOk()
            ->assertJsonStructure(['bucket_seconds', 'series' => [['t', 'values' => ['c2xx', 'c4xx', 'c5xx']]]]);
    }

    public function test_unknown_metric_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/apps/dash_app/timeseries/nope')->assertNotFound();
    }
}
