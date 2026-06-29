<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

/** Attempt to write a key the policy marks read-only (e.g. APP_KEY, DB_PASSWORD). */
final class ProtectedKeyException extends EnvKitException
{
    public static function for(string $key): self
    {
        return new self("Refusing to modify protected key: {$key}.");
    }

    public function envKitReason(): string
    {
        return 'protected';
    }
}
