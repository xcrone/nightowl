<?php

namespace Tests\Feature\Apps;

use App\Models\Org;
use App\Models\Team;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/apps + GET /api/apps/{app} (App\Domains\Apps\Actions\ListApps,
 * ShowApp). Relocated from tests/Feature/Apps/AppScopingTest.php (Batch 4
 * of the controllers -> Actions migration) — that file's telemetry-scoping
 * test moved to tests/Feature/Telemetry/TelemetryApiTest.php in Batch 7
 * (TelemetryController -> App\Actions\Telemetry\*), which emptied and
 * removed the original file.
 */
class AppApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('nightowl')->table('nightowl_requests')->delete();
    }

    public function test_apps_endpoint_returns_teams_and_apps(): void
    {
        $user = User::factory()->create();
        $app = $this->seedApp();

        $this->actingAs($user)
            ->getJson('/api/apps')
            ->assertOk()
            ->assertJsonStructure(['org' => ['id', 'uuid', 'name', 'account_email'], 'teams' => [['id', 'uuid', 'name', 'apps_count', 'apps']]]);
    }

    public function test_app_show_returns_the_app(): void
    {
        $user = User::factory()->create();
        $app = $this->seedApp();

        $this->actingAs($user)
            ->getJson("/api/apps/{$app->app_id}")
            ->assertOk()
            ->assertJsonPath('app_id', $app->app_id)
            ->assertJsonPath('name', 'Test App')
            ->assertJsonStructure(['team' => ['id', 'uuid', 'name'], 'org' => ['id', 'uuid', 'name', 'account_email']]);
    }

    public function test_unknown_app_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/apps/does-not-exist/requests')
            ->assertNotFound();
    }

    public function test_apps_endpoint_returns_the_current_users_own_org_not_the_first_one_in_the_table(): void
    {
        // A pre-existing org (e.g. seeded demo data) that comes first in the
        // table, but which the acting user is NOT a member of.
        $otherOrg = Org::query()->create(['name' => 'Someone Else', 'account_email' => 'other@example.com']);
        Team::query()->create(['org_id' => $otherOrg->id, 'name' => 'Someone Else Team']);

        $user = User::factory()->create();
        $myOrg = Org::query()->create(['name' => 'My Org', 'account_email' => $user->email]);
        $myOrg->users()->attach($user->id);
        Team::query()->create(['org_id' => $myOrg->id, 'name' => 'My Team']);

        $this->actingAs($user)
            ->getJson('/api/apps')
            ->assertOk()
            ->assertJsonPath('org.uuid', $myOrg->uuid)
            ->assertJsonPath('teams.0.name', 'My Team');
    }

    public function test_apps_endpoint_returns_the_org_selected_via_the_org_query_param(): void
    {
        $user = User::factory()->create();

        $orgA = Org::query()->create(['name' => 'Org A', 'account_email' => 'a@example.com']);
        $orgA->users()->attach($user->id);
        Team::query()->create(['org_id' => $orgA->id, 'name' => 'Team A']);

        $orgB = Org::query()->create(['name' => 'Org B', 'account_email' => 'b@example.com']);
        $orgB->users()->attach($user->id);
        Team::query()->create(['org_id' => $orgB->id, 'name' => 'Team B']);

        $this->actingAs($user)
            ->getJson("/api/apps?org={$orgB->uuid}")
            ->assertOk()
            ->assertJsonPath('org.uuid', $orgB->uuid)
            ->assertJsonPath('teams.0.name', 'Team B');
    }

    public function test_apps_endpoint_rejects_an_org_query_param_the_user_doesnt_belong_to(): void
    {
        $user = User::factory()->create();
        $myOrg = Org::query()->create(['name' => 'My Org', 'account_email' => 'me@example.com']);
        $myOrg->users()->attach($user->id);

        $otherOrg = Org::query()->create(['name' => 'Not Mine', 'account_email' => 'other@example.com']);

        $this->actingAs($user)
            ->getJson("/api/apps?org={$otherOrg->uuid}")
            ->assertNotFound();
    }

    /**
     * Regression: web/src/store/org.js persists the selected org's uuid in
     * localStorage as `currentOrgUuid`, which outlives logins/logouts/DB
     * resets. If that org is later deleted, the stale uuid used to 404 this
     * endpoint permanently — org.org never populated, so every mutation
     * guarded by `if (!org.org?.uuid) return` (save org, add team, add app)
     * silently no-op'd with no error shown. A missing org should fall back
     * to the user's own org instead, exactly like the no-param case.
     */
    public function test_apps_endpoint_falls_back_to_the_users_own_org_when_the_requested_org_no_longer_exists(): void
    {
        $user = User::factory()->create();
        $myOrg = Org::query()->create(['name' => 'My Org', 'account_email' => 'me@example.com']);
        $myOrg->users()->attach($user->id);
        Team::query()->create(['org_id' => $myOrg->id, 'name' => 'My Team']);

        $staleUuid = (string) Str::uuid();

        $this->actingAs($user)
            ->getJson("/api/apps?org={$staleUuid}")
            ->assertOk()
            ->assertJsonPath('org.uuid', $myOrg->uuid)
            ->assertJsonPath('teams.0.name', 'My Team');
    }

    /**
     * Regression for the misleading "% err" health badge: error_rate must
     * only reflect real server errors (status_code >= 500) — ordinary
     * client errors (401/404/429/...) are reported separately via
     * count_4xx and must never inflate error_rate.
     */
    public function test_health_error_rate_counts_only_5xx_not_4xx(): void
    {
        $user = User::factory()->create();
        $app = $this->seedApp();

        RequestRecord::factory()->count(6)->create(['app_id' => $app->app_id, 'status_code' => 200]);
        RequestRecord::factory()->count(3)->create(['app_id' => $app->app_id, 'status_code' => 404]);
        RequestRecord::factory()->count(1)->create(['app_id' => $app->app_id, 'status_code' => 500]);

        $this->actingAs($user)
            ->getJson('/api/apps')
            ->assertOk()
            ->assertJsonPath('teams.0.apps.0.request_count', 10)
            ->assertJsonPath('teams.0.apps.0.count_4xx', 3)
            ->assertJsonPath('teams.0.apps.0.count_5xx', 1)
            // 1 real (5xx) error out of 10 requests = 10%, not 40% (which
            // is what lumping the 3 404s in with the 1 500 would produce).
            // round(10.0, 1) serializes as a bare integer, not 10.0.
            ->assertJsonPath('teams.0.apps.0.error_rate', 10);
    }

    /**
     * The concrete repro: a single stray 4xx in the 1h health window must
     * not make an otherwise healthy app read as "100% err".
     */
    public function test_health_error_rate_is_zero_for_a_single_stray_4xx_request(): void
    {
        $user = User::factory()->create();
        $app = $this->seedApp();

        RequestRecord::factory()->create(['app_id' => $app->app_id, 'status_code' => 404]);

        $this->actingAs($user)
            ->getJson('/api/apps')
            ->assertOk()
            ->assertJsonPath('teams.0.apps.0.request_count', 1)
            ->assertJsonPath('teams.0.apps.0.count_4xx', 1)
            ->assertJsonPath('teams.0.apps.0.count_5xx', 0)
            ->assertJsonPath('teams.0.apps.0.error_rate', 0);
    }
}
