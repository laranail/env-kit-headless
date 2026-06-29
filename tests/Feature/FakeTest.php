<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Testing\EnvKitFake;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('records mutations and answers reads from memory', function () {
    $fake = EnvKit::fake(['APP_NAME' => 'Acme', 'DEBUG' => 'true']);

    expect(EnvKit::get('APP_NAME'))->toBe('Acme')
        ->and(EnvKit::getBool('DEBUG'))->toBeTrue()
        ->and(EnvKit::has('APP_NAME'))->toBeTrue();

    EnvKit::set('NEW', 'val');
    EnvKit::forget('APP_NAME');

    expect(EnvKit::get('NEW'))->toBe('val');
    $fake->assertSet('NEW', 'val');
    $fake->assertForgotten('APP_NAME');
});

it('touches no disk when faked', function () {
    $path = $this->bindEnv("A=1\n");

    EnvKit::fake();
    EnvKit::set('B', '2');

    expect(file_get_contents($path))->toBe("A=1\n"); // the real file is untouched
});

it('is resolvable via DI and the helper after faking', function () {
    EnvKit::fake(['K' => 'v']);

    expect(app(EnvKitInterface::class))->toBeInstanceOf(EnvKitFake::class)
        ->and(env_kit('K'))->toBe('v');
});

// --- Direct reads -----------------------------------------------------------

it('answers get() from the map and falls back to the default', function () {
    $fake = new EnvKitFake(['A' => 'one']);

    expect($fake->get('A'))->toBe('one')
        ->and($fake->get('MISSING'))->toBeNull()
        ->and($fake->get('MISSING', 'fallback'))->toBe('fallback');
});

it('casts typed getters and returns typed defaults for missing keys', function () {
    $fake = new EnvKitFake([
        'STR' => 'hello',
        'FLAG' => 'yes',
        'NUM' => '42',
        'RATE' => '3.5',
        'LIST' => 'a,b,c',
        'JSON' => '{"x":1}',
    ]);

    expect($fake->getString('STR'))->toBe('hello')
        ->and($fake->getString('MISSING', 'def'))->toBe('def')
        ->and($fake->getBool('FLAG'))->toBeTrue()
        ->and($fake->getBool('MISSING', false))->toBeFalse()
        ->and($fake->getInt('NUM'))->toBe(42)
        ->and($fake->getInt('MISSING', 7))->toBe(7)
        ->and($fake->getFloat('RATE'))->toBe(3.5)
        ->and($fake->getFloat('MISSING', 1.5))->toBe(1.5)
        ->and($fake->getArray('LIST'))->toBe(['a', 'b', 'c'])
        ->and($fake->getArray('MISSING', ['d']))->toBe(['d'])
        ->and($fake->getJson('JSON'))->toBe(['x' => 1])
        ->and($fake->getJson('MISSING', 'none'))->toBe('none');
});

it('reports presence with has() and missing()', function () {
    $fake = new EnvKitFake(['A' => '1']);

    expect($fake->has('A'))->toBeTrue()
        ->and($fake->has('B'))->toBeFalse()
        ->and($fake->missing('B'))->toBeTrue()
        ->and($fake->missing('A'))->toBeFalse();
});

it('exposes the full map and its keys', function () {
    $fake = new EnvKitFake(['A' => '1', 'B' => '2']);

    expect($fake->all())->toBe(['A' => '1', 'B' => '2'])
        ->and($fake->keys())->toBe(['A', 'B']);
});

it('filters with only() and except()', function () {
    $fake = new EnvKitFake(['A' => '1', 'B' => '2', 'C' => '3']);

    expect($fake->only(['A', 'C']))->toBe(['A' => '1', 'C' => '3'])
        ->and($fake->except(['A', 'C']))->toBe(['B' => '2']);
});

it('groups keys by the prefix plus underscore boundary', function () {
    // DBNAME shares the "DB" prefix but not the "DB_" boundary, so it is excluded.
    $fake = new EnvKitFake(['DB_HOST' => 'h', 'DB_PORT' => '5432', 'DBNAME' => 'nope', 'APP_NAME' => 'x']);

    expect($fake->group('DB'))->toBe(['DB_HOST' => 'h', 'DB_PORT' => '5432'])
        ->and($fake->group('DB_'))->toBe(['DB_HOST' => 'h', 'DB_PORT' => '5432'])
        ->and($fake->group('APP'))->toBe(['APP_NAME' => 'x']);
});

it('renders the map back to .env text via raw()', function () {
    $fake = new EnvKitFake(['A' => '1', 'B' => 'two']);

    expect($fake->raw())->toBe("\nA=1\nB=two");
});

