<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Exceptions\PortException;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Porter\Formats\DotenvFormat;
use Simtabi\Laranail\EnvKit\Headless\Porter\Formats\YamlFormat;
use Simtabi\Laranail\EnvKit\Headless\Porter\Porter;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('registers dotenv and yaml in the default porter', function () {
    expect(Porter::withDefaults()->names())->toContain('json')
        ->toContain('csv')
        ->toContain('dotenv')
        ->toContain('yaml');
});

it('round-trips the dotenv format including quoting and empties', function () {
    $format = new DotenvFormat;
    $values = ['A' => '1', 'B' => 'has space', 'C' => ''];

    $exported = $format->export($values);

    expect($exported)->toContain('A=1')
        ->toContain('B="has space"')
        ->and($format->import($exported))->toBe($values);
});

it('round-trips the yaml format', function () {
    $format = new YamlFormat;
    $values = ['A' => '1', 'B' => 'two words'];

    expect($format->import($format->export($values)))->toBe($values);
});

it('yaml import rejects malformed content', function () {
    expect(fn () => (new YamlFormat)->import('foo: [unclosed'))->toThrow(PortException::class);
});

it('EnvKit::export/import work with dotenv and yaml', function () {
    $this->bindEnv("A=1\nB=2\n", ['env-kit.auto_backup' => false]);

    expect(EnvKit::export('yaml'))->toContain('A:');

    EnvKit::import("C: 3\nD: 4\n", 'yaml');
    expect(EnvKit::get('C'))->toBe('3')->and(EnvKit::get('D'))->toBe('4');

    EnvKit::import("E=5\n", 'dotenv');
    expect(EnvKit::get('E'))->toBe('5');
});
