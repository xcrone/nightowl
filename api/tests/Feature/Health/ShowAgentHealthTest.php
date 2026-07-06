<?php

namespace Tests\Feature\Health;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/apps/{app}/health (App\Actions\Health\ShowAgentHealth). Relocated
 * from tests/Feature/Apps/HealthDataManagementTest.php (that file's
 * DataManagement half moved to tests/Feature/DataManagement/ in Batch 1;
 * this, its AgentHealth half, moves here in Batch 7 alongside the rest of
 * AgentHealthController's -> Actions migration).
 */
class ShowAgentHealthTest extends TestCase
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
}
