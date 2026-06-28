<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Security;

use Illuminate\Contracts\Encryption\Encrypter;
use Simtabi\Laranail\EnvKit\Headless\Contracts\ValueCipherInterface;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\EncryptionException;

/**
 * The default cipher: Laravel's APP_KEY-based {@see Encrypter}. Ciphertext is
 * prefixed `envkit:` so it round-trips through the document unquoted (base64,
 * no special chars) and is trivially recognisable on read.
 */
final class LaravelValueCipher implements ValueCipherInterface
{
    private const PREFIX = 'envkit:';

    public function __construct(
        private readonly Encrypter $encrypter,
    ) {}

    public function encrypt(string $plain): string
    {
        return self::PREFIX.$this->encrypter->encrypt($plain);
    }

    public function decrypt(string $cipher): string
    {
        if (! $this->isEncrypted($cipher)) {
            return $cipher;
        }

        $value = $this->encrypter->decrypt(substr($cipher, \strlen(self::PREFIX)));

        if (! \is_string($value)) {
            throw EncryptionException::corrupt();
        }

        return $value;
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
