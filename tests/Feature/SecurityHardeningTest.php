<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Exceptions\InvalidEnvironmentException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\InvalidValueException;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('rejects path-traversal environment names in on()', function (string $bad) {
    $this->bindEnv("A=1\n");

    expect(fn () => EnvKit::on($bad))->toThrow(InvalidEnvironmentException::class);
})->with([['../etc'], ['a/b'], ['a\\b'], ['..'], [''], ['.hidden']]);

it('accepts safe environment names in on()', function () {
    $this->bindEnv("A=1\n");

    expect(EnvKit::on('staging')->path())->toEndWith('.env.staging')
        ->and(EnvKit::on('dusk.local')->path())->toEndWith('.env.dusk.local');
});

it('strips control characters from written values', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::set('A', "x\x07y\x08z"); // bell + backspace stripped

    expect(EnvKit::getString('A'))->toBe('xyz');
});

it('rejects NUL bytes in written values', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    expect(fn () => EnvKit::set('A', "x\0y"))->toThrow(InvalidValueException::class);
    expect(EnvKit::getString('A'))->toBe('1'); // unchanged
});

it('rejects values longer than the configured limit', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false, 'env-kit.limits.max_value_length' => 10]);

    expect(fn () => EnvKit::set('A', str_repeat('x', 11)))->toThrow(InvalidValueException::class);

    EnvKit::set('A', str_repeat('x', 10)); // exactly at the limit is allowed
    expect(EnvKit::getString('A'))->toBe(str_repeat('x', 10));
});
