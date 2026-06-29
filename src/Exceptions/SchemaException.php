<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

/** Raised by `EnvKit::assertValid()` when the .env does not satisfy the schema. */
final class SchemaException extends EnvKitException
{
    /** @param list<string> $messages */
    public static function failed(array $messages): self
    {
        return new self('Schema validation failed: '.implode('; ', $messages));
    }

    public function envKitReason(): string
    {
        return 'invalid';
    }
}
