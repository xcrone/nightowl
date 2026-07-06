<?php

namespace Database\Seeders;

use App\Models\App;
use App\Models\Org;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the "Owlworks Agency" org with its teams and apps (docs/pages/
 * org-dashboard.md). Idempotent. The first app reuses the default app_id the
 * agent migration backfilled onto existing telemetry, so seeded telemetry
 * and the dashboard line up out of the box.
 */
class OrgSeeder extends Seeder
{
    /** Keep in sync with the agent app_id migration's DEFAULT_APP_ID. */
    public const DEFAULT_APP_ID = '3FoNKDbo7D5S9MGhLx9qybejLCE';

    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => 'password']
        );

        $org = Org::query()->firstOrCreate(
            ['name' => 'Owlworks Agency'],
            ['account_email' => $admin->email]
        );

        $org->users()->syncWithoutDetaching([$admin->id]);

        $envs = ['production' => '#22c55e', 'staging' => '#f59e0b'];

        $structure = [
            'Northwind Traders' => [
                ['app_id' => self::DEFAULT_APP_ID, 'name' => 'Northwind Web',
                    'db' => 'ep-northwind-web-8821.us-east-1.aws.neon.tech:5432/northwind_web'],
                ['app_id' => 'Nw2ApiKdbo7D5S9MGhLx9qyAPI', 'name' => 'Northwind API',
                    'db' => 'ep-northwind-api-4410.us-east-1.aws.neon.tech:5432/northwind_api'],
            ],
            'Delta Payments' => [
                ['app_id' => 'Dp3PayKdbo7D5S9MGhLx9qyPAY', 'name' => 'Delta Payments',
                    'db' => 'ep-delta-payments-362a.us-east-1.aws.neon.tech:5432/delta_payments'],
                ['app_id' => 'Dl4LedKdbo7D5S9MGhLx9qyLED', 'name' => 'Delta Ledger',
                    'db' => 'ep-delta-ledger-1907.us-east-1.aws.neon.tech:5432/delta_ledger'],
            ],
        ];

        foreach ($structure as $teamName => $apps) {
            $team = Team::query()->firstOrCreate(
                ['org_id' => $org->id, 'name' => $teamName]
            );

            foreach ($apps as $spec) {
                App::query()->firstOrCreate(
                    ['app_id' => $spec['app_id']],
                    [
                        'team_id' => $team->id,
                        'name' => $spec['name'],
                        'description' => $spec['name'].' — simulated telemetry.',
                        'db_connection' => $spec['db'],
                        'environments' => $envs,
                        'agent_token' => 'nwt_'.Str::random(32),
                    ]
                );
            }
        }
    }
}
