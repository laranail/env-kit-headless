<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

final class SyncCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.sync
        {--example= : path to the .env.example template (default: sibling .env.example)}
        {--file= : operate on a custom .env file}';

    /** @var string */
    protected $description = 'Add keys present in .env.example but missing from .env.';

    /** @var list<string> */
    protected array $commandAliases = ['env:sync'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $target = $this->targetEnv($env);
            $example = $this->exampleOption();

            $missing = $target->missingFromExample($example);
            if ($missing === []) {
                $this->info('.env is already in sync with the example.');

                return self::EXIT_OK;
            }

            $target->syncFromExample($example);
            $this->info(sprintf('Added %d missing key(s): %s', count($missing), implode(', ', $missing)));

            return self::EXIT_OK;
        });
    }

    private function exampleOption(): ?string
    {
        $value = $this->option('example');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
