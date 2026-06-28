<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

final class RestoreCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.restore
        {name? : backup name to restore (defaults to the latest)}
        {--file= : operate on a custom .env file}
        {--force-production : allow the restore in production}';

    /** @var string */
    protected $description = 'Restore the .env from a backup (latest if no name given).';

    /** @var list<string> */
    protected array $commandAliases = ['env:restore'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $target = $this->targetEnv($env);

            $name = $this->stringArgument('name');
            if ($name === '') {
                $latest = $target->backups()->latest();
                if ($latest === null) {
                    return $this->failWith('No backups to restore from.', self::EXIT_VALIDATION);
                }
                $name = $latest->name;
            }

            $backup = $target->restore($name);
            $this->info("Restored from [{$backup->name}].");

            return self::EXIT_OK;
        });
    }
}
