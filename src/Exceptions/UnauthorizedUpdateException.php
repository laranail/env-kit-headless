<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

/** The update gate denied a commit. The message is an authorization reason, never a value. */
final class UnauthorizedUpdateException extends EnvKitException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function envKitReason(): string
    {
        return 'unauthorized';
    }
}
