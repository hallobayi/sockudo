<?php

declare(strict_types=1);

namespace Sockudo\Laravel;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Sockudo\Laravel\Commands\CheckCommand;
use Sockudo\Laravel\Commands\InstallCommand;
use Sockudo\SockudoInterface;

class SockudoServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sockudo.php', 'sockudo');

        $this->app->singleton(SockudoManager::class, fn($app): SockudoManager => new SockudoManager($app));
        $this->app->alias(SockudoManager::class, 'sockudo');
        $this->app->bind(
            SockudoInterface::class,
            fn($app): SockudoInterface => $app->make(SockudoManager::class)->connection(),
        );

        $this->registerDefaultBroadcastConnection();
    }

    public function boot(BroadcastManager $broadcastManager): void
    {
        $broadcastManager->extend('sockudo', function ($app, array $config): SockudoBroadcaster {
            $client = $app->make(SockudoManager::class)->connectionForConfig($config);

            return new SockudoBroadcaster($client, (bool) ($config['jsonp'] ?? false));
        });

        $this->publishes([
            __DIR__ . '/../config/sockudo.php' => config_path('sockudo.php'),
        ], 'sockudo-config');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, CheckCommand::class]);
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function registerDefaultBroadcastConnection(): void
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make('config');
        $name = (string) $config->get('sockudo.connection', 'sockudo');

        if (!$config->has('broadcasting.default')) {
            $config->set('broadcasting.default', $config->get('sockudo.default', 'null'));
        }

        if ($config->has("broadcasting.connections.{$name}")) {
            return;
        }

        $connection = $config->get('sockudo.broadcasting', []);

        if (is_array($connection)) {
            $config->set("broadcasting.connections.{$name}", $connection);
        }
    }
}
