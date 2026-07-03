<?php

namespace Tests\Feature\Telemetry;

use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Models\Telemetry\LogRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelemetryApiTest extends TestCase
{
    // RefreshDatabase (not DatabaseTransactions) because the sqlite side
    // needs migrate:fresh to create the users table; it only migrates the
    // default connection, so the real nightowl Postgres schema is untouched
    // — connectionsToTransact below still wraps it in a rolled-back
    // transaction so these tests don't leave data behind.
    use RefreshDatabase;

    /**
     * Wrap both the app's own default connection and the nightowl Postgres
     * connection in a transaction, since telemetry data lives in a real,
     * shared Postgres database rather than the sqlite testing connection.
     *
     * Must be the literal connection name ('sqlite', per phpunit.xml's
     * DB_CONNECTION), not null — RefreshDatabase caches the in-memory PDO
     * per connection name, and other test classes that don't override this
     * property transact against config('database.default') (also
     * 'sqlite'). Using null here would cache under a different key and the
     * users table would appear to vanish for whichever class runs second.
     */
    protected $connectionsToTransact = ['sqlite', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        // This 'nightowl' database is shared with real dev/simulator traffic
        // outside of this test's transaction. Clear the tables these tests
        // assert exact contents of, so pre-existing rows don't leak into
        // filter assertions. Safe: it's inside the per-test transaction
        // RefreshDatabase started above, rolled back on tearDown.
        foreach (['nightowl_requests', 'nightowl_exceptions', 'nightowl_issues', 'nightowl_logs'] as $table) {
            DB::connection('nightowl')->table($table)->delete();
        }
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/requests')->assertUnauthorized();
    }

    public function test_unknown_resource_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/not-a-real-resource')
            ->assertNotFound();
    }

    public function test_lists_and_paginates_requests(): void
    {
        $user = User::factory()->create();
        RequestRecord::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/requests');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_shows_a_single_request(): void
    {
        $user = User::factory()->create();
        $record = RequestRecord::factory()->create(['method' => 'POST']);

        $this->actingAs($user)
            ->getJson("/api/requests/{$record->id}")
            ->assertOk()
            ->assertJsonPath('method', 'POST');
    }

    public function test_requests_failed_filter_matches_5xx_only(): void
    {
        $user = User::factory()->create();
        RequestRecord::factory()->create(['status_code' => 200]);
        $failing = RequestRecord::factory()->create(['status_code' => 500]);

        $response = $this->actingAs($user)->getJson('/api/requests?failed=1');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$failing->id], $ids);
    }

    public function test_requests_slow_filter_uses_1000ms_threshold(): void
    {
        $user = User::factory()->create();
        RequestRecord::factory()->create(['duration' => 500 * 1000]);
        $slow = RequestRecord::factory()->create(['duration' => 2000 * 1000]);

        $response = $this->actingAs($user)->getJson('/api/requests?slow=1');

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$slow->id], $ids);
    }

    public function test_requests_has_exceptions_filter(): void
    {
        $user = User::factory()->create();
        RequestRecord::factory()->create(['exceptions' => 0]);
        $withException = RequestRecord::factory()->create(['exceptions' => 2]);

        $response = $this->actingAs($user)->getJson('/api/requests?has_exceptions=1');

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$withException->id], $ids);
    }

    public function test_exceptions_unhandled_only_filter(): void
    {
        $user = User::factory()->create();
        ExceptionRecord::factory()->create(['handled' => true]);
        $unhandled = ExceptionRecord::factory()->create(['handled' => false]);

        $response = $this->actingAs($user)->getJson('/api/exceptions?unhandled_only=1');

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$unhandled->id], $ids);
    }

    public function test_issues_status_filter(): void
    {
        $user = User::factory()->create();
        Issue::factory()->create(['status' => 'resolved']);
        $open = Issue::factory()->create(['status' => 'open']);

        $response = $this->actingAs($user)->getJson('/api/issues?status=open');

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$open->id], $ids);
    }

    public function test_logs_level_filter(): void
    {
        $user = User::factory()->create();
        LogRecord::factory()->create(['level' => 'info']);
        $error = LogRecord::factory()->create(['level' => 'error']);

        $response = $this->actingAs($user)->getJson('/api/logs?level=error');

        $ids = array_column($response->json('data'), 'id');
        $this->assertSame([$error->id], $ids);
    }

    public function test_invalid_sort_column_falls_back_to_default(): void
    {
        $user = User::factory()->create();
        RequestRecord::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/requests?sort=some_unsortable_column')
            ->assertOk();
    }
}
