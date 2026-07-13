<?php

namespace NightOwl\Tests\Integration;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use NightOwl\Support\AppIdResolver;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for AppIdResolver — requires a live PostgreSQL database.
 * See RecordWriterTest for the NIGHTOWL_TEST_DB_* env vars / Docker setup.
 */
class AppIdResolverTest extends TestCase
{
    private static ?PDO $pdo = null;

    private static string $host;

    private static int $port;

    private static string $database;

    private static string $username;

    private static string $password;

    public static function setUpBeforeClass(): void
    {
        self::$host = getenv('NIGHTOWL_TEST_DB_HOST') ?: '127.0.0.1';
        self::$port = (int) (getenv('NIGHTOWL_TEST_DB_PORT') ?: 5432);
        self::$database = getenv('NIGHTOWL_TEST_DB_DATABASE') ?: 'nightowl_test';
        self::$username = getenv('NIGHTOWL_TEST_DB_USERNAME') ?: 'nightowl_test';
        self::$password = getenv('NIGHTOWL_TEST_DB_PASSWORD') ?: 'test123';

        try {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', self::$host, self::$port, self::$database);
            self::$pdo = new PDO($dsn, self::$username, self::$password);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            self::$pdo = null;
        }

        if (self::$pdo) {
            MigrationRunner::migrate(self::$host, self::$port, self::$database, self::$username, self::$password);
        }
    }

    protected function setUp(): void
    {
        if (self::$pdo === null) {
            $this->markTestSkipped('PostgreSQL not available. Set NIGHTOWL_TEST_DB_* env vars.');
        }

        // MigrationRunner boots the shared Container/Capsule ('db' binding);
        // add a config Repository alongside it so the config()/env() helpers
        // AppIdResolver relies on work the same way they do in production.
        Container::getInstance()->instance('config', new Repository);

        self::$pdo->exec('TRUNCATE nightowl_apps');
    }

    public function testResolvesAppIdFromMatchingToken(): void
    {
        $token = 'nwt_'.bin2hex(random_bytes(20));

        DB::connection('nightowl')->table('nightowl_apps')->insert([
            'app_id' => 'app-under-test',
            'token_hash' => AppIdResolver::hashToken($token),
        ]);

        config(['nightowl.agent.app_id' => null]);
        config(['nightowl.agent.token' => $token]);

        AppIdResolver::resolve();

        $this->assertSame('app-under-test', config('nightowl.agent.app_id'));
    }

    public function testNonMatchingTokenLeavesAppIdNull(): void
    {
        DB::connection('nightowl')->table('nightowl_apps')->insert([
            'app_id' => 'some-other-app',
            'token_hash' => AppIdResolver::hashToken('nwt_'.bin2hex(random_bytes(20))),
        ]);

        config(['nightowl.agent.app_id' => null]);
        config(['nightowl.agent.token' => 'nwt_'.bin2hex(random_bytes(20))]);

        AppIdResolver::resolve();

        $this->assertNull(config('nightowl.agent.app_id'));
    }

    public function testExplicitAppIdIsNeverOverwritten(): void
    {
        $token = 'nwt_'.bin2hex(random_bytes(20));

        // A row exists that WOULD resolve to a different app_id for this
        // token — proving the explicit override wins without even looking.
        DB::connection('nightowl')->table('nightowl_apps')->insert([
            'app_id' => 'would-be-resolved',
            'token_hash' => AppIdResolver::hashToken($token),
        ]);

        config(['nightowl.agent.app_id' => 'explicit-override']);
        config(['nightowl.agent.token' => $token]);

        AppIdResolver::resolve();

        $this->assertSame('explicit-override', config('nightowl.agent.app_id'));
    }
}
