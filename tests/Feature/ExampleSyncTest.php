<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Support\ExampleSync;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

function envkitDir(): string
{
    $dir = sys_get_temp_dir().'/envkit-ex-'.bin2hex(random_bytes(5));
    @mkdir($dir, 0777, true);

    return $dir;
}

it('the ExampleSync helper reports missing and extra keys', function () {
    $sync = new ExampleSync;
    $env = ['A' => '1', 'EXTRA' => 'x'];
    $example = ['A' => '', 'B' => '', 'C' => ''];

    expect($sync->missing($env, $example))->toBe(['B', 'C'])
        ->and($sync->extra($env, $example))->toBe(['EXTRA'])
        ->and($sync->inSync($env, $example))->toBeFalse()
        ->and($sync->inSync(['A' => '1', 'B' => '2', 'C' => '3'], $example))->toBeTrue();
});

it('lists keys missing from the .env relative to .env.example', function () {
    $dir = envkitDir();
    file_put_contents($dir.'/.env', "A=1\n");
    file_put_contents($dir.'/.env.example', "A=\nB=\nDB_HOST=\n");
    config(['env-kit.path' => $dir.'/.env', 'env-kit.auto_backup' => false]);
    $this->app->forgetInstance(EnvKitInterface::class);

    expect(EnvKit::missingFromExample())->toBe(['B', 'DB_HOST']);
});

it('syncs missing keys from .env.example into .env', function () {
    $dir = envkitDir();
    file_put_contents($dir.'/.env', "A=1\n");
    file_put_contents($dir.'/.env.example', "A=\nB=default\nDB_HOST=localhost\n");
    config(['env-kit.path' => $dir.'/.env', 'env-kit.auto_backup' => false]);
    $this->app->forgetInstance(EnvKitInterface::class);

    EnvKit::syncFromExample();

    expect(EnvKit::get('B'))->toBe('default')
        ->and(EnvKit::get('DB_HOST'))->toBe('localhost')
        ->and(EnvKit::get('A'))->toBe('1')             // untouched
        ->and(EnvKit::missingFromExample())->toBe([]); // now in sync
});

it('env:check exits non-zero on drift and zero when in sync', function () {
    $dir = envkitDir();
    file_put_contents($dir.'/.env', "A=1\n");
    file_put_contents($dir.'/.env.example', "A=\nB=\n");
    config(['env-kit.path' => $dir.'/.env', 'env-kit.auto_backup' => false]);
    $this->app->forgetInstance(EnvKitInterface::class);

    $this->artisan('env:check')->expectsOutputToContain('missing')->assertExitCode(3);

    $this->artisan('env:sync')->expectsOutputToContain('Added')->assertExitCode(0);
    $this->artisan('env:check')->expectsOutputToContain('in sync')->assertExitCode(0);
});

it('env:generate produces a value and can write it to a key', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    $this->artisan('env:generate', ['type' => 'hex', '--bytes' => 8])->assertExitCode(0);
    $this->artisan('env:generate', ['type' => 'app_key', '--set' => 'NEW_KEY'])
        ->expectsOutputToContain('wrote')
        ->assertExitCode(0);

    expect(EnvKit::get('NEW_KEY'))->toStartWith('base64:');
});

it('EnvKit::generate produces the requested secret shapes', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    expect(EnvKit::generate('app_key'))->toStartWith('base64:')
        ->and(EnvKit::generate('hex', ['bytes' => 4]))->toHaveLength(8)       // 4 bytes → 8 hex chars
        ->and(EnvKit::generate('base64'))->not->toContain('=')
        ->and(EnvKit::fake(['A' => '1'])->generate('app_key'))->toStartWith('base64:');
});
