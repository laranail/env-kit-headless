<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

final class BackupNotFoundException extends EnvKitException
{
    public static function named(string $name): self
    {
        return new self("No backup named [{$name}] was found.");
    }
}
