<?php

namespace Tests\Feature\Apps;

use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Models\Telemetry\NightowlUser;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IssueUserDetailTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['nightowl_requests', 'nightowl_exceptions', 'nightowl_issues', 'nightowl_issue_activity', 'nightowl_users', 'nightowl_jobs'] as $t) {
            DB::connection('nightowl')->table($t)->delete();
        }

        $this->seedApp('det_app');
    }

    public function test_issue_detail_returns_occurrences_and_environment_breakdown(): void
    {
        $user = User::factory()->create();
        $issue = Issue::factory()->create(['app_id' => 'det_app', 'group_hash' => 'gh1', 'exception_class' => 'App\\X']);
        ExceptionRecord::factory()->count(2)->create(['app_id' => 'det_app', 'group_hash' => 'gh1', 'environment' => 'production']);
        // noise: different app + different hash must not appear.
        ExceptionRecord::factory()->create(['app_id' => 'other', 'group_hash' => 'gh1']);

        $response = $this->actingAs($user)->getJson("/api/apps/det_app/issues/{$issue->id}");

        $response->assertOk()
            ->assertJsonPath('issue.id', $issue->id)
            ->assertJsonCount(2, 'occurrences')
            ->assertJsonPath('occurrences_by_environment.0.environment', 'production')
            ->assertJsonPath('occurrences_by_environment.0.count', 2);
    }

    public function test_assign_and_priority_record_activity(): void
    {
        $user = User::factory()->create();
        $issue = Issue::factory()->create(['app_id' => 'det_app', 'priority' => 'low']);

        $this->actingAs($user)->postJson("/api/apps/det_app/issues/{$issue->id}/assign", ['assigned_to' => 'alice'])
            ->assertOk()->assertJsonPath('assigned_to', 'alice');

        $this->actingAs($user)->postJson("/api/apps/det_app/issues/{$issue->id}/priority", ['priority' => 'high'])
            ->assertOk()->assertJsonPath('priority', 'high');

        $this->assertDatabaseHas('nightowl_issue_activity', ['issue_id' => $issue->id, 'action' => 'priority_changed', 'new_value' => 'high'], 'nightowl');
    }

    public function test_resolve_reopen_and_ignore_transitions_are_app_scoped_routes(): void
    {
        $user = User::factory()->create();
        $issue = Issue::factory()->create(['app_id' => 'det_app', 'status' => 'open']);

        $this->actingAs($user)
            ->postJson("/api/apps/det_app/issues/{$issue->id}/resolve")
            ->assertOk()
            ->assertJsonPath('status', 'resolved');

        $this->assertDatabaseHas('nightowl_issue_activity', [
            'issue_id' => $issue->id, 'action' => 'status_changed',
            'old_value' => 'open', 'new_value' => 'resolved',
        ], 'nightowl');

        $this->actingAs($user)
            ->postJson("/api/apps/det_app/issues/{$issue->id}/reopen")
            ->assertOk()
            ->assertJsonPath('status', 'open');

        $this->actingAs($user)
            ->postJson("/api/apps/det_app/issues/{$issue->id}/ignore")
            ->assertOk()
            ->assertJsonPath('status', 'ignored');
    }

    public function test_can_comment_on_an_issue_via_app_scoped_route(): void
    {
        $user = User::factory()->create();
        $issue = Issue::factory()->create(['app_id' => 'det_app']);

        $this->actingAs($user)
            ->postJson("/api/apps/det_app/issues/{$issue->id}/comments", ['body' => 'Investigating.'])
            ->assertCreated()
            ->assertJsonPath('body', 'Investigating.');

        $this->actingAs($user)
            ->getJson("/api/apps/det_app/issues/{$issue->id}/comments")
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_issue_actions_404_when_hit_through_the_wrong_apps_url(): void
    {
        $user = User::factory()->create();
        $this->seedApp('other_app');
        $issue = Issue::factory()->create(['app_id' => 'det_app']);

        $this->actingAs($user)->getJson("/api/apps/other_app/issues/{$issue->id}")->assertNotFound();

        $this->actingAs($user)->postJson("/api/apps/other_app/issues/{$issue->id}/resolve")->assertNotFound();

        $this->actingAs($user)->postJson("/api/apps/other_app/issues/{$issue->id}/assign", ['assigned_to' => 'alice'])
            ->assertNotFound();

        $this->actingAs($user)->postJson("/api/apps/other_app/issues/{$issue->id}/comments", ['body' => 'Nope.'])
            ->assertNotFound();

        $this->actingAs($user)->getJson("/api/apps/other_app/issues/{$issue->id}/comments")->assertNotFound();
    }

    public function test_user_detail_aggregates_requests_and_routes(): void
    {
        $user = User::factory()->create();
        NightowlUser::factory()->create(['app_id' => 'det_app', 'user_id' => 'user_9', 'email' => 'nine@example.com']);
        RequestRecord::factory()->count(2)->create(['app_id' => 'det_app', 'user_id' => 'user_9', 'route_path' => '/api/orders', 'status_code' => 200]);
        RequestRecord::factory()->create(['app_id' => 'det_app', 'user_id' => 'user_9', 'route_path' => '/api/orders', 'status_code' => 500]);
        // another user's traffic must not leak in.
        RequestRecord::factory()->create(['app_id' => 'det_app', 'user_id' => 'user_other', 'route_path' => '/x']);

        $response = $this->actingAs($user)->getJson('/api/apps/det_app/users/user_9');

        $response->assertOk()
            ->assertJsonPath('user.email', 'nine@example.com')
            ->assertJsonPath('requests.total', 3)
            ->assertJsonPath('requests.c5xx', 1)
            ->assertJsonPath('top_routes.0.route_path', '/api/orders')
            ->assertJsonPath('top_routes.0.count', 3);
    }
}
