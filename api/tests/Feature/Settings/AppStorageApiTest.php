<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/apps/{app}/settings/storage — the Settings "Storage" tab's live
 * Postgres footprint (App\Domains\Settings\Actions\ShowAppStorage). Reports
 * the physical on-disk size (pg_total_relation_size, incl. indexes) of the
 * nightowl_* telemetry tables.
 */
class AppStorageApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    public function test_storage_reports_telemetry_table_footprint(): void
    {
        $user = User::factory()->create();
        $this->seedApp('store_app');

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

        // …and a known telemetry table shows up with a non-zero physical size.
        $names = array_column($tables, 'name');
        $this->assertContains('nightowl_requests', $names);
        $this->assertGreaterThan(0, $response->json('total_bytes'));
    }

    public function test_storage_requires_authentication(): void
    {
        $this->seedApp('store_app');

        $this->getJson('/api/apps/store_app/settings/storage')->assertUnauthorized();
    }
}
