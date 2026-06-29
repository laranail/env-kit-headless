<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Exceptions\SchemaException;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Rules\MatchesEnvSchema;
use Simtabi\Laranail\EnvKit\Headless\Schema\EnvSchema;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('validates against a runtime schema', function () {
    $this->bindEnv("APP_ENV=local\nPORT=8080\n", ['env-kit.auto_backup' => false]);

    EnvKit::schema()
        ->required('APP_ENV')->in('APP_ENV', ['local', 'production'])
        ->integer('PORT')
        ->required('MISSING_ONE');

    $result = EnvKit::validate();

    expect($result->failed())->toBeTrue()
        ->and($result->errors())->toHaveKey('MISSING_ONE')
        ->and(EnvKit::isValid())->toBeFalse();
});

it('passes a valid env and assertValid does not throw', function () {
    $this->bindEnv("APP_ENV=production\nPORT=80\n", ['env-kit.auto_backup' => false]);

    EnvKit::schema()->required('APP_ENV')->in('APP_ENV', ['local', 'production'])->integer('PORT');

    expect(EnvKit::isValid())->toBeTrue();
    EnvKit::assertValid(); // no throw
});

it('assertValid throws on failure', function () {
    $this->bindEnv("PORT=notanint\n", ['env-kit.auto_backup' => false]);
    EnvKit::schema()->integer('PORT');

    expect(fn () => EnvKit::assertValid())->toThrow(SchemaException::class);
});

it('seeds the schema from config', function () {
    $this->bindEnv("APP_ENV=invalid\n", [
        'env-kit.auto_backup' => false,
        'env-kit.schema' => ['APP_ENV' => 'required|in:local,production'],
    ]);

    expect(EnvKit::isValid())->toBeFalse()
        ->and(EnvKit::validate()->errors())->toHaveKey('APP_ENV');
});

it('the env:validate command reports schema errors and exits 3', function () {
    $this->bindEnv("PORT=oops\n", [
        'env-kit.auto_backup' => false,
        'env-kit.schema' => ['PORT' => 'integer'],
    ]);

    $this->artisan('env:validate')->expectsOutputToContain('Schema')->assertExitCode(3);
});

it('the schema builder covers every rule type', function () {
    $schema = (new EnvSchema)
        ->boolean('B')->number('N')->url('U')->email('E')->regex('R', '/^x+$/')->string('S');

    $result = $schema->validate([
        'B' => 'maybe', 'N' => 'notnum', 'U' => 'noturl', 'E' => 'noat', 'R' => 'yyy', 'S' => 'anything',
    ]);

    expect($result->errors())->toHaveKeys(['B', 'N', 'U', 'E', 'R'])
        ->and($result->errors())->not->toHaveKey('S')
        ->and($result->messages())->not->toBeEmpty();
});

it('MatchesEnvSchema validates a single value for a FormRequest', function () {
    $rule = new MatchesEnvSchema((new EnvSchema)->integer('PORT'), 'PORT');

    $failures = [];
    $rule->validate('port', 'abc', function (string $m) use (&$failures): void {
        $failures[] = $m;
    });
    expect($failures)->not->toBeEmpty();

    $clean = [];
    $rule->validate('port', '8080', function (string $m) use (&$clean): void {
        $clean[] = $m;
    });
    expect($clean)->toBeEmpty();
});

it('the fake validates against its own schema', function () {
    $fake = EnvKit::fake(['PORT' => 'nope']);
    $fake->schema()->integer('PORT');

    expect($fake->isValid())->toBeFalse()
        ->and(fn () => $fake->assertValid())->toThrow(SchemaException::class);
});
