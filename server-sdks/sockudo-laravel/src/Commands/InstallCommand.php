<?php

declare(strict_types=1);

namespace Sockudo\Laravel\Commands;

use Illuminate\Console\Command;
use Sockudo\Laravel\SockudoServiceProvider;

class InstallCommand extends Command
{
    protected $signature = 'sockudo:install {--force : Overwrite the published configuration file}';

    protected $description = 'Publish the Sockudo configuration and show the required environment variables';

    public function handle(): int
    {
        $arguments = [
            '--provider' => SockudoServiceProvider::class,
            '--tag' => 'sockudo-config',
        ];

        if ($this->option('force')) {
            $arguments['--force'] = true;
        }

        $this->call('vendor:publish', $arguments);
        $this->newLine();
        $this->components->info('Sockudo Laravel integration installed.');
        $this->line('Set BROADCAST_CONNECTION=sockudo and configure SOCKUDO_APP_ID, SOCKUDO_APP_KEY, SOCKUDO_APP_SECRET, and SOCKUDO_HOST.');

        if (!file_exists(base_path('routes/channels.php'))) {
            $this->components->warn('Laravel broadcasting routes are not installed. Run php artisan install:broadcasting before using private or presence channels.');
        }

        $this->line('Run php artisan sockudo:check after configuration.');

        return self::SUCCESS;
    }
}
