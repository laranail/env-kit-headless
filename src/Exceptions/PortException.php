<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

final class PortException extends EnvKitException
{
    public static function unknownFormat(string $name): self
    {
        return new self("Unknown port format [{$name}].");
    }

    public static function malformed(string $format): self
    {
        return new self("Malformed {$format} import payload.");
    }
}
