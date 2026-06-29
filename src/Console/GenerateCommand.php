<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;

final class GenerateCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.generate
        {type=token : token|hex|base64|app_key}
        {--bytes=32 : entropy for token/hex/base64}
        {--set= : write the generated value to this key (subject to the key policy)}
        {--file= : operate on a custom .env file}';

    /** @var string */
    protected $description = 'Generate a secret value (random token or Laravel APP_KEY); optionally write it to a key.';

    /** @var list<string> */
    protected array $commandAliases = ['env:generate'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $target = $this->targetEnv($env);
            $type = is_string($this->argument('type')) ? $this->argument('type') : 'token';

            $bytes = $this->option('bytes');
            $options = is_numeric($bytes) ? ['bytes' => (int) $bytes] : [];

            $value = $target->generate($type, $options);

            $setKey = $this->option('set');
            if (is_string($setKey) && $setKey !== '') {
                $target->set($setKey, $value);
                $this->info("Generated and wrote [{$setKey}].");

                return self::EXIT_OK;
            }

            $this->line($value);

            return self::EXIT_OK;
        });
    }
}
