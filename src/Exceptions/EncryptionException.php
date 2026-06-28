<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Exceptions;

final class EncryptionException extends EnvKitException
{
    public static function notConfigured(): self
    {
        return new self('No cipher configured. Set an APP_KEY, or register one via configure()->useCipher().');
    }

    public static function corrupt(): self
    {
        return new self('Failed to decrypt value: unexpected payload.');
    }
}
