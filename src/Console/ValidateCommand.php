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
    protected $description = 'Check every key/value for well-formedness.';

    /** @var list<string> */
    protected array $commandAliases = ['env:validate'];

    public function handle(EnvKit $env): int
    {
        return $this->runSafely(function () use ($env): int {
            $entries = $this->targetEnv($env)->all();
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
