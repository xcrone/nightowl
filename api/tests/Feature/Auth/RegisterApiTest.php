<?php

namespace Tests\Feature\Auth;

use App\Models\Org;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_founds_a_new_org(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'org_name' => 'Analytical Engines Inc',
        ]);

        $response->assertCreated()->assertJsonPath('user.email', 'ada@example.com');

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);

        $org = Org::where('name', 'Analytical Engines Inc')->firstOrFail();
        $this->assertSame($user->email, $org->account_email);
        $this->assertTrue($user->orgs()->where('orgs.id', $org->id)->exists());
        $this->assertSame($user->id, $org->owner_id);
        $this->assertTrue($org->is_personal);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/register', [
            'name' => 'Someone Else',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'org_name' => 'Someone Else Org',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_registration_fails_with_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'not-the-same',
            'org_name' => 'Analytical Engines Inc',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_registration_converts_a_racing_duplicate_insert_into_a_422_not_a_500(): void
    {
        // Simulates a genuine race between two concurrent registrations for
        // the same email. A real race involves two independent DB
        // connections/transactions, so a `User::creating` listener inserts
        // the colliding row through a SECOND, separately-opened connection
        // (autocommit, no transaction of its own) the instant this
        // request's own User::create() fires — i.e. after this request's
        // own `unique:users,email` validation already passed. That commits
        // immediately and independently of this test's own
        // RefreshDatabase-wrapped connection/transaction, so this request's
        // own insert then hits the DB's unique constraint exactly like a
        // genuine concurrent loser would (rather than both inserts living
        // in one transaction and rolling back together, which would prove
        // nothing).
        config(['database.connections.pgsql_race_test' => config('database.connections.pgsql')]);
        DB::purge('pgsql_race_test');

        User::creating(function (User $user) {
            if ($user->email === 'racer@example.com') {
                DB::connection('pgsql_race_test')->table('users')->insert([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'First Writer',
                    'email' => 'racer@example.com',
                    'password' => bcrypt('whatever-password'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        try {
            $response = $this->postJson('/register', [
                'name' => 'Racer',
                'email' => 'racer@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'org_name' => 'Race Org',
            ]);

            $response->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->assertGuest();
            $this->assertSame(
                1,
                DB::connection('pgsql_race_test')->table('users')->where('email', 'racer@example.com')->count(),
                'the racing insert should be the only row for this email — the losing request must not also persist a user or an org'
            );
            $this->assertDatabaseMissing('orgs', ['name' => 'Race Org']);
        } finally {
            // The colliding row was committed on its own connection/session,
            // outside this test's RefreshDatabase-wrapped transaction, so it
            // survives that transaction's rollback at tearDown — clean it up
            // explicitly (through the same independent connection) so it
            // doesn't leak into the next test run.
            DB::connection('pgsql_race_test')->table('users')->where('email', 'racer@example.com')->delete();
        }
    }

    public function test_registration_fails_without_org_name(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('org_name');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }
}