it('resolves ${VAR} references with interpolated()', function () {
    $fake = new EnvKitFake(['HOST' => 'localhost', 'URL' => 'http://${HOST}/api']);

    expect($fake->interpolated('URL'))->toBe('http://localhost/api')
        ->and($fake->interpolated('HOST'))->toBe('localhost')
        ->and($fake->interpolated('MISSING'))->toBeNull()
        ->and($fake->interpolated('MISSING', 'def'))->toBe('def');
});

it('lists entries as Setter objects', function () {
    $fake = new EnvKitFake(['A' => '1', 'B' => '2']);

    $entries = $fake->entries();

    expect($entries)->toHaveCount(2)
        ->and($entries->map(fn ($s) => $s->key)->all())->toBe(['A', 'B'])
        ->and($entries->map(fn ($s) => $s->value)->all())->toBe(['1', '2']);
});

// --- Direct writes (mutate + record) ----------------------------------------

it('set() stores the value and records the action', function () {
    $fake = new EnvKitFake;

    $result = $fake->set('K', 'v');

    expect($result)->toBe($fake)
        ->and($fake->get('K'))->toBe('v')
        ->and($fake->recorded)->toBe([['action' => 'set', 'key' => 'K', 'value' => 'v']]);
});

it('forget() removes the value and records the action', function () {
    $fake = new EnvKitFake(['K' => 'v']);

    $result = $fake->forget('K');

    expect($result)->toBe($fake)
        ->and($fake->has('K'))->toBeFalse()
        ->and($fake->recorded)->toBe([['action' => 'forget', 'key' => 'K', 'value' => null]]);
});

it('rename() moves an existing key and records from/to', function () {
    $fake = new EnvKitFake(['OLD' => 'v']);

    $result = $fake->rename('OLD', 'NEW');

    expect($result)->toBe($fake)
        ->and($fake->has('OLD'))->toBeFalse()
        ->and($fake->get('NEW'))->toBe('v')
        ->and($fake->recorded)->toBe([['action' => 'rename', 'key' => 'OLD', 'value' => 'NEW']]);
});

it('rename() of a missing key changes nothing but still records', function () {
    $fake = new EnvKitFake(['A' => '1']);

    $fake->rename('MISSING', 'NEW');

    expect($fake->has('NEW'))->toBeFalse()
        ->and($fake->all())->toBe(['A' => '1'])
        ->and($fake->recorded)->toBe([['action' => 'rename', 'key' => 'MISSING', 'value' => 'NEW']]);
});

it('setMany() sets and records each pair', function () {
    $fake = new EnvKitFake;

    $result = $fake->setMany(['A' => '1', 'B' => '2']);

    expect($result)->toBe($fake)
        ->and($fake->all())->toBe(['A' => '1', 'B' => '2'])
        ->and($fake->recorded)->toBe([
            ['action' => 'set', 'key' => 'A', 'value' => '1'],
            ['action' => 'set', 'key' => 'B', 'value' => '2'],
        ]);
});

it('transaction() runs the callback with the fake itself', function () {
    $fake = new EnvKitFake(['A' => '1']);

    $received = null;
    $out = $fake->transaction(function ($kit) use (&$received) {
        $received = $kit;
        $kit->set('B', '2');

        return 'done';
    });

    expect($out)->toBe('done')
        ->and($received)->toBe($fake)
        ->and($fake->get('B'))->toBe('2');
});

// --- Assertions -------------------------------------------------------------

it('assertSet passes for a set key and matching value', function () {
    $fake = new EnvKitFake;
    $fake->set('K', 'v');

    $fake->assertSet('K');
    $fake->assertSet('K', 'v');
});

it('assertSet fails when the key was never set', function () {
    $fake = new EnvKitFake;

    expect(fn () => $fake->assertSet('MISSING'))
        ->toThrow(AssertionFailedError::class);
});

it('assertSet fails when the value does not match', function () {
    $fake = new EnvKitFake;
    $fake->set('K', 'v');

    expect(fn () => $fake->assertSet('K', 'other'))
        ->toThrow(AssertionFailedError::class);
});

it('assertForgotten passes for an absent key', function () {
    $fake = new EnvKitFake(['A' => '1']);
    $fake->forget('A');

    $fake->assertForgotten('A');
});

it('assertForgotten fails when the key is still present', function () {
    $fake = new EnvKitFake(['A' => '1']);

    expect(fn () => $fake->assertForgotten('A'))
        ->toThrow(AssertionFailedError::class);
});

it('assertNothingChanged passes with no writes and fails after one', function () {
    $fake = new EnvKitFake(['A' => '1']);
    $fake->assertNothingChanged();

    $fake->set('B', '2');
    expect(fn () => $fake->assertNothingChanged())
        ->toThrow(AssertionFailedError::class);
});
