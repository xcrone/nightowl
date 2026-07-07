<?php

namespace Tests\Feature\Issues;

use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue detail + workflow endpoints (App\Domains\Issues\Actions\{ShowIssue,
 * AssignIssue,SetIssuePriority,TransitionIssueStatus,ListIssueComments,
 * StoreIssueComment}). Relocated from
 * tests/Feature/Apps/IssueUserDetailTest.php (Batch 6 of the controllers ->
 * Actions migration; that file's UserDetail-half was already relocated to
 * tests/Feature/Users/ in Batch 3, leaving only Issues content here, so the
 * old file is deleted rather than left empty).
 *
 * Adds explicit cross-app-404 coverage for priority/ignore/reopen (missing
 * before this migration — only show/resolve/assign/comments were covered)
 * given the Batch 5 DI bug (lorisleiva's authorize()/rules() resolve
 * route-bound Eloquent params via plain container call(), which can silently
 * receive empty model instances instead of the router's real bound ones).
 */
class IssueApiTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['nightowl_exceptions', 'nightowl_issues', 'nightowl_issue_activity', 'nightowl_issue_comments'] as $t) {
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
            ->assertJsonPath('issue.uuid', $issue->uuid)
            ->assertJsonCount(2, 'occurrences')
            ->assertJsonPath('occurrences_by_environment.0.environment', 'production')
            ->assertJsonPath('occurrences_by_environment.0.count', 2);
    }

    public function test_assign_and_priority_record_activity(): void
    {
        $user = User::factory()->create();
        $issue = Issue::factory()->create(['app_id' => 'det_app', 'priority' => 'low']);

        $this->actingAs($user)->postJson("/api/apps/det_app/issues/{$issue->id}/assign", ['assigned_to' => 'alice'])
            ->assertOk()
            ->assertJsonPath('assigned_to', 'alice')
            ->assertJsonStructure(['id', 'uuid']);

        $this->actingAs($user)->postJson("/api/apps/det_app/issues/{$issue->id}/priority", ['priority' => 'high'])
            ->assertOk()->assertJsonPath('priority', 'high');

        $this->assertDatabaseHas('nightowl_issue_activity', ['issue_id' => $issue->id, 'action' => 'assigned', 'new_value' => 'alice'], 'nightowl');
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

    public function test_transition_is_a_noop_when_status_is_unchanged(): void
    {
        $user = User::factory()->create();
        $issue = Issue::factory()->create(['app_id' => 'det_app', 'status' => 'resolved']);

        $this->actingAs($user)
            ->postJson("/api/apps/det_app/issues/{$issue->id}/resolve")
            ->assertOk()
            ->assertJsonPath('status', 'resolved');

        $this->assertDatabaseMissing('nightowl_issue_activity', [
            'issue_id' => $issue->id, 'action' => 'status_changed',
        ], 'nightowl');
    }

    public function test_can_comment_on_an_issue_via_app_scoped_route(): void
    {
        $user = User::factory()->create();
        $issue = Issue::factory()->create(['app_id' => 'det_app']);

        $this->actingAs($user)
            ->postJson("/api/apps/det_app/issues/{$issue->id}/comments", ['body' => 'Investigating.'])
            ->assertCreated()
            ->assertJsonPath('body', 'Investigating.')
            ->assertJsonPath('actor_type', 'user')
            ->assertJsonStructure(['id', 'uuid']);

        $this->actingAs($user)
            ->getJson("/api/apps/det_app/issues/{$issue->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_issue_actions_404_when_hit_through_the_wrong_apps_url(): void
    {
        $user = User::factory()->create();
        $this->seedApp('other_app');
        $issue = Issue::factory()->create(['app_id' => 'det_app']);

        $this->actingAs($user)->getJson("/api/apps/other_app/issues/{$issue->id}")->assertNotFound();

        $this->actingAs($user)->postJson("/api/apps/other_app/issues/{$issue->id}/assign", ['assigned_to' => 'alice'])
            ->assertNotFound();

        $this->actingAs($user)->postJson("/api/apps/other_app/issues/{$issue->id}/priority", ['priority' => 'high'])
            ->assertNotFound();

        $this->actingAs($user)->postJson("/api/apps/other_app/issues/{$issue->id}/resolve")->assertNotFound();
        $this->actingAs($user)->postJson("/api/apps/other_app/issues/{$issue->id}/ignore")->assertNotFound();
        $this->actingAs($user)->postJson("/api/apps/other_app/issues/{$issue->id}/reopen")->assertNotFound();

        $this->actingAs($user)->getJson("/api/apps/other_app/issues/{$issue->id}/comments")->assertNotFound();

        $this->actingAs($user)->postJson("/api/apps/other_app/issues/{$issue->id}/comments", ['body' => 'Nope.'])
            ->assertNotFound();

        // None of the wrong-app attempts should have mutated the issue.
        $this->assertDatabaseHas('nightowl_issues', ['id' => $issue->id, 'status' => 'open'], 'nightowl');
        $this->assertDatabaseMissing('nightowl_issue_activity', ['issue_id' => $issue->id], 'nightowl');
        $this->assertDatabaseMissing('nightowl_issue_comments', ['issue_id' => $issue->id], 'nightowl');
    }
}
