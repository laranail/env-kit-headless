<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Schema\EnvSchema;

function fullSchema(): EnvSchema
{
    return (new EnvSchema)
        ->integer('I')->boolean('B')->number('N')->url('U')->email('E')->regex('R', '/^x+$/')->string('S');
}

it('type rules pass for ABSENT values (only required enforces presence)', function () {
    expect(fullSchema()->validate([])->passed())->toBeTrue();
});

it('type rules pass for EMPTY values (empty is a valid at-rest string)', function () {
    $result = fullSchema()->validate(['I' => '', 'B' => '', 'N' => '', 'U' => '', 'E' => '', 'R' => '', 'S' => '']);

    expect($result->passed())->toBeTrue();
});

it('type rules pass for VALID present values', function () {
    $result = fullSchema()->validate([
        'I' => '-42', 'B' => 'true', 'N' => '3.14', 'U' => 'https://x.test', 'E' => 'a@b.co', 'R' => 'xxx', 'S' => 'anything',
    ]);

    expect($result->passed())->toBeTrue();
});

it('each type rule fails for an INVALID present value', function () {
    expect((new EnvSchema)->integer('K')->validate(['K' => '1.5'])->failed())->toBeTrue()
        ->and((new EnvSchema)->boolean('K')->validate(['K' => 'maybe'])->failed())->toBeTrue()
        ->and((new EnvSchema)->number('K')->validate(['K' => 'NaNx'])->failed())->toBeTrue()
        ->and((new EnvSchema)->url('K')->validate(['K' => 'not a url'])->failed())->toBeTrue()
        ->and((new EnvSchema)->email('K')->validate(['K' => 'no-at'])->failed())->toBeTrue()
        ->and((new EnvSchema)->regex('K', '/^x+$/')->validate(['K' => 'y'])->failed())->toBeTrue();
});

it('required fails for empty and absent, passes when present', function () {
    $schema = (new EnvSchema)->required('K');

    expect($schema->validate(['K' => ''])->failed())->toBeTrue()
        ->and($schema->validate([])->failed())->toBeTrue()
        ->and($schema->validate(['K' => 'v'])->passed())->toBeTrue();
});

it('reports the FIRST failure per key with the exact message and a single entry', function () {
    $errors = (new EnvSchema)->in('ENV', ['a', 'b'])->validate(['ENV' => 'z'])->errors();

    expect($errors['ENV'])->toBe(['must be one of: a, b']);
});

it('messages() flattens key + message', function () {
    $messages = (new EnvSchema)->integer('PORT')->validate(['PORT' => 'x'])->messages();

    expect($messages)->toBe(['PORT must be an integer']);
});

it('define parses pipe specs, uppercase names, colons in args, and trims list items', function () {
    $schema = (new EnvSchema)->define('K', 'REQUIRED|IN: a , b ')->define('R', 'regex:/^a:b$/');

    expect($schema->validate(['K' => 'a', 'R' => 'a:b'])->passed())->toBeTrue()  // 'a' ∈ trimmed [a,b]; colon kept in regex
        ->and($schema->validate(['K' => 'c', 'R' => 'a:b'])->failed())->toBeTrue()  // 'c' ∉ set
        ->and($schema->validate(['K' => 'a', 'R' => 'zzz'])->failed())->toBeTrue(); // regex mismatch
});

it('define accepts an array of specs and ignores blanks/unknowns', function () {
    $schema = (new EnvSchema)->define('K', ['integer', '', 'totally-unknown-rule']);

    expect($schema->validate(['K' => '7'])->passed())->toBeTrue()
        ->and($schema->validate(['K' => 'seven'])->failed())->toBeTrue();
});

it('stops at the first failing rule for a key (break works)', function () {
    // integer runs first and fails; the in() rule must NOT also report.
    $errors = (new EnvSchema)->integer('K')->in('K', ['a'])->validate(['K' => 'zzz'])->errors();

    expect($errors['K'])->toBe(['must be an integer']);
});

it('keys() lists every keyed rule', function () {
    $schema = (new EnvSchema)->required('A')->integer('B')->integer('B');

    expect($schema->keys())->toBe(['A', 'B']); // de-duplicated by array key
});
