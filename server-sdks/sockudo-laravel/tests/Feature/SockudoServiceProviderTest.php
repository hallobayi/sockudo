<?php

declare(strict_types=1);

namespace Sockudo\Laravel\Tests\Feature;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Broadcasting\BroadcastManager;
use Sockudo\Laravel\SockudoBroadcaster;
use Sockudo\Laravel\SockudoManager;
use Sockudo\Laravel\Tests\TestCase;
use Sockudo\SockudoInterface;

class SockudoServiceProviderTest extends TestCase
{
    public function testItRegistersTheDefaultBroadcastConnection(): void
    {
        $connection = $this->app['config']->get('broadcasting.connections.sockudo');

        self::assertIsArray($connection);
        self::assertSame('sockudo', $connection['driver']);
        self::assertSame('test-key', $connection['key']);
    }

    public function testLaravelResolvesTheSockudoBroadcaster(): void
    {
        $broadcaster = $this->app->make(BroadcastManager::class)->connection('sockudo');
        $manager = $this->app->make(SockudoManager::class);

        self::assertInstanceOf(SockudoBroadcaster::class, $broadcaster);
        self::assertSame('test-app', $broadcaster->getSockudo()->getSettings()['app_id']);
        self::assertSame($manager->connection(), $broadcaster->getSockudo());
    }

    public function testItBindsTheManagerAndDefaultSdkClient(): void
    {
        $manager = $this->app->make(SockudoManager::class);
        $client = $this->app->make(SockudoInterface::class);

        self::assertSame($client, $manager->connection());
        self::assertSame('127.0.0.1', $client->getSettings()['host']);
    }

    public function testConfigurationCheckDoesNotRequireNetworkAccess(): void
    {
        $this->artisan('sockudo:check', ['--config-only' => true])
            ->expectsOutputToContain('Sockudo configuration is valid.')
            ->assertExitCode(0);
    }

    public function testSdkTimeoutIsAppliedToTheConfiguredHttpClient(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([new Response(200, [], '{}')]));
        $handler->push(Middleware::history($history));

        $client = $this->app->make(SockudoManager::class)->make([
            'driver' => 'sockudo',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 6001,
                'scheme' => 'http',
                'timeout' => 12,
            ],
            'client_options' => [
                'connect_timeout' => 2,
                'handler' => $handler,
            ],
        ]);

        $client->trigger('orders', 'test', ['ok' => true]);

        self::assertCount(1, $history);
        self::assertSame(12, $history[0]['options']['timeout']);
        self::assertSame(2, $history[0]['options']['connect_timeout']);
    }
}
