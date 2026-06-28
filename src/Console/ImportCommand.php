<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

final class ImportCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.import
        {source : the json or csv file to import}
        {--format=json : json or csv}
        {--file= : operate on a custom .env file}
        {--force-production : allow the write in production}';

    /** @var string */
    protected $description = 'Import keys from a json or csv file.';

    /** @var list<string> */
    protected array $commandAliases = ['env:import'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $source = $this->stringArgument('source');
            if (! is_file($source)) {
                return $this->failWith("Source not found: [{$source}].", self::EXIT_USAGE);
            }

            $this->targetEnv($env)->import((string) file_get_contents($source), $this->formatOption());
            $this->info("Imported from [{$source}].");

            return self::EXIT_OK;
        });
    }
}
