<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

/**
 * Raised for an unusable value. Messages reference the KEY and the reason only —
 * never the raw value, which may be a secret.
 */
final class InvalidValueException extends EnvKitException
{
    public static function nulByte(?string $key = null): self
    {
        return new self('Value contains a NUL byte'.($key !== null ? " (key {$key})" : '').'.');
    }

    public static function tooLong(?string $key, int $max): self
    {
        return new self('Value exceeds the maximum length of '.$max.' bytes'.($key !== null ? " (key {$key})" : '').'.');
    }

    public function envKitReason(): string
    {
        return 'invalid';
    }
}
