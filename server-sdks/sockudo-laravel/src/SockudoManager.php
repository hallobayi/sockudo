<?php

declare(strict_types=1);

namespace Sockudo\Laravel;

use GuzzleHttp\Client;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Sockudo\Sockudo;
use Sockudo\SockudoInterface;

class SockudoManager
{
    /**
     * @var array<string, SockudoInterface>
     */
    private array $connections = [];

    public function __construct(private readonly Container $app) {}

    public function connection(?string $name = null): SockudoInterface
    {
        $name ??= (string) $this->app['config']->get('sockudo.connection', 'sockudo');

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $config = $this->app['config']->get("broadcasting.connections.{$name}");

        if (!is_array($config) || ($config['driver'] ?? null) !== 'sockudo') {
            throw new InvalidArgumentException("Sockudo broadcasting connection [{$name}] is not defined.");
        }

        return $this->connections[$name] = $this->make($config);
    }

    /**
     * Resolve a named connection when Laravel provides only its config array.
     *
     * @param array<string, mixed> $config
     */
    public function connectionForConfig(array $config): SockudoInterface
    {
        $connections = $this->app['config']->get('broadcasting.connections', []);

        if (is_array($connections)) {
            foreach ($connections as $name => $candidate) {
                if (is_string($name) && $candidate === $config) {
                    return $this->connection($name);
                }
            }
        }

        return $this->make($config);
    }

    /**
     * Create an SDK client from a Laravel broadcasting connection.
     *
     * @param array<string, mixed> $config
     */
    public function make(array $config): SockudoInterface
    {
        $key = $this->requiredString($config, 'key');
        $secret = $this->requiredString($config, 'secret');
        $appId = $this->requiredString($config, 'app_id');
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];
        $clientOptions = is_array($config['client_options'] ?? null) ? $config['client_options'] : [];

        if (!array_key_exists('timeout', $clientOptions) && isset($options['timeout'])) {
            $clientOptions['timeout'] = $options['timeout'];
        }

        $httpClient = $clientOptions === [] ? null : new Client($clientOptions);

        return new Sockudo($key, $secret, $appId, $options, $httpClient);
    }

    public function purge(?string $name = null): void
    {
        $name ??= (string) $this->app['config']->get('sockudo.connection', 'sockudo');
        unset($this->connections[$name]);
    }

    /**
     * Proxy native SDK calls to the default Sockudo connection.
     *
     * @param array<int, mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->{$method}(...$parameters);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requiredString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Sockudo connection is missing [{$key}].");
        }

        return $value;
    }
}
