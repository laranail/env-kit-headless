<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

/**
 * Per-VALUE decryption (engine cipher). Deliberately NOT aliased to `env:decrypt`
 * — that is Laravel's whole-file core command, which we never shadow.
 */
final class DecryptCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.decrypt-value
        {key : the key whose value to decrypt in place}
        {--file= : operate on a custom .env file}';

    /** @var string */
    protected $description = "Decrypt a single key's previously-encrypted value back to plaintext in place.";

    /** @var list<string> */
    protected array $commandAliases = ['env:decrypt-value'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $key = is_string($this->argument('key')) ? $this->argument('key') : '';
            $target = $this->targetEnv($env);

            if ($target->missing($key)) {
                $this->error("Key [{$key}] not found.");

                return self::EXIT_USAGE;
            }

            $target->decrypt($key);
            $this->info("Decrypted [{$key}].");

            return self::EXIT_OK;
        });
    }
}
