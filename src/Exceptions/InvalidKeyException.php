<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

final class InvalidKeyException extends EnvKitException
{
    public static function for(string $key): self
    {
        // Key NAMES are safe to surface (they are not secret); values never are.
        return new self("Invalid environment key: {$key}. Keys must match /^[A-Za-z_][A-Za-z0-9_]*$/.");
    }

    public function envKitReason(): string
    {
        return 'invalid';
    }
}
