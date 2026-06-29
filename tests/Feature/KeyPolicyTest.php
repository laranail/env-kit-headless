<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Exceptions\NotEditableException;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('refuses to write a key outside the configured editable allowlist', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false, 'env-kit.editable_keys' => ['APP_*']]);

    expect(fn () => EnvKit::set('DB_HOST', 'x'))->toThrow(NotEditableException::class);

    EnvKit::set('APP_NAME', 'Acme'); // allowlisted → writes
    expect(EnvKit::get('APP_NAME'))->toBe('Acme');
});

it('enforces an allowlist registered at runtime via configure()->onlyEditable()', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::configure()->onlyEditable(['SAFE_*']);

    expect(fn () => EnvKit::set('DANGER', 'x'))->toThrow(NotEditableException::class);

    EnvKit::set('SAFE_FLAG', 'on');
    expect(EnvKit::get('SAFE_FLAG'))->toBe('on');
});

it('also governs deletes and renames of non-editable keys', function () {
    $this->bindEnv("A=1\nB=2\n", ['env-kit.auto_backup' => false, 'env-kit.editable_keys' => ['A']]);

    expect(fn () => EnvKit::forget('B'))->toThrow(NotEditableException::class);
    EnvKit::forget('A'); // allowlisted → ok
    expect(EnvKit::has('A'))->toBeFalse();
});

it('returns CLI exit 3 for an editable-allowlist violation', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false, 'env-kit.editable_keys' => ['APP_*']]);

    $this->artisan('env:set', ['key' => 'DB_HOST', 'value' => 'x'])->assertExitCode(3);
});

it('env:list honours a custom hidden_keys pattern', function () {
    $this->bindEnv("APP_NAME=Acme\nWIDGET_API=topsecret123\n", ['env-kit.hidden_keys' => ['WIDGET_*']]);

    $this->artisan('env:list')
        ->expectsOutputToContain('APP_NAME=Acme')
        ->doesntExpectOutputToContain('topsecret123')
        ->assertExitCode(0);
});
