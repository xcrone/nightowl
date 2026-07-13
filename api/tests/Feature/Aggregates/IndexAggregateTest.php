<?php

namespace Tests\Feature\Aggregates;

use App\Models\Telemetry\CacheEvent;
use App\Models\Telemetry\CommandRecord;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\MailRecord;
use App\Models\Telemetry\OutgoingRequest;
use App\Models\Telemetry\QueryRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\Telemetry\ScheduledTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/apps/{app}/aggregate/{resource} (App\Actions\Aggregates\IndexAggregate).
 * Relocated from tests/Feature/Apps/AggregateApiTest.php in Batch 7
 * (AggregateController -> Actions migration) — same assertions/URLs, since
 * the route and JSON shape are unchanged.
 */
class IndexAggregateTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'nightowl_requests', 'nightowl_cache_events', 'nightowl_mail',
            'nightowl_exceptions', 'nightowl_queries', 'nightowl_scheduled_tasks',
            'nightowl_jobs',
        ] as $t) {
            DB::connection('nightowl')->table($t)->delete();
        }

        $this->seedApp('agg_app');
    }

    public function test_requests_aggregate_groups_per_route_with_status_buckets(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->count(3)->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'status_code' => 200]);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'status_code' => 500]);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/login', 'status_code' => 200]);

        $response = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests');

        $response->assertOk();
        $rows = collect($response->json('data'));
        $orders = $rows->firstWhere('route_path', '/api/orders');

        $this->assertSame(4, $orders['total']);
        $this->assertSame(3, $orders['c2xx']);
        $this->assertSame(1, $orders['c5xx']);
        $this->assertArrayHasKey('p95', $orders);
        // default sort is -total, so the busier route comes first.
        $this->assertSame('/api/orders', $rows->first()['route_path']);
    }

    public function test_aggregate_is_scoped_to_the_app(): void
    {
        $user = User::factory()->create();
        $this->seedApp('other_app');

        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/mine']);
        RequestRecord::factory()->create(['app_id' => 'other_app', 'route_path' => '/theirs']);

        $paths = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests')->json('data'))
            ->pluck('route_path')->all();

        $this->assertContains('/mine', $paths);
        $this->assertNotContains('/theirs', $paths);
    }

    public function test_environment_scope_filters_aggregate_rows_and_panels(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->count(2)->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'environment' => 'production']);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'environment' => 'staging']);

        $response = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests?environment=production');
        $response->assertOk();

        $orders = collect($response->json('data'))->firstWhere('route_path', '/api/orders');
        $this->assertSame(2, $orders['total']);
        // Panel totals honor the same environment scope as the grouped rows.
        $this->assertSame(2, $response->json('panels.requests.total'));
    }

    public function test_empty_environment_leaves_aggregate_unfiltered(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'environment' => 'production']);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'environment' => 'staging']);

        $orders = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests?environment=')->json('data'))
            ->firstWhere('route_path', '/api/orders');

        $this->assertSame(2, $orders['total']);
    }

    public function test_users_aggregate_ignores_environment_scope(): void
    {
        $user = User::factory()->create();

        // The bespoke users aggregate has no environment column; the filter
        // must be ignored (not error) and every user remains.
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'user_id' => 'alpha', 'environment' => 'production']);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'user_id' => 'beta', 'environment' => 'staging']);

        $response = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/users?environment=production');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('user_id')->all();
        $this->assertContains('alpha', $ids);
        $this->assertContains('beta', $ids);
    }

    public function test_cache_aggregate_computes_hit_rate(): void
    {
        $user = User::factory()->create();

        CacheEvent::factory()->count(3)->create(['app_id' => 'agg_app', 'key' => 'k', 'store' => 'redis', 'event_type' => 'hit']);
        CacheEvent::factory()->create(['app_id' => 'agg_app', 'key' => 'k', 'store' => 'redis', 'event_type' => 'missed']);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/cache')->json('data'))
            ->firstWhere('key', 'k');

        $this->assertSame(3, $row['hits']);
        $this->assertSame(1, $row['misses']);
        $this->assertEqualsWithDelta(75.0, $row['hit_rate'], 0.001);
    }

    public function test_unknown_aggregate_resource_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/nope')->assertNotFound();
    }

    /**
     * Locks in the Batch 7 opportunistic fix: AggregateController's inline
     * ?q= ILIKE search is replaced by the shared App\Support\SearchTerm
     * (same helper TelemetryController/IndexTelemetryResource use), rather
     * than a second, cruder copy.
     */
    public function test_grouped_aggregate_search_matches_route_path(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders']);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/login']);

        $paths = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests?q=orders')->json('data'))
            ->pluck('route_path')->all();

        $this->assertSame(['/api/orders'], $paths);
    }

    /**
     * Bespoke `users` aggregate search (matched in PHP against user_id +
     * email, not SQL ILIKE) also now goes through SearchTerm::fromRequest()
     * for its trimming/length-cap, rather than its own inline trim().
     */
    public function test_users_aggregate_search_matches_user_id(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->create(['app_id' => 'agg_app', 'user_id' => 'user-alpha']);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'user_id' => 'user-beta']);

        $ids = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/users?q=alpha')->json('data'))
            ->pluck('user_id')->all();

        $this->assertSame(['user-alpha'], $ids);
    }

    /**
     * FINDING #1: `panels` must be nested per stat panel so the frontend's
     * panels(p) builders (web/src/aggregateConfig.js) resolve — requests reads
     * p.requests.{total,c2xx,c4xx,c5xx} and p.duration.{min,max,avg,p95}.
     */
    public function test_requests_panels_are_nested_per_stat_panel(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->count(3)->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'status_code' => 200, 'duration' => 100]);
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'route_path' => '/api/orders', 'status_code' => 500, 'duration' => 200]);

        $panels = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests')->json('panels');

        $this->assertSame(4, $panels['requests']['total']);
        $this->assertSame(3, $panels['requests']['c2xx']);
        $this->assertSame(1, $panels['requests']['c5xx']);
        $this->assertArrayHasKey('duration', $panels);
        $this->assertArrayHasKey('min', $panels['duration']);
        $this->assertArrayHasKey('p95', $panels['duration']);
        // Flat leakage must be gone.
        $this->assertArrayNotHasKey('total', $panels);
        $this->assertArrayNotHasKey('c2xx', $panels);
    }

    /**
     * FINDING #1: cache panels nest into events + failures
     * (docs: { events:{hits,misses,writes,deletes}, failures:{write,delete} }).
     */
    public function test_cache_panels_nest_events_and_failures(): void
    {
        $user = User::factory()->create();

        CacheEvent::factory()->count(2)->create(['app_id' => 'agg_app', 'key' => 'k', 'store' => 'redis', 'event_type' => 'hit']);
        CacheEvent::factory()->create(['app_id' => 'agg_app', 'key' => 'k', 'store' => 'redis', 'event_type' => 'missed']);
        CacheEvent::factory()->create(['app_id' => 'agg_app', 'key' => 'k', 'store' => 'redis', 'event_type' => 'failed']);

        $panels = $this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/cache')->json('panels');

        $this->assertSame(2, $panels['events']['hits']);
        $this->assertSame(1, $panels['events']['misses']);
        $this->assertArrayHasKey('writes', $panels['events']);
        $this->assertArrayHasKey('deletes', $panels['events']);
        // failures sub-object has write/delete keys; delete has no raw source -> 0.
        $this->assertSame(1, $panels['failures']['write']);
        $this->assertSame(0, $panels['failures']['delete']);
    }

    /** FINDING #3: mail rows emit `last_sent` (not `last_created_at`). */
    public function test_mail_aggregate_emits_last_sent(): void
    {
        $user = User::factory()->create();

        MailRecord::factory()->create(['app_id' => 'agg_app', 'mailable' => 'App\\Mail\\Welcome']);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/mail')->json('data'))
            ->firstWhere('mailable', 'App\\Mail\\Welcome');

        $this->assertArrayHasKey('last_sent', $row);
        $this->assertArrayNotHasKey('last_created_at', $row);
    }

    /** FINDING #3: exception rows emit `last_seen` and `source`. */
    public function test_exceptions_aggregate_emits_last_seen_and_source(): void
    {
        $user = User::factory()->create();

        ExceptionRecord::factory()->create([
            'app_id' => 'agg_app', 'class' => 'App\\LogicException',
            'execution_source' => 'request', 'handled' => true,
        ]);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/exceptions')->json('data'))
            ->firstWhere('class', 'App\\LogicException');

        $this->assertSame('request', $row['source']);
        $this->assertArrayHasKey('last_seen', $row);
        $this->assertArrayNotHasKey('execution_source', $row);
        $this->assertArrayNotHasKey('last_created_at', $row);
    }

    /** FINDING #3: query rows emit `rw` (not `connection_type`). */
    public function test_queries_aggregate_emits_rw(): void
    {
        $user = User::factory()->create();

        QueryRecord::factory()->create([
            'app_id' => 'agg_app', 'sql_query' => 'select * from orders',
            'group_hash' => 'gh-orders', 'connection_type' => 'write',
        ]);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/queries')->json('data'))
            ->firstWhere('group_hash', 'gh-orders');

        $this->assertSame('write', $row['rw']);
        $this->assertArrayNotHasKey('connection_type', $row);
    }

    /** FINDING #3: scheduled-task rows emit a humanized `schedule` (not raw cron). */
    public function test_scheduled_tasks_aggregate_emits_humanized_schedule(): void
    {
        $user = User::factory()->create();

        ScheduledTask::factory()->create([
            'app_id' => 'agg_app', 'command' => 'php artisan reports:nightly',
            'expression' => '0 0 * * *', 'status' => 'processed',
        ]);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/scheduled-tasks')->json('data'))
            ->firstWhere('command', 'php artisan reports:nightly');

        // schedule is humanized for display, but the raw cron `expression`
        // (a group_by key) is retained so the detail drill-down / rowLink can
        // disambiguate rows sharing a command but differing by cadence.
        $this->assertSame('Daily', $row['schedule']);
        $this->assertSame('0 0 * * *', $row['expression']);
    }

    /**
     * Bug fix: a group's handled/unhandled badge must reflect the group's
     * *latest* occurrence (matching Exceptions\ShowExceptionGroup's
     * representative pick), not "any occurrence in the group was handled" —
     * a mostly-unhandled group (5 unhandled + 1 handled) whose latest
     * occurrence is unhandled must report `handled: false`, not `true`.
     */
    public function test_exceptions_aggregate_handled_reflects_latest_occurrence_not_any_true(): void
    {
        $user = User::factory()->create();

        ExceptionRecord::factory()->count(5)->create([
            'app_id' => 'agg_app', 'class' => 'App\\LogicException',
            'handled' => false, 'created_at' => now()->subMinutes(10),
        ]);
        // The one handled occurrence is the oldest, not the latest.
        ExceptionRecord::factory()->create([
            'app_id' => 'agg_app', 'class' => 'App\\LogicException',
            'handled' => true, 'created_at' => now()->subMinutes(20),
        ]);
        // The latest occurrence overall is unhandled.
        ExceptionRecord::factory()->create([
            'app_id' => 'agg_app', 'class' => 'App\\LogicException',
            'handled' => false, 'created_at' => now(),
        ]);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/exceptions')->json('data'))
            ->firstWhere('class', 'App\\LogicException');

        $this->assertSame(7, $row['total']);
        $this->assertFalse($row['handled']);
    }

    /** Bug fix: the inverse case — latest occurrence handled must report `handled: true`. */
    public function test_exceptions_aggregate_handled_true_when_latest_occurrence_is_handled(): void
    {
        $user = User::factory()->create();

        ExceptionRecord::factory()->count(5)->create([
            'app_id' => 'agg_app', 'class' => 'App\\LogicException',
            'handled' => false, 'created_at' => now()->subMinutes(10),
        ]);
        ExceptionRecord::factory()->create([
            'app_id' => 'agg_app', 'class' => 'App\\LogicException',
            'handled' => true, 'created_at' => now(),
        ]);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/exceptions')->json('data'))
            ->firstWhere('class', 'App\\LogicException');

        $this->assertTrue($row['handled']);
    }

    /**
     * Bug fix: the read/write badge must reflect the group's latest occurrence
     * connection_type, not "any occurrence in the group was a write" — MAX()
     * on text picks 'write' > 'read' lexicographically regardless of which is
     * more recent or more common.
     */
    public function test_queries_aggregate_rw_reflects_latest_occurrence_not_any_write(): void
    {
        $user = User::factory()->create();

        QueryRecord::factory()->count(5)->create([
            'app_id' => 'agg_app', 'sql_query' => 'select * from orders',
            'group_hash' => 'gh-orders', 'connection_type' => 'read',
            'created_at' => now()->subMinutes(10),
        ]);
        // The one write is not the latest occurrence.
        QueryRecord::factory()->create([
            'app_id' => 'agg_app', 'sql_query' => 'select * from orders',
            'group_hash' => 'gh-orders', 'connection_type' => 'write',
            'created_at' => now()->subMinutes(20),
        ]);
        QueryRecord::factory()->create([
            'app_id' => 'agg_app', 'sql_query' => 'select * from orders',
            'group_hash' => 'gh-orders', 'connection_type' => 'read',
            'created_at' => now(),
        ]);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/queries')->json('data'))
            ->firstWhere('group_hash', 'gh-orders');

        $this->assertSame(7, $row['total']);
        $this->assertSame('read', $row['rw']);
    }

    /**
     * FINDING #8: the bespoke users aggregate honors ?sort= against its
     * sortable whitelist rather than always sorting by -requests.
     */
    public function test_users_aggregate_honors_sort(): void
    {
        $user = User::factory()->create();

        // alpha: 1 request, 2 queued jobs; beta: 2 requests, 0 jobs.
        RequestRecord::factory()->create(['app_id' => 'agg_app', 'user_id' => 'alpha']);
        RequestRecord::factory()->count(2)->create(['app_id' => 'agg_app', 'user_id' => 'beta']);
        JobRecord::factory()->count(2)->create(['app_id' => 'agg_app', 'user_id' => 'alpha']);

        // Default (-requests): beta first.
        $default = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/users')->json('data'))
            ->pluck('user_id')->all();
        $this->assertSame(['beta', 'alpha'], $default);

        // -queued_jobs: alpha first.
        $byJobs = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/users?sort=-queued_jobs')->json('data'))
            ->pluck('user_id')->all();
        $this->assertSame(['alpha', 'beta'], $byJobs);
    }

    /** requests rows now emit `last_triggered` (MAX(created_at) per group). */
    public function test_requests_aggregate_emits_last_triggered(): void
    {
        $user = User::factory()->create();

        RequestRecord::factory()->create([
            'app_id' => 'agg_app', 'route_path' => '/api/orders',
            'created_at' => now()->subMinutes(10),
        ]);
        $latest = RequestRecord::factory()->create([
            'app_id' => 'agg_app', 'route_path' => '/api/orders',
            'created_at' => now(),
        ]);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/requests')->json('data'))
            ->firstWhere('route_path', '/api/orders');

        $this->assertArrayHasKey('last_triggered', $row);
        $this->assertSame(
            $latest->created_at->toIso8601String(),
            Carbon::parse($row['last_triggered'])->toIso8601String()
        );
    }

    /**
     * jobs rows emit both `last_finished` (MAX(created_at)) and `last_duration`
     * — the latter must be the duration of the SAME latest-created_at
     * occurrence, not any occurrence in the group (nightowl_jobs has no
     * separate start timestamp, so the frontend derives "triggered at" as
     * last_finished - last_duration).
     */
    public function test_jobs_aggregate_emits_last_finished_and_matching_last_duration(): void
    {
        $user = User::factory()->create();

        JobRecord::factory()->create([
            'app_id' => 'agg_app', 'job_class' => 'App\\Jobs\\SendInvoice',
            'created_at' => now()->subMinutes(10), 'duration' => 111111,
        ]);
        $latest = JobRecord::factory()->create([
            'app_id' => 'agg_app', 'job_class' => 'App\\Jobs\\SendInvoice',
            'created_at' => now(), 'duration' => 222222,
        ]);

        $row = collect($this->actingAs($user)->getJson('/api/apps/agg_app/aggregate/jobs')->json('data'))
            ->firstWhere('job_class', 'App\\Jobs\\SendInvoice');

        $this->assertArrayHasKey('last_finished', $row);
        $this->assertArrayHasKey('last_duration', $row);
        $this->assertSame(
            $latest->created_at->toIso8601String(),
            Carbon::parse($row['last_finished'])->toIso8601String()
        );
        $this->assertSame($latest->duration, $row['last_duration']);
    }

    /**
     * Lighter coverage: outgoing-requests, commands, scheduled-tasks, queries,
     * and cache aggregate rows all now carry `last_triggered` too.
     */
    public function test_remaining_resources_emit_last_triggered(): void
    {
        $user = User::factory()->create();

        OutgoingRequest::factory()->create(['app_id' => 'agg_app', 'host' => 'https://api.stripe.com']);
        CommandRecord::factory()->create(['app_id' => 'agg_app', 'command' => 'migrate --force']);
        ScheduledTask::factory()->create(['app_id' => 'agg_app', 'command' => 'php artisan inspire']);
        QueryRecord::factory()->create(['app_id' => 'agg_app', 'group_hash' => 'gh-remaining']);
        CacheEvent::factory()->create(['app_id' => 'agg_app', 'key' => 'k', 'store' => 'redis', 'event_type' => 'hit']);

        $cases = [
            'outgoing-requests' => ['host', 'https://api.stripe.com'],
            'commands' => ['command', 'migrate --force'],
            'scheduled-tasks' => ['command', 'php artisan inspire'],
            'queries' => ['group_hash', 'gh-remaining'],
            'cache' => ['key', 'k'],
        ];

        foreach ($cases as $resource => [$col, $value]) {
            $row = collect($this->actingAs($user)->getJson("/api/apps/agg_app/aggregate/{$resource}")->json('data'))
                ->firstWhere($col, $value);

            $this->assertNotNull($row, "no row found for {$resource}");
            $this->assertArrayHasKey('last_triggered', $row, "{$resource} row missing last_triggered");
        }
    }
}
