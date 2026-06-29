<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Exceptions\BackupNotFoundException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ConflictException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\EncryptionException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\EnvKitException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\FileNotWritableException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\IntegrityException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\InvalidKeyException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\InvalidValueException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\KeyNotFoundException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\LockException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\PortException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ProductionGuardException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ProtectedKeyException;

it('BackupNotFoundException::named is typed and names the backup', function () {
    $e = BackupNotFoundException::named('nightly-2026');

    expect($e)->toBeInstanceOf(BackupNotFoundException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('No backup named [nightly-2026] was found.');
});

it('ConflictException::for is typed and surfaces the path', function () {
    $e = ConflictException::for('/var/app/.env');

    expect($e)->toBeInstanceOf(ConflictException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('The .env file changed on disk since it was loaded: /var/app/.env');
});

it('EncryptionException::notConfigured is typed and explains the missing cipher', function () {
    $e = EncryptionException::notConfigured();

    expect($e)->toBeInstanceOf(EncryptionException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('No cipher configured. Set an APP_KEY, or register one via configure()->useCipher().');
});

it('EncryptionException::corrupt is typed and reports a bad payload', function () {
    $e = EncryptionException::corrupt();

    expect($e)->toBeInstanceOf(EncryptionException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Failed to decrypt value: unexpected payload.');
});

it('FileNotWritableException::for is typed and surfaces the path', function () {
    $e = FileNotWritableException::for('/etc/.env');

    expect($e)->toBeInstanceOf(FileNotWritableException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Path is not writable: /etc/.env');
});

it('IntegrityException::for is typed and reports the rollback', function () {
    $e = IntegrityException::for('/var/app/.env');

    expect($e)->toBeInstanceOf(IntegrityException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Post-write integrity check failed; rolled back: /var/app/.env');
});

it('InvalidKeyException::for is typed and names the offending key', function () {
    $e = InvalidKeyException::for('1BAD-KEY');

    expect($e)->toBeInstanceOf(InvalidKeyException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Invalid environment key: 1BAD-KEY. Keys must match /^[A-Za-z_][A-Za-z0-9_]*$/.');
});

it('InvalidValueException::nulByte names the key when one is given, never the value', function () {
    $e = InvalidValueException::nulByte('API_TOKEN');

    expect($e)->toBeInstanceOf(InvalidValueException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Value contains a NUL byte (key API_TOKEN).')
        ->and($e->getMessage())->toContain('(key API_TOKEN)');
});

it('InvalidValueException::nulByte omits the key suffix when none is given', function () {
    $e = InvalidValueException::nulByte();

    expect($e)->toBeInstanceOf(InvalidValueException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Value contains a NUL byte.')
        ->and($e->getMessage())->not->toContain('(key')
        ->and($e->getMessage())->not->toContain('null');
});

it('InvalidValueException::nulByte is identical whether null is explicit or defaulted', function () {
    expect(InvalidValueException::nulByte(null)->getMessage())
        ->toBe(InvalidValueException::nulByte()->getMessage());
});

it('KeyNotFoundException::for is typed and names the missing key', function () {
    $e = KeyNotFoundException::for('MISSING_KEY');

    expect($e)->toBeInstanceOf(KeyNotFoundException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Environment key not found: MISSING_KEY');
});

it('LockException::for is typed and surfaces the path', function () {
    $e = LockException::for('/var/app/.env');

    expect($e)->toBeInstanceOf(LockException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Could not acquire an exclusive lock for: /var/app/.env');
});

it('PortException::unknownFormat is typed and names the format', function () {
    $e = PortException::unknownFormat('dotenv-vault');

    expect($e)->toBeInstanceOf(PortException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Unknown port format [dotenv-vault].');
});

it('PortException::malformed is typed and names the format', function () {
    $e = PortException::malformed('json');

    expect($e)->toBeInstanceOf(PortException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Malformed json import payload.');
});

it('ProductionGuardException::make is typed and lists every opt-in route', function () {
    $e = ProductionGuardException::make();

    expect($e)->toBeInstanceOf(ProductionGuardException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe(
            'Refusing to modify the .env file in production. '
            .'Opt in per-call with ->allowProduction(), the --force-production flag, '
            .'or set protect_production=false.'
        );
});

it('ProtectedKeyException::for is typed and names the protected key', function () {
    $e = ProtectedKeyException::for('APP_KEY');

    expect($e)->toBeInstanceOf(ProtectedKeyException::class)
        ->and($e)->toBeInstanceOf(EnvKitException::class)
        ->and($e->getMessage())->toBe('Refusing to modify protected key: APP_KEY.');
});
