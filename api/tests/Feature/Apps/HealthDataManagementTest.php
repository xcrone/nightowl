<?php

namespace Tests\Feature\Apps;

use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthDataManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('nightowl')->table('nightowl_requests')->delete();
        $this->seedApp('ops_app');
    }

    public function test_agent_health_returns_status_instances_and_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/apps/ops_app/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonStructure([
                'score', 'last_report_at',
                'instances' => [['name', 'health', 'ingest_per_s', 'pg_latency_ms', 'cpu_pct', 'memory_bytes']],
                'history' => ['throughput', 'buffer', 'pg_latency', 'score'],
            ]);
    }

    public function test_data_management_preview_counts_rows_in_window(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->count(3)->create(['app_id' => 'ops_app', 'created_at' => now()->subDays(40)]);
        RequestRecord::factory()->create(['app_id' => 'ops_app', 'created_at' => now()]); // outside window

        $response = $this->actingAs($user)->postJson('/api/apps/ops_app/data-management/preview', [
            'from' => now()->subDays(60)->toIso8601String(),
            'to' => now()->subDays(30)->toIso8601String(),
            'types' => ['requests', 'queries'],
        ]);

        $response->assertOk()
            ->assertJsonPath('counts.requests', 3)
            ->assertJsonPath('counts.queries', 0)
            ->assertJsonPath('total', 3);
    }

    public function test_data_management_requires_at_least_one_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/apps/ops_app/data-management/preview', [
            'to' => now()->toIso8601String(), 'types' => [],
        ])->assertUnprocessable();
    }
}
