<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

final class InvalidEnvironmentException extends EnvKitException
{
    public static function for(string $environment): self
    {
        return new self("Invalid environment name [{$environment}]: it must contain only letters, digits, dots, hyphens and underscores (no path separators).");
    }
}
