<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Document\Entry\Setter;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\KeyNotFoundException;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('update() sets an existing key and throws on a missing one', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::update('A', '2');
    expect(EnvKit::get('A'))->toBe('2');

    expect(fn () => EnvKit::update('MISSING', 'x'))->toThrow(KeyNotFoundException::class);
});

it('setIfMissing() only writes when the key is absent', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::setIfMissing('A', '9'); // no-op
    EnvKit::setIfMissing('B', '2'); // writes

    expect(EnvKit::get('A'))->toBe('1')
        ->and(EnvKit::get('B'))->toBe('2');
});

it('setOrUpdate() upserts whether the key exists or not', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::setOrUpdate('A', '2')->setOrUpdate('NEW', 'v');

    expect(EnvKit::get('A'))->toBe('2')
        ->and(EnvKit::get('NEW'))->toBe('v');
});

it('forgetMany() removes several keys in one commit', function () {
    $this->bindEnv("A=1\nB=2\nC=3\n", ['env-kit.auto_backup' => false]);

    EnvKit::forgetMany(['A', 'C']);

    expect(EnvKit::has('A'))->toBeFalse()
        ->and(EnvKit::has('B'))->toBeTrue()
        ->and(EnvKit::has('C'))->toBeFalse();
});

it('setExport() toggles the export prefix on an existing key', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::setExport('A');
    expect((string) file_get_contents($path))->toContain('export A=1');

    EnvKit::setExport('A', false);
    expect((string) file_get_contents($path))->not->toContain('export ');

    expect(fn () => EnvKit::setExport('MISSING'))->toThrow(KeyNotFoundException::class);
});

it('entry() returns a key\'s setter metadata or null', function () {
    $this->bindEnv("A=hello\n", ['env-kit.auto_backup' => false]);

    expect(EnvKit::entry('A'))->toBeInstanceOf(Setter::class)
        ->and(EnvKit::entry('A')->value)->toBe('hello')
        ->and(EnvKit::entry('MISSING'))->toBeNull();
});

it('the fake mirrors the new write helpers', function () {
    $fake = EnvKit::fake(['A' => '1']);

    $fake->setOrUpdate('A', '2')->setIfMissing('A', '9')->setIfMissing('B', '3')->forgetMany(['B']);
    expect($fake->get('A'))->toBe('2')
        ->and($fake->has('B'))->toBeFalse()
        ->and($fake->entry('A'))->toBeInstanceOf(Setter::class);

    expect(fn () => $fake->update('NOPE', 'x'))->toThrow(KeyNotFoundException::class);
    expect(fn () => $fake->setExport('NOPE'))->toThrow(KeyNotFoundException::class);
    $fake->setExport('A'); // records, no throw
});
