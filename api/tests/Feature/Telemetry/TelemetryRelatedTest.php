<?php

namespace Tests\Feature\Telemetry;

use App\Models\Telemetry\CacheEvent;
use App\Models\Telemetry\Issue;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\LogRecord;
use App\Models\Telemetry\QueryRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelemetryRelatedTest extends TestCase
{
    // See TelemetryApiTest for why RefreshDatabase + connectionsToTransact
    // (both connections) is needed instead of DatabaseTransactions here.
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['nightowl_requests', 'nightowl_jobs', 'nightowl_queries', 'nightowl_logs', 'nightowl_issues', 'nightowl_cache_events'] as $table) {
            DB::connection('nightowl')->table($table)->delete();
        }

        // App-scoped routes: seed the app the telemetry factories stamp.
        $this->seedApp('test_app');
    }

    public function test_related_links_a_query_back_to_its_request(): void
    {
        $user = User::factory()->create();
        $request = RequestRecord::factory()->create(['trace_id' => 'trace-req-1']);
        $query = QueryRecord::factory()->create([
            'execution_source' => 'request',
            'execution_id' => 'trace-req-1',
        ]);

        $response = $this->actingAs($user)->getJson("/api/apps/test_app/queries/{$query->id}/related");

        $response->assertOk()
            ->assertJsonPath('origin.resource', 'requests')
            ->assertJsonPath('origin.record.id', $request->id);
    }

    public function test_related_counts_children_of_a_request(): void
    {
        $user = User::factory()->create();
        $request = RequestRecord::factory()->create(['trace_id' => 'trace-req-2']);
        QueryRecord::factory()->count(3)->create(['execution_source' => 'request', 'execution_id' => 'trace-req-2']);
        LogRecord::factory()->create(['execution_source' => 'request', 'execution_id' => 'trace-req-2']);

        // Noise: a query belonging to a different request must not be counted.
        QueryRecord::factory()->create(['execution_source' => 'request', 'execution_id' => 'trace-req-other']);

        $response = $this->actingAs($user)->getJson("/api/apps/test_app/requests/{$request->id}/related");

        $response->assertOk()
            ->assertJsonPath('children_filter.execution_source', 'request')
            ->assertJsonPath('children_filter.execution_id', 'trace-req-2')
            ->assertJsonPath('children.queries', 3)
            ->assertJsonPath('children.logs', 1);
    }

    public function test_related_resolves_a_processed_job_attempt_via_attempt_id_not_trace_id(): void
    {
        $user = User::factory()->create();

        // A queue worker's trace_id can outlive many job attempts, so the
        // per-attempt correlation key is attempt_id, not trace_id — assert
        // the join uses attempt_id even though trace_id also differs here.
        $job = JobRecord::factory()->create([
            'trace_id' => 'worker-trace-shared',
            'attempt_id' => 'attempt-abc',
            'status' => 'processed',
        ]);
        $query = QueryRecord::factory()->create([
            'trace_id' => 'unrelated-query-trace',
            'execution_source' => 'job',
            'execution_id' => 'attempt-abc',
        ]);

        $response = $this->actingAs($user)->getJson("/api/apps/test_app/jobs/{$job->id}/related");

        $response->assertOk()->assertJsonPath('children.queries', 1);

        $queryRelated = $this->actingAs($user)->getJson("/api/apps/test_app/queries/{$query->id}/related");
        $queryRelated->assertOk()->assertJsonPath('origin.resource', 'jobs');
        $queryRelated->assertJsonPath('origin.record.id', $job->id);
    }

    public function test_related_falls_back_to_shared_trace_id_for_a_processed_job(): void
    {
        $user = User::factory()->create();

        // A processed job attempt has no execution_source/execution_id of
        // its own (it's an origin, not a child) — but Nightwatch propagates
        // the dispatching request's trace_id through the queue, so it can
        // still be traced back that way.
        $request = RequestRecord::factory()->create(['trace_id' => 'propagated-trace']);
        $job = JobRecord::factory()->create([
            'trace_id' => 'propagated-trace',
            'attempt_id' => 'attempt-xyz',
            'execution_source' => null,
            'execution_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson("/api/apps/test_app/jobs/{$job->id}/related");

        $response->assertOk()
            ->assertJsonPath('origin.resource', 'requests')
            ->assertJsonPath('origin.record.id', $request->id);
    }

    public function test_index_filters_by_execution_source_and_execution_id(): void
    {
        $user = User::factory()->create();
        $match = QueryRecord::factory()->count(2)->create(['execution_source' => 'job', 'execution_id' => 'attempt-1']);
        QueryRecord::factory()->create(['execution_source' => 'job', 'execution_id' => 'attempt-2']);

        $response = $this->actingAs($user)->getJson('/api/apps/test_app/queries?execution_source=job&execution_id=attempt-1');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        sort($ids);
        $this->assertSame($match->pluck('id')->sort()->values()->all(), $ids);
    }

    public function test_related_shows_sibling_activity_when_viewing_a_query(): void
    {
        $user = User::factory()->create();
        RequestRecord::factory()->create(['trace_id' => 'trace-req-3']);
        $query = QueryRecord::factory()->create(['execution_source' => 'request', 'execution_id' => 'trace-req-3']);
        CacheEvent::factory()->count(2)->create(['execution_source' => 'request', 'execution_id' => 'trace-req-3']);
        LogRecord::factory()->create(['execution_source' => 'request', 'execution_id' => 'trace-req-3']);

        $response = $this->actingAs($user)->getJson("/api/apps/test_app/queries/{$query->id}/related");

        $response->assertOk()
            ->assertJsonPath('children_filter.execution_source', 'request')
            ->assertJsonPath('children_filter.execution_id', 'trace-req-3')
            ->assertJsonPath('children.cache-events', 2)
            ->assertJsonPath('children.logs', 1);
        $this->assertArrayNotHasKey('queries', $response->json('children'));
    }

    public function test_related_excludes_itself_when_counting_same_resource_type_siblings(): void
    {
        $user = User::factory()->create();
        $queries = QueryRecord::factory()->count(3)->create([
            'execution_source' => 'request',
            'execution_id' => 'trace-req-4',
        ]);
        $viewed = $queries->first();

        $response = $this->actingAs($user)->getJson("/api/apps/test_app/queries/{$viewed->id}/related");

        $response->assertOk()->assertJsonPath('children.queries', 2);
    }

    public function test_related_shows_siblings_for_a_job_dispatch_event_row(): void
    {
        $user = User::factory()->create();
        RequestRecord::factory()->create(['trace_id' => 'trace-req-5']);

        // The row written when a job is *dispatched* is a child of whatever
        // queued it (execution_source/execution_id set, no attempt_id) —
        // distinct from a *processed* attempt row, which is an origin.
        $dispatchEvent = JobRecord::factory()->create([
            'status' => 'queued',
            'attempt_id' => null,
            'execution_source' => 'request',
            'execution_id' => 'trace-req-5',
        ]);
        JobRecord::factory()->count(2)->create([
            'status' => 'queued',
            'attempt_id' => null,
            'execution_source' => 'request',
            'execution_id' => 'trace-req-5',
        ]);
        QueryRecord::factory()->create(['execution_source' => 'request', 'execution_id' => 'trace-req-5']);

        $response = $this->actingAs($user)->getJson("/api/apps/test_app/jobs/{$dispatchEvent->id}/related");

        $response->assertOk()
            ->assertJsonPath('origin.resource', 'requests')
            ->assertJsonPath('children.jobs', 2)
            ->assertJsonPath('children.queries', 1);
    }

    public function test_related_is_empty_for_a_non_traceable_resource(): void
    {
        $user = User::factory()->create();
        $issue = Issue::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/apps/test_app/issues/{$issue->id}/related");

        $response->assertOk()
            ->assertJsonPath('origin', null)
            ->assertJsonPath('children_filter', null)
            ->assertJsonPath('children', []);
    }
}
