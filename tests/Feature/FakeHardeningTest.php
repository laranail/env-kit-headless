<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('the fake applies the new write helpers to its in-memory map', function () {
    $fake = EnvKit::fake(['A' => '1', 'B' => '2']);

    $fake->update('A', '9')->setIfMissing('A', 'x')->setIfMissing('C', '3')->forgetMany(['B']);

    expect($fake->all())->toBe(['A' => '9', 'C' => '3']);
});

it('the fake records setExport with the exact boolean flag', function () {
    $fake = EnvKit::fake(['A' => '1']);

    $fake->setExport('A', false);
    expect(collect($fake->recorded)->firstWhere('action', 'setExport')['value'])->toBe('0');

    $fake->setExport('A', true);
    expect(collect($fake->recorded)->where('action', 'setExport')->last()['value'])->toBe('1');
});

it('the fake generate matches each requested shape', function () {
    $fake = EnvKit::fake();

    expect($fake->generate('app_key'))->toStartWith('base64:')
        ->and($fake->generate('base64'))->not->toContain('=')
        ->and($fake->generate('hex', ['bytes' => 4]))->toHaveLength(8)
        ->and($fake->generate())->toHaveLength(64);
});

it('the fake backup records the label and returns null when empty', function () {
    $fake = EnvKit::fake(['A' => '1']);

    expect($fake->backup('snap')->name)->toBe('snap')
        ->and(collect($fake->recorded)->firstWhere('action', 'backup')['key'])->toBe('snap')
        ->and(EnvKit::fake()->backup())->toBeNull(); // empty map → nothing to back up
});

it('the fake schema validates and assertValid is chainable on success', function () {
    $fake = EnvKit::fake(['PORT' => '8080']);
    $fake->schema()->integer('PORT');

    expect($fake->isValid())->toBeTrue()
        ->and($fake->assertValid())->toBe($fake);   // returns itself on success
});
