<?php

namespace Tests\Feature\Apps;

use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['nightowl_requests', 'nightowl_exceptions', 'nightowl_jobs'] as $t) {
            DB::connection('nightowl')->table($t)->delete();
        }

        $this->seedApp('dash_app');
    }

    public function test_dashboard_summarizes_requests_and_exceptions(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->count(2)->create(['app_id' => 'dash_app', 'status_code' => 200]);
        RequestRecord::factory()->create(['app_id' => 'dash_app', 'status_code' => 500]);
        ExceptionRecord::factory()->create(['app_id' => 'dash_app', 'handled' => false]);

        $response = $this->actingAs($user)->getJson('/api/apps/dash_app/dashboard');

        $response->assertOk()
            ->assertJsonPath('requests.total', 3)
            ->assertJsonPath('requests.c5xx', 1)
            ->assertJsonPath('exceptions.count', 1)
            ->assertJsonPath('exceptions.unhandled', 1)
            ->assertJsonStructure(['duration' => ['avg', 'p95'], 'jobs', 'job_duration', 'users' => ['most_active']]);
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
