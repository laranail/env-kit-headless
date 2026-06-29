<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

final class BackupDeleteCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.backup-delete
        {name? : the backup file name to delete}
        {--older-than= : instead, delete every backup older than N days}
        {--file= : operate on a custom .env file}';

    /** @var string */
    protected $description = 'Delete a named backup, or prune backups older than N days.';

    /** @var list<string> */
    protected array $commandAliases = ['env:backup-delete'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $backups = $this->targetEnv($env)->backups();

            $older = $this->option('older-than');
            if (is_numeric($older)) {
                $count = $backups->deleteOlderThan((int) $older);
                $this->info("Deleted {$count} backup(s) older than {$older} day(s).");

                return self::EXIT_OK;
            }

            $name = $this->argument('name');
            if (! is_string($name) || $name === '') {
                $this->error('Provide a backup name, or --older-than=DAYS.');

                return self::EXIT_USAGE;
            }

            if ($backups->delete($name)) {
                $this->info("Deleted backup [{$name}].");

                return self::EXIT_OK;
            }

            $this->warn("Backup [{$name}] not found.");

            return self::EXIT_OK;
        });
    }
}
