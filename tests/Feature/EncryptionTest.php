<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\Encrypter;
use Simtabi\Laranail\EnvKit\Headless\Contracts\ValueCipherInterface;
use Simtabi\Laranail\EnvKit\Headless\EnvKitManager;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Security\LaravelValueCipher;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('reads the configured encryption driver name, defaulting to laravel', function () {
    $manager = app(EnvKitManager::class);

    expect($manager->getDefaultDriver())->toBe('laravel');

    config(['env-kit.encryption.driver' => 'custom-name']);
    expect($manager->getDefaultDriver())->toBe('custom-name');

    // A non-string config value falls back to the 'laravel' default.
    config(['env-kit.encryption.driver' => ['not', 'a', 'string']]);
    expect($manager->getDefaultDriver())->toBe('laravel');
});

it('throws when a resolved driver is not a ValueCipherInterface', function () {
    $manager = app(EnvKitManager::class);

    $manager->extend('not-a-cipher', fn () => new stdClass);

    expect(fn () => $manager->cipher('not-a-cipher'))
        ->toThrow(LogicException::class);
});

it('passes the container to custom driver creators', function () {
    $manager = app(EnvKitManager::class);

    $manager->extend('from-container', fn ($container) => new LaravelValueCipher(
        $container->make(Encrypter::class),
    ));

    expect($manager->cipher('from-container'))->toBeInstanceOf(LaravelValueCipher::class);
});

it('encrypts a value at rest and decrypts it back', function () {
    $path = $this->bindEnv("API_TOKEN=plaintext-secret\n", ['env-kit.auto_backup' => false]);

    EnvKit::encrypt('API_TOKEN');

    // at rest the file holds ciphertext, not the plaintext
    $raw = (string) file_get_contents($path);
    expect($raw)->not->toContain('plaintext-secret')
        ->and($raw)->toContain('envkit:');

    // get() returns the at-rest ciphertext; getDecrypted() returns the plaintext
    expect(EnvKit::get('API_TOKEN'))->not->toBe('plaintext-secret')
        ->and(EnvKit::getDecrypted('API_TOKEN'))->toBe('plaintext-secret');

    // decrypt() restores plaintext at rest
    EnvKit::decrypt('API_TOKEN');
    expect((string) file_get_contents($path))->toContain('API_TOKEN=plaintext-secret')
        ->and(EnvKit::get('API_TOKEN'))->toBe('plaintext-secret');
});

it('setEncrypted stores ciphertext that getDecrypted reads back', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::setEncrypted('DB_PASSWORD', 'hunter2');

    expect((string) file_get_contents($path))->not->toContain('hunter2')
        ->and(EnvKit::getDecrypted('DB_PASSWORD'))->toBe('hunter2');
});

it('resolves the default laravel cipher and honours EnvKitManager::extend()', function () {
    $manager = app(EnvKitManager::class);

    expect($manager->cipher())->toBeInstanceOf(LaravelValueCipher::class);

    $manager->extend('reverse', fn () => new class implements ValueCipherInterface
    {
        public function encrypt(string $plain): string
        {
            return 'rev:'.strrev($plain);
        }

        public function decrypt(string $cipher): string
        {
            return strrev(substr($cipher, 4));
        }

        public function isEncrypted(string $value): bool
        {
            return str_starts_with($value, 'rev:');
        }
    });

    $cipher = $manager->cipher('reverse');
    $encrypted = $cipher->encrypt('abc');

    expect($encrypted)->toBe('rev:cba')
        ->and($cipher->decrypt($encrypted))->toBe('abc');
});

it('uses a custom cipher registered via configure()->useCipher()', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::configure()->useCipher(new class implements ValueCipherInterface
    {
        public function encrypt(string $plain): string
        {
            return 'X:'.base64_encode($plain);
        }

        public function decrypt(string $cipher): string
        {
            return (string) base64_decode(substr($cipher, 2), true);
        }

        public function isEncrypted(string $value): bool
        {
            return str_starts_with($value, 'X:');
        }
    });

    EnvKit::setEncrypted('TOKEN', 'abc');

    expect((string) file_get_contents($path))->toContain('TOKEN=X:')
        ->and(EnvKit::getDecrypted('TOKEN'))->toBe('abc');
});
