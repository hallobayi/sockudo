<?php

declare(strict_types=1);

namespace Sockudo\Laravel\Commands;

use Illuminate\Console\Command;
use Sockudo\Laravel\SockudoManager;
use Throwable;

class CheckCommand extends Command
{
    protected $signature = 'sockudo:check
        {--connection= : Named Sockudo broadcasting connection}
        {--config-only : Validate configuration without contacting Sockudo}';

    protected $description = 'Validate the Sockudo configuration and API connection';

    public function __construct(private readonly SockudoManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $name = $this->option('connection');
            $client = $this->manager->connection(is_string($name) && $name !== '' ? $name : null);
            $client->getSettings();

            if ($this->option('config-only')) {
                $this->components->info('Sockudo configuration is valid.');

                return self::SUCCESS;
            }

            $client->getChannels();
        } catch (Throwable) {
            $this->components->error('Sockudo check failed. Verify the connection name, server availability, TLS settings, and credentials.');

            return self::FAILURE;
        }

        $this->components->info('Sockudo API connection is healthy.');

        return self::SUCCESS;
    }
}
