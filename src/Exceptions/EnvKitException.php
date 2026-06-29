<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

use RuntimeException;

/**
 * Base for every EnvKit exception, so callers can catch the whole family in one
 * line. Concrete subclasses are enumerated as build slices need them (§3B).
 */
class EnvKitException extends RuntimeException
{
    /** A stable reason code used when a refused write emits a WriteRejected event. */
    public function envKitReason(): string
    {
        return 'rejected';
    }
}
