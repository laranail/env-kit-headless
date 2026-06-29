<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

final class CheckCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.check
        {--example= : path to the .env.example template (default: sibling .env.example)}
        {--file= : operate on a custom .env file}';

    /** @var string */
    protected $description = 'List keys in .env.example missing from .env (non-zero exit on drift — CI friendly).';

    /** @var list<string> */
    protected array $commandAliases = ['env:check'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $value = $this->option('example');
            $example = is_string($value) && $value !== '' ? $value : null;

            $missing = $this->targetEnv($env)->missingFromExample($example);
            if ($missing === []) {
                $this->info('.env is in sync with the example.');

                return self::EXIT_OK;
            }

            $this->warn(sprintf('%d key(s) missing from .env: %s', count($missing), implode(', ', $missing)));

            return self::EXIT_VALIDATION;
        });
    }
}
