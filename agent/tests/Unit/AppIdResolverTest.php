<?php

namespace NightOwl\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use NightOwl\Support\AppIdResolver;
use PHPUnit\Framework\TestCase;

/**
 * Only exercises the short-circuit branches (no DB configured/available) —
 * the DB-backed lookup itself is covered by tests/Integration/AppIdResolverTest.
 */
class AppIdResolverTest extends TestCase
{
    protected function setUp(): void
    {
        $container = new Container;
        Container::setInstance($container);
        $container->instance('config', new Repository);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function testHashTokenIsStableSha256Hex(): void
    {
        $hash = AppIdResolver::hashToken('nwt_abc123');

        $this->assertSame(hash('sha256', 'nwt_abc123'), $hash);
        $this->assertSame(64, strlen($hash));
        $this->assertSame($hash, AppIdResolver::hashToken('nwt_abc123'));
    }

    public function testHashTokenDiffersForDifferentTokens(): void
    {
        $this->assertNotSame(
            AppIdResolver::hashToken('nwt_tokenA'),
            AppIdResolver::hashToken('nwt_tokenB'),
        );
    }

    public function testResolveSkipsLookupWhenAppIdAlreadyConfigured(): void
    {
        // No 'db' binding exists in this container — if resolve() attempted a
        // lookup here it would throw, so a clean pass proves the short-circuit.
        config(['nightowl.agent.app_id' => 'existing-app-id']);
        config(['nightowl.agent.token' => 'some-token']);

        AppIdResolver::resolve();

        $this->assertSame('existing-app-id', config('nightowl.agent.app_id'));
    }

    public function testResolveIsNoOpWhenNoTokenConfigured(): void
    {
        config(['nightowl.agent.app_id' => null]);
        config(['nightowl.agent.token' => '']);

        AppIdResolver::resolve();

        $this->assertNull(config('nightowl.agent.app_id'));
    }
}
