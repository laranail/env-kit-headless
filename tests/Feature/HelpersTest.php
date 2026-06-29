<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('returns the bound EnvKitInterface instance when called with no args', function () {
    $this->bindEnv("A=1\n");

    expect(env_kit())->toBe(app(EnvKitInterface::class))
        ->and(env_kit())->toBeInstanceOf(EnvKitInterface::class);
});

it('reads a key value when given a key', function () {
    $this->bindEnv("APP_NAME=Acme\n");

    expect(env_kit('APP_NAME'))->toBe('Acme');
});

it('returns null for a missing key with no default', function () {
    $this->bindEnv("A=1\n");

    expect(env_kit('MISSING'))->toBeNull();
});

it('returns the supplied default for a missing key', function () {
    $this->bindEnv("A=1\n");

    expect(env_kit('MISSING', 'fallback'))->toBe('fallback');
});

it('ignores the default when the key exists', function () {
    $this->bindEnv("PRESENT=real\n");

    expect(env_kit('PRESENT', 'fallback'))->toBe('real');
});
