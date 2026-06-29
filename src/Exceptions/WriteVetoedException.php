<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

/** A write observer vetoed a commit (a before-hook returned false / a denial). */
final class WriteVetoedException extends EnvKitException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function envKitReason(): string
    {
        return 'vetoed';
    }
}
