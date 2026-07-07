<?php

namespace Tests\Feature\DataManagement;

use App\Models\Telemetry\LogRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataManagementActionTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('nightowl')->table('nightowl_requests')->delete();
        DB::connection('nightowl')->table('nightowl_logs')->delete();
        $this->seedApp('ops_app');
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

    public function test_preview_narrows_by_user_id_across_categories(): void
    {
        $user = User::factory()->create();

        $window = ['created_at' => now()->subDays(40)];
        RequestRecord::factory()->count(2)->create($window + ['app_id' => 'ops_app', 'user_id' => 'user_a']);
        RequestRecord::factory()->create($window + ['app_id' => 'ops_app', 'user_id' => 'user_b']);
        LogRecord::factory()->count(3)->create([
            'app_id' => 'ops_app', 'user_id' => 'user_a', 'level' => 'info',
            'created_at' => now()->subDays(40)->toIso8601String(),
        ]);
        LogRecord::factory()->create([
            'app_id' => 'ops_app', 'user_id' => 'user_b', 'level' => 'info',
            'created_at' => now()->subDays(40)->toIso8601String(),
        ]);

        $this->actingAs($user)->postJson('/api/apps/ops_app/data-management/preview', [
            'from' => now()->subDays(60)->toIso8601String(),
            'to' => now()->subDays(30)->toIso8601String(),
            'types' => ['requests', 'logs'],
            'user_id' => 'user_a',
        ])->assertOk()
            ->assertJsonPath('counts.requests', 2)
            ->assertJsonPath('counts.logs', 3)
            ->assertJsonPath('total', 5);
    }

    public function test_preview_narrows_logs_by_level(): void
    {
        $user = User::factory()->create();

        LogRecord::factory()->create([
            'app_id' => 'ops_app', 'user_id' => 'user_a', 'level' => 'error',
            'created_at' => now()->subDays(40)->toIso8601String(),
        ]);
        LogRecord::factory()->count(2)->create([
            'app_id' => 'ops_app', 'user_id' => 'user_a', 'level' => 'info',
            'created_at' => now()->subDays(40)->toIso8601String(),
        ]);

        $this->actingAs($user)->postJson('/api/apps/ops_app/data-management/preview', [
            'from' => now()->subDays(60)->toIso8601String(),
            'to' => now()->subDays(30)->toIso8601String(),
            'types' => ['logs'],
            'level' => 'error',
        ])->assertOk()
            ->assertJsonPath('counts.logs', 1)
            ->assertJsonPath('total', 1);
    }

    public function test_data_management_requires_at_least_one_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/apps/ops_app/data-management/preview', [
            'to' => now()->toIso8601String(), 'types' => [],
        ])->assertUnprocessable();
    }
}
