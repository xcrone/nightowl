<?php

namespace Tests\Feature\Exceptions;

use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Models\User;
use App\Support\AggregateKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/apps/{app}/exception-groups/{key} — the exception-detail drill-down
 * (App\Actions\Exceptions\ShowExceptionGroup), docs/pages/exception-detail.md.
 */
class ShowExceptionGroupTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['pgsql', 'nightowl'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['nightowl_exceptions', 'nightowl_issues'] as $t) {
            DB::connection('nightowl')->table($t)->delete();
        }

        $this->seedApp('exc_app');
    }

    private function key(string $raw): string
    {
        return AggregateKey::encode($raw);
    }

    public function test_returns_occurrences_panel_stack_and_info(): void
    {
        $user = User::factory()->create();

        ExceptionRecord::factory()->count(2)->create([
            'app_id' => 'exc_app', 'class' => 'App\\LogicException',
            'message' => 'Undefined array key "total"', 'handled' => false,
            'server' => 'web-1', 'user_id' => 'u1',
            'trace' => "#0 /app/Services/Foo.php(12): bar()\n#1 /app/Jobs/Baz.php(44): qux()",
        ]);
        ExceptionRecord::factory()->create([
            'app_id' => 'exc_app', 'class' => 'App\\LogicException',
            'handled' => true, 'server' => 'web-2', 'user_id' => 'u2',
            'trace' => '#0 /app/Services/Foo.php(12): bar()',
        ]);
        // Different class must not leak.
        ExceptionRecord::factory()->create(['app_id' => 'exc_app', 'class' => 'App\\OtherException']);

        $res = $this->actingAs($user)->getJson('/api/apps/exc_app/exception-groups/'.$this->key('App\\LogicException'));

        $res->assertOk();
        $this->assertSame('App\\LogicException', $res->json('class'));

        $this->assertSame(3, $res->json('panels.occurrences.total'));
        $this->assertSame(1, $res->json('panels.occurrences.handled'));
        $this->assertSame(2, $res->json('panels.occurrences.unhandled'));

        $this->assertNotEmpty($res->json('stack_frames'));
        $this->assertSame(2, $res->json('info.impacted_users'));
        $this->assertEqualsCanonicalizing(['web-1', 'web-2'], $res->json('info.servers'));
        // Windowed counts (all three rows are created "now").
        $this->assertSame(3, $res->json('info.occurrences_24h'));
        $this->assertSame(3, $res->json('info.occurrences_7d'));
        $this->assertSame(3, $res->json('occurrences.total'));
    }

    public function test_per_page_zero_is_floored_and_does_not_500(): void
    {
        $user = User::factory()->create();

        ExceptionRecord::factory()->create(['app_id' => 'exc_app', 'class' => 'App\\LogicException']);

        $this->actingAs($user)
            ->getJson('/api/apps/exc_app/exception-groups/'.$this->key('App\\LogicException').'?per_page=0')
            ->assertOk();
    }

    public function test_links_to_deduplicated_issue_via_group_hash(): void
    {
        $user = User::factory()->create();

        $issue = Issue::factory()->create([
            'app_id' => 'exc_app', 'exception_class' => 'App\\PaymentException',
            'group_hash' => 'gh-pay',
        ]);
        ExceptionRecord::factory()->create([
            'app_id' => 'exc_app', 'class' => 'App\\PaymentException', 'group_hash' => 'gh-pay',
        ]);

        $res = $this->actingAs($user)->getJson('/api/apps/exc_app/exception-groups/'.$this->key('App\\PaymentException'));

        $res->assertOk();
        $this->assertSame($issue->id, $res->json('issue.id'));
        $this->assertSame($issue->uuid, $res->json('issue.uuid'));
    }

    public function test_null_issue_when_no_matching_issue(): void
    {
        $user = User::factory()->create();

        ExceptionRecord::factory()->create(['app_id' => 'exc_app', 'class' => 'App\\OrphanException', 'group_hash' => 'no-issue']);

        $res = $this->actingAs($user)->getJson('/api/apps/exc_app/exception-groups/'.$this->key('App\\OrphanException'));

        $res->assertOk();
        $this->assertNull($res->json('issue'));
    }

    public function test_unknown_exception_class_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/apps/exc_app/exception-groups/'.$this->key('App\\Nope'))->assertNotFound();
    }

    public function test_is_scoped_to_the_app(): void
    {
        $user = User::factory()->create();
        $this->seedApp('other_app');

        ExceptionRecord::factory()->create(['app_id' => 'exc_app', 'class' => 'App\\Shared']);
        ExceptionRecord::factory()->create(['app_id' => 'other_app', 'class' => 'App\\Shared']);

        $res = $this->actingAs($user)->getJson('/api/apps/exc_app/exception-groups/'.$this->key('App\\Shared'));

        $res->assertOk();
        $this->assertSame(1, $res->json('panels.occurrences.total'));
    }
}
