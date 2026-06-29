<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

final class NotEditableException extends EnvKitException
{
    public static function for(string $key): self
    {
        return new self("Key [{$key}] is not in the editable allowlist.");
    }

    public function envKitReason(): string
    {
        return 'not_editable';
    }
}
