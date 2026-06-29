<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Security\KeyValidator;
use Simtabi\Laranail\EnvKit\Headless\Security\ValueSanitizer;

final class ValidateCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.validate {--file= : operate on a custom .env file}';

    /** @var string */
    protected $description = 'Check every key/value for well-formedness + the configured schema.';

    /** @var list<string> */
    protected array $commandAliases = ['env:validate'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $target = $this->targetEnv($env);
            $entries = $target->all();
            $keyValidator = new KeyValidator;
            $sanitizer = new ValueSanitizer;

            $violations = [];
            foreach ($entries as $key => $value) {
                if (! $keyValidator->isValid($key)) {
                    $violations[] = "Invalid key name: [{$key}].";
                }
                if (! $sanitizer->isClean($value)) {
                    $violations[] = "Unsafe control characters in value for key [{$key}].";
                }
            }

            // Schema rules (config + runtime), when any are defined.
            foreach ($target->validate()->messages() as $message) {
                $violations[] = "Schema: {$message}";
            }

            if ($violations !== []) {
                foreach ($violations as $violation) {
                    $this->error($violation);
                }

                return self::EXIT_VALIDATION;
            }

            $this->info(sprintf('All %d entries are valid.', count($entries)));

            return self::EXIT_OK;
        });
    }
}
