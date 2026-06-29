<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\EnvKit\Headless\Schema\EnvSchema;

/**
 * Laravel validation rule applying one key's {@see EnvSchema} rules — so a WebUI
 * FormRequest validates a value exactly as the CLI/programmatic schema does.
 */
final class MatchesEnvSchema implements ValidationRule
{
    public function __construct(
        private readonly EnvSchema $schema,
        private readonly string $key,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = $this->schema->validate([$this->key => is_string($value) ? $value : '']);

        foreach ($result->errors()[$this->key] ?? [] as $message) {
            $fail("The :attribute {$message}.");
        }
    }
}
