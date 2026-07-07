<?php

namespace Tests\Feature\Settings;

use App\Models\Telemetry\LogRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/apps/{app}/settings/storage — the Settings "Storage" tab's live
 * Postgres footprint (App\Domains\Settings\Actions\ShowAppStorage). Reports
 * the physical on-disk size (pg_total_relation_size, incl. indexes) of the
 * nightowl_* telemetry tables, scoped to the requesting app's row counts
 * (and its proportional share of each table's bytes) rather than
 * whole-database totals.
 */
class AppStorageApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        // Shared with real dev/simulator traffic outside this test's
        // transaction — clear so pre-existing rows don't skew the counts
        // these tests assert exact values for. Safe: rolled back on tearDown.
        foreach (['nightowl_requests', 'nightowl_logs'] as $table) {
            DB::connection('nightowl')->table($table)->delete();
        }
    }

    public function test_storage_reports_telemetry_table_footprint(): void
    {
        $user = User::factory()->create();
        $this->seedApp('store_app');
        RequestRecord::factory()->count(3)->create(['app_id' => 'store_app']);
        LogRecord::factory()->count(2)->create(['app_id' => 'store_app']);

        $response = $this->actingAs($user)->getJson('/api/apps/store_app/settings/storage')
            ->assertOk()
            ->assertJsonStructure([
                'tables' => [['name', 'bytes', 'rows']],
                'total_bytes',
            ]);

        $tables = $response->json('tables');
        $this->assertNotEmpty($tables);

        // Every reported table is a nightowl_* telemetry table…
        foreach ($tables as $table) {
            $this->assertStringStartsWith('nightowl_', $table['name']);
            $this->assertIsInt($table['bytes']);
            $this->assertIsInt($table['rows']);
        }

        $byName = collect($tables)->keyBy('name');
        $this->assertSame(3, $byName['nightowl_requests']['rows']);
        $this->assertSame(2, $byName['nightowl_logs']['rows']);

        // A whole-deployment table with no per-app relation is excluded
        // rather than misreported as either "0" or "everyone's".
        $this->assertArrayNotHasKey('nightowl_reports', $byName->all());
    }

    public function test_storage_is_scoped_to_the_requesting_app(): void
    {
        $user = User::factory()->create();
        $this->seedApp('busy_app');
        $this->seedApp('empty_app');
        RequestRecord::factory()->count(5)->create(['app_id' => 'busy_app']);
        LogRecord::factory()->count(4)->create(['app_id' => 'busy_app']);

        // A brand-new app with zero telemetry reports zero rows/bytes for
        // every table, even though busy_app's rows physically live in the
        // same shared Postgres tables.
        $response = $this->actingAs($user)->getJson('/api/apps/empty_app/settings/storage')
            ->assertOk();

        $byName = collect($response->json('tables'))->keyBy('name');
        $this->assertSame(0, $byName['nightowl_requests']['rows']);
        $this->assertSame(0, $byName['nightowl_requests']['bytes']);
        $this->assertSame(0, $byName['nightowl_logs']['rows']);
        $this->assertSame(0, $byName['nightowl_logs']['bytes']);
        $this->assertSame(0, $response->json('total_bytes'));

        // busy_app sees its own rows.
        $busyResponse = $this->actingAs($user)->getJson('/api/apps/busy_app/settings/storage')
            ->assertOk();
        $busyByName = collect($busyResponse->json('tables'))->keyBy('name');
        $this->assertSame(5, $busyByName['nightowl_requests']['rows']);
        $this->assertSame(4, $busyByName['nightowl_logs']['rows']);
    }

    public function test_storage_requires_authentication(): void
    {
        $this->seedApp('store_app');

        $this->getJson('/api/apps/store_app/settings/storage')->assertUnauthorized();
    }
}
