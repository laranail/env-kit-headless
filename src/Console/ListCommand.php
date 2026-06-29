<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Security\SecretRedactor;

final class ListCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.list
        {--file= : operate on a custom .env file}
        {--reveal : show secret values instead of masking them}';

    /** @var string */
    protected $description = 'List keys and values (secret-shaped values masked unless --reveal).';

    /** @var list<string> */
    protected array $commandAliases = ['env:list'];

    public function handle(EnvKit $env, SecretRedactor $redactor): int
    {
        return $this->runSafely(function () use ($env, $redactor): int {
            $reveal = (bool) $this->option('reveal');

            foreach ($this->targetEnv($env)->all() as $key => $value) {
                $this->line($key.'='.($reveal ? $value : $redactor->forKey($key, $value)));
            }

            return self::EXIT_OK;
        });
    }
}
