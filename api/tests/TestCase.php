<?php

namespace Tests;

use App\Models\App;
use App\Models\Org;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed a minimal Org → Team → App chain and return the App, so per-app
     * route binding (`/api/apps/{app}/…`) resolves and telemetry records can
     * be stamped with `app_id => $app->app_id`. Used by every telemetry/
     * feature test now that everything is app-scoped. When `$user` is
     * given, it is attached to the created Org so it counts as a member.
     */
    protected function seedApp(?string $appId = null, ?User $user = null): App
    {
        $org = Org::query()->create([
            'name' => 'Test Org',
            'account_email' => 'test@example.com',
        ]);

        if ($user) {
            $org->users()->attach($user->id);
        }

        $team = Team::query()->create([
            'org_id' => $org->id,
            'name' => 'Test Team',
        ]);

        return App::query()->create([
            'app_id' => $appId ?? 'test_'.Str::random(20),
            'team_id' => $team->id,
            'name' => 'Test App',
            'environments' => ['production' => '#22c55e'],
            'agent_token' => 'nwt_test',
        ]);
    }
}
