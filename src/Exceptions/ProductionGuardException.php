<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

/** A write was attempted in production without an explicit opt-in. */
final class ProductionGuardException extends EnvKitException
{
    public static function make(): self
    {
        return new self(
            'Refusing to modify the .env file in production. '
            .'Opt in per-call with ->allowProduction(), the --force-production flag, '
            .'or set protect_production=false.'
        );
    }

    public function envKitReason(): string
    {
        return 'production';
    }
}
