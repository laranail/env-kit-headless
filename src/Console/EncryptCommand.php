<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

/**
 * Per-VALUE encryption (engine cipher). Deliberately NOT aliased to `env:encrypt`
 * — that is Laravel's whole-file core command, which we never shadow.
 */
final class EncryptCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.encrypt-value
        {key : the key whose value to encrypt in place}
        {--file= : operate on a custom .env file}';

    /** @var string */
    protected $description = "Encrypt a single key's value in place (read it back with EnvKit::getDecrypted).";

    /** @var list<string> */
    protected array $commandAliases = ['env:encrypt-value'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $key = is_string($this->argument('key')) ? $this->argument('key') : '';
            $target = $this->targetEnv($env);

            if ($target->missing($key)) {
                $this->error("Key [{$key}] not found.");

                return self::EXIT_USAGE;
            }

            $target->encrypt($key);
            $this->info("Encrypted [{$key}].");

            return self::EXIT_OK;
        });
    }
}
