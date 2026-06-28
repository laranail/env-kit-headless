<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

final class BackupCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.backup {--file= : operate on a custom .env file}';

    /** @var string */
    protected $description = 'Snapshot the .env file to the backup directory.';

    /** @var list<string> */
    protected array $commandAliases = ['env:backup'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $backup = $this->targetEnv($env)->backup();

            if ($backup === null) {
                $this->warn('Nothing to back up (file not found).');

                return self::EXIT_OK;
            }

            $this->info("Backed up to [{$backup->name}].");

            return self::EXIT_OK;
        });
    }
}
