<?php

namespace Database\Seeders;

use App\Models\App;
use App\Models\Telemetry\CacheEvent;
use App\Models\Telemetry\CommandRecord;
use App\Models\Telemetry\ExceptionRecord;
use App\Models\Telemetry\Issue;
use App\Models\Telemetry\JobRecord;
use App\Models\Telemetry\LogRecord;
use App\Models\Telemetry\MailRecord;
use App\Models\Telemetry\NightowlUser;
use App\Models\Telemetry\NotificationRecord;
use App\Models\Telemetry\OutgoingRequest;
use App\Models\Telemetry\QueryRecord;
use App\Models\Telemetry\RequestRecord;
use App\Models\Telemetry\ScheduledTask;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dev/demo telemetry generator: fills the shared nightowl DB with realistic,
 * multi-app data so every dashboard page renders. NOT wired into the default
 * DatabaseSeeder (it writes hundreds of rows to the shared Postgres) — run
 * explicitly: `php artisan db:seed --class=Database\\Seeders\\TelemetrySeeder`.
 *
 * Idempotent per app: clears that app's rows first, then regenerates.
 */
class TelemetrySeeder extends Seeder
{
    private const ROUTES = [
        ['GET', '/api/products/{id}'], ['GET', '/api/orders'], ['POST', '/api/checkout'],
        ['POST', '/api/auth/login'], ['GET', '/api/search'], ['PUT', '/api/settings'],
        ['GET', '/api/invoices/{id}/pdf'], ['DELETE', '/api/subscriptions/{id}'],
        ['GET', '/dashboard'], ['POST', '/api/webhooks/stripe'],
    ];

    private const EXCEPTIONS = [
        'GuzzleHttp\\Exception\\ConnectException', 'App\\Exceptions\\PaymentFailedException',
        'Illuminate\\Database\\QueryException', 'TypeError', 'DivisionByZeroError',
        'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
    ];

    private const TELEMETRY_TABLES = [
        'nightowl_requests', 'nightowl_exceptions', 'nightowl_issues', 'nightowl_jobs',
        'nightowl_queries', 'nightowl_logs', 'nightowl_cache_events', 'nightowl_mail',
        'nightowl_notifications', 'nightowl_commands', 'nightowl_scheduled_tasks',
        'nightowl_outgoing_requests', 'nightowl_users',
    ];

    public function run(): void
    {
        foreach (App::all() as $app) {
            $this->command?->info("Seeding telemetry for {$app->name} ({$app->app_id})…");
            $this->clear($app->app_id);
            $this->seedApp($app->app_id);
        }
    }

    private function clear(string $appId): void
    {
        foreach (self::TELEMETRY_TABLES as $table) {
            \DB::connection('nightowl')->table($table)->where('app_id', $appId)->delete();
        }
    }

    private function seedApp(string $appId): void
    {
        // nightowl_users PK is user_id alone (not composite with app_id), so
        // ids must be globally unique — prefix with a short app tag.
        $tag = substr($appId, 0, 6);
        $userIds = collect(range(1, 25))->map(fn ($i) => "{$tag}_user_{$i}")->all();
        foreach ($userIds as $i => $uid) {
            NightowlUser::factory()->create([
                'app_id' => $appId, 'user_id' => $uid,
                'name' => fake()->name(), 'email' => "{$tag}_user{$i}@example.com",
            ]);
        }

        $at = fn () => Carbon::now()->subMinutes(fake()->numberBetween(0, 1440));
        $user = fn () => fake()->randomElement($userIds);

        foreach (range(1, 80) as $_) {
            [$method, $path] = fake()->randomElement(self::ROUTES);
            $status = fake()->randomElement([200, 200, 200, 201, 302, 404, 500]);
            RequestRecord::factory()->create([
                'app_id' => $appId, 'method' => $method, 'route_path' => $path,
                'url' => $path, 'status_code' => $status, 'user_id' => $user(),
                'duration' => fake()->numberBetween(5000, 1500000),
                'exceptions' => $status >= 500 ? 1 : 0, 'created_at' => $at(),
            ]);
        }

        // Issues first, so their (class, group_hash) can seed matching
        // exception occurrences — the dashboard's issue detail correlates
        // occurrences by group_hash.
        $issueKeys = [];
        foreach (self::EXCEPTIONS as $class) {
            $hash = Str::random(16);
            $issueKeys[] = [$class, $hash];
            Issue::factory()->create([
                'app_id' => $appId, 'exception_class' => $class,
                'exception_message' => 'cURL error 28: Operation timed out',
                'group_hash' => $hash, 'status' => fake()->randomElement(['open', 'open', 'resolved', 'ignored']),
                'occurrences_count' => fake()->numberBetween(1, 200),
                'users_count' => fake()->numberBetween(1, 30),
                'first_seen_at' => Carbon::now()->subDays(fake()->numberBetween(1, 14)),
                'last_seen_at' => $at(),
            ]);
        }

        foreach (range(1, 30) as $_) {
            [$class, $hash] = fake()->randomElement($issueKeys);
            ExceptionRecord::factory()->create([
                'app_id' => $appId, 'class' => $class, 'group_hash' => $hash,
                'message' => 'cURL error 28: Operation timed out',
                'handled' => fake()->boolean(30), 'user_id' => $user(),
                'execution_source' => fake()->randomElement(['job', 'request']),
                'trace' => "#0 /app/Services/ShippingClient.php(121): connect()\n#1 /app/Jobs/ProcessPayment.php(44): ship()",
                'php_version' => '8.4.15', 'laravel_version' => '12.43.1',
                'created_at' => $at(),
            ]);
        }

        JobRecord::factory()->count(50)->create(['app_id' => $appId, 'created_at' => $at(), 'user_id' => $user()]);
        QueryRecord::factory()->count(60)->create(['app_id' => $appId, 'created_at' => $at()]);
        LogRecord::factory()->count(25)->create(['app_id' => $appId, 'created_at' => $at()->toIso8601String()]);
        CacheEvent::factory()->count(50)->create(['app_id' => $appId, 'created_at' => $at()]);
        MailRecord::factory()->count(20)->create(['app_id' => $appId, 'created_at' => $at(), 'user_id' => $user()]);
        NotificationRecord::factory()->count(20)->create(['app_id' => $appId, 'created_at' => $at(), 'user_id' => $user()]);
        CommandRecord::factory()->count(12)->create(['app_id' => $appId, 'created_at' => $at()]);
        ScheduledTask::factory()->count(12)->create(['app_id' => $appId, 'created_at' => $at()]);
        OutgoingRequest::factory()->count(24)->create(['app_id' => $appId, 'created_at' => $at(), 'user_id' => $user()]);
    }
}
