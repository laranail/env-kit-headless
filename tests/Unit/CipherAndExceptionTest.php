<?php

declare(strict_types=1);

use Illuminate\Encryption\Encrypter;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\EncryptionException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\EnvKitException;
use Simtabi\Laranail\EnvKit\Headless\Security\LaravelValueCipher;

function envkitCipher(): LaravelValueCipher
{
    return new LaravelValueCipher(new Encrypter(str_repeat('a', 32), 'AES-256-CBC'));
}

it('round-trips an encrypted value and recognises the prefix', function () {
    $cipher = envkitCipher();
    $encrypted = $cipher->encrypt('s3cret');

    expect($cipher->isEncrypted($encrypted))->toBeTrue()
        ->and($encrypted)->toStartWith('envkit:')
        ->and($cipher->decrypt($encrypted))->toBe('s3cret');
});

it('passes a non-encrypted value through decrypt unchanged', function () {
    $cipher = envkitCipher();

    expect($cipher->isEncrypted('plain-text'))->toBeFalse()
        ->and($cipher->decrypt('plain-text'))->toBe('plain-text');
});

it('throws when a cipher value decrypts to a non-string', function () {
    $encrypter = new Encrypter(str_repeat('a', 32), 'AES-256-CBC');
    $cipher = new LaravelValueCipher($encrypter);
    $corrupt = 'envkit:'.$encrypter->encrypt(['not', 'a', 'string']); // array payload

    expect(fn () => $cipher->decrypt($corrupt))->toThrow(EncryptionException::class);
});

it('the base EnvKitException reports a generic rejection reason', function () {
    expect((new EnvKitException('boom'))->envKitReason())->toBe('rejected');
});
