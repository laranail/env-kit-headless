<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

final class BackupsListCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.backups {--file= : operate on a custom .env file}';

    /** @var string */
    protected $description = 'List available .env backups (newest first).';

    /** @var list<string> */
    protected array $commandAliases = ['env:backups'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $backups = $this->targetEnv($env)->backups()->all();

            if ($backups === []) {
                $this->warn('No backups found.');

                return self::EXIT_OK;
            }

            foreach ($backups as $backup) {
                $this->line(sprintf('%s  (%d bytes)', $backup->name, $backup->size));
            }

            return self::EXIT_OK;
        });
    }
}
