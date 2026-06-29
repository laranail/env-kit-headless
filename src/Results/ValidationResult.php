<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Results;

use Simtabi\Laranail\EnvKit\Headless\Schema\EnvSchema;

/** The outcome of validating an .env against an {@see EnvSchema}. */
final class ValidationResult
{
    /** @param array<string, list<string>> $errors key => list of failure messages */
    public function __construct(
        private readonly array $errors = [],
    ) {}

    public function passed(): bool
    {
        return $this->errors === [];
    }

    public function failed(): bool
    {
        return ! $this->passed();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<string> flat "KEY message" lines */
    public function messages(): array
    {
        $out = [];
        foreach ($this->errors as $key => $messages) {
            foreach ($messages as $message) {
                $out[] = "{$key} {$message}";
            }
        }

        return $out;
    }
}
