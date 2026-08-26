<?php

declare(strict_types=1);

namespace Sockudo\Laravel\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Sockudo\Laravel\SockudoServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SockudoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sockudo.connection', 'sockudo');
        $app['config']->set('broadcasting.connections.sockudo', [
            'driver' => 'sockudo',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 6001,
                'scheme' => 'http',
                'useTLS' => false,
            ],
        ]);
    }
}
