<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('env:encrypt-value encrypts a key in place and env:decrypt-value reverses it', function () {
    $path = $this->bindEnv("SECRET=plaintext\n", ['env-kit.auto_backup' => false]);

    $this->artisan('env:encrypt-value', ['key' => 'SECRET'])
        ->expectsOutputToContain('Encrypted')
        ->assertExitCode(0);

    expect((string) file_get_contents($path))->toContain('envkit:')     // stored ciphertext
        ->and(EnvKit::get('SECRET'))->toStartWith('envkit:')
        ->and(EnvKit::getDecrypted('SECRET'))->toBe('plaintext');         // round-trips

    $this->artisan('env:decrypt-value', ['key' => 'SECRET'])
        ->expectsOutputToContain('Decrypted')
        ->assertExitCode(0);

    expect(EnvKit::get('SECRET'))->toBe('plaintext');
});

it('env:encrypt-value errors on a missing key', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    $this->artisan('env:encrypt-value', ['key' => 'NOPE'])
        ->expectsOutputToContain('not found')
        ->assertExitCode(2);
});
