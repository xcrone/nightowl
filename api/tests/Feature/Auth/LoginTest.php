<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/logout')
            ->assertNoContent();

        $this->assertGuest();
    }

    public function test_unauthenticated_request_to_user_endpoint_is_rejected(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_unauthenticated_request_without_json_accept_header_still_gets_401(): void
    {
        // Regression: this app has no server-rendered 'login' route, so
        // Laravel's default Authenticate middleware tried to build a
        // redirect to one for any request that didn't explicitly send
        // Accept: application/json — crashing with a 500 instead of a 401.
        // See AppServiceProvider::boot()'s Authenticate::redirectUsing().
        $this->get('/api/user')->assertUnauthorized();
    }

    public function test_login_route_sends_cors_headers_for_the_frontend_origin(): void
    {
        // Regression: config/cors.php's `paths` only covered api/* and
        // sanctum/csrf-cookie — login/logout live in routes/web.php and were
        // missing, so the browser blocked the SPA's login POST as a CORS
        // violation even though the request itself would have succeeded.
        // curl doesn't enforce CORS, so this was invisible without an actual
        // browser (see AppServiceProvider comment / config/cors.php).
        $response = $this->withHeaders(['Origin' => 'http://localhost:5173'])
            ->postJson('/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);

        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_authenticated_request_to_user_endpoint_returns_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }
}
