<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Contracts\WriterInterface;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\BackupNotFoundException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ProductionGuardException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ProtectedKeyException;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('reads via the facade, DI, and the env_kit() helper', function () {
    $this->bindEnv("APP_NAME=Acme\nDEBUG=true\nPORT=8080\n");

    // facade + typed getters
    expect(EnvKit::get('APP_NAME'))->toBe('Acme')
        ->and(EnvKit::getBool('DEBUG'))->toBeTrue()
        ->and(EnvKit::getInt('PORT'))->toBe(8080)
        ->and(EnvKit::get('MISSING', 'def'))->toBe('def');

    // constructor DI of the contract
    expect(app(EnvKitInterface::class)->getString('APP_NAME'))->toBe('Acme');

    // helper (read shortcut + accessor)
    expect(env_kit('APP_NAME'))->toBe('Acme')
        ->and(env_kit('MISSING', 'fallback'))->toBe('fallback')
        ->and(env_kit())->toBeInstanceOf(EnvKitInterface::class);
});

it('writes immediately under auto_commit', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::set('B', 'two');

    expect(EnvKit::get('B'))->toBe('two')
        ->and(file_get_contents($path))->toContain('B=two');
});

it('commits a batch as one transaction', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::transaction(function ($session) {
        $session->set('B', '2')->set('C', '3');
    });

    expect(EnvKit::get('B'))->toBe('2')
        ->and(EnvKit::get('C'))->toBe('3');
});

it('supports group / only / except / interpolated reads', function () {
    $this->bindEnv(
        "MAIL_HOST=smtp\nMAIL_PORT=587\nAPP_NAME=Acme\n".'URL=${MAIL_HOST}:${MAIL_PORT}'."\n"
    );

    expect(EnvKit::group('MAIL'))->toBe(['MAIL_HOST' => 'smtp', 'MAIL_PORT' => '587'])
        ->and(EnvKit::only(['APP_NAME']))->toBe(['APP_NAME' => 'Acme'])
        ->and(EnvKit::except(['MAIL_HOST', 'MAIL_PORT', 'URL']))->toBe(['APP_NAME' => 'Acme'])
        ->and(EnvKit::interpolated('URL'))->toBe('smtp:587');
});

it('takes an auto-backup before an immediate write', function () {
    $path = $this->bindEnv("A=1\n"); // auto_backup defaults to true

    EnvKit::set('A', '2');

    expect(glob(dirname($path).'/backups/*.bak'))->toHaveCount(1)
        ->and(EnvKit::get('A'))->toBe('2');
});

it('group() matches the exact "PREFIX_" boundary, not a bare prefix', function () {
    // MAILBOX shares the "MAIL" prefix but is NOT in the MAIL_ group; the needle
    // is rtrim($prefix, '_').'_', so it must equal "MAIL_" for both call shapes.
    $this->bindEnv("MAIL_HOST=smtp\nMAIL_PORT=587\nMAILBOX=inbox\nMAIN_DB=x\n");

    expect(EnvKit::group('MAIL'))->toBe(['MAIL_HOST' => 'smtp', 'MAIL_PORT' => '587'])
        ->and(EnvKit::group('MAIL_'))->toBe(['MAIL_HOST' => 'smtp', 'MAIL_PORT' => '587']);
});

it('writes an export-prefixed line when the export option is set', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::set('B', 'two', ['export' => true]);

    expect((string) file_get_contents($path))->toContain('export B=two');
});

it('accumulates staged writes in one pending session across manual save', function () {
    // With auto_commit off, each set() must reuse the SAME pending session
    // (?? = newSession), so save() persists every staged pair, not just the last.
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_commit' => false, 'env-kit.auto_backup' => false]);

    EnvKit::set('B', '2');
    EnvKit::set('C', '3');
    EnvKit::save();

    $doc = EnvDocument::parse((string) file_get_contents($path));

    expect($doc->toArray())->toBe(['A' => '1', 'B' => '2', 'C' => '3']);
});

it('enforces config-level protected keys against writes', function () {
    // protected_keys flows into the constructor list, which must be merged into
    // the pipeline's ProtectedKeys for the write to be refused.
    $this->bindEnv("A=1\n", [
        'env-kit.auto_backup' => false,
        'env-kit.protected_keys' => ['APP_KEY'],
    ]);

    expect(fn () => EnvKit::set('APP_KEY', 'leaked'))
        ->toThrow(ProtectedKeyException::class);
});

it('encrypt() / decrypt() on a missing key are no-ops', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::encrypt('NOPE');
    EnvKit::decrypt('NOPE');

    expect(EnvKit::has('NOPE'))->toBeFalse()
        ->and((string) file_get_contents($path))->toContain('A=1');
});

it('transaction() honours allowProduction() and consumes the override', function () {
    $this->app['env'] = 'production';
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    // a production write is blocked without opting in
    expect(fn () => EnvKit::transaction(fn ($s) => $s->set('B', '2')))
        ->toThrow(ProductionGuardException::class);

    // opting in lets the batch through
    EnvKit::allowProduction()->transaction(fn ($s) => $s->set('B', '2'));
    expect(EnvKit::get('B'))->toBe('2');

    // the override is single-use: the next transaction is blocked again
    expect(fn () => EnvKit::transaction(fn ($s) => $s->set('C', '3')))
        ->toThrow(ProductionGuardException::class);
    expect(EnvKit::get('C'))->toBeNull();
});

it('manual save() honours allowProduction() and consumes the override', function () {
    $this->app['env'] = 'production';
    $this->bindEnv("A=1\n", ['env-kit.auto_commit' => false, 'env-kit.auto_backup' => false]);

    // staged then committed without opting in → blocked
    EnvKit::set('B', '2');
    expect(fn () => EnvKit::save())->toThrow(ProductionGuardException::class);

    // opting in commits the staged batch
    EnvKit::allowProduction()->save();
    expect(EnvKit::get('B'))->toBe('2');

    // override consumed → a fresh staged write is blocked again
    EnvKit::set('C', '3');
    expect(fn () => EnvKit::save())->toThrow(ProductionGuardException::class);
    expect(EnvKit::get('C'))->toBeNull();
});

it('restore() is production-guarded and resets the override afterwards', function () {
    $this->app['env'] = 'production';
    $path = $this->bindEnv("A=1\n");

    $backup = EnvKit::backup();             // snapshot is allowed in production
    file_put_contents($path, "A=2\n");

    // a restore is a write: blocked without opting in
    expect(fn () => EnvKit::restore($backup->name))
        ->toThrow(ProductionGuardException::class);

    // opting in lets it through and rewrites the file
    EnvKit::allowProduction()->restore($backup->name);
    expect((string) file_get_contents($path))->toContain('A=1');

    // override consumed → a subsequent write is blocked again
    expect(fn () => EnvKit::set('A', '9'))->toThrow(ProductionGuardException::class);
});

it('restore() takes a safety backup before overwriting when auto_backup is on', function () {
    $path = $this->bindEnv("A=1\n"); // auto_backup defaults to true

    $backup = EnvKit::backup();                                  // 1 backup
    file_put_contents($path, "A=2\n");                           // change out-of-band
    $before = count(glob(dirname($path).'/backups/*.bak') ?: []);

    EnvKit::restore($backup->name);

    expect(count(glob(dirname($path).'/backups/*.bak') ?: []))->toBe($before + 1)
        ->and((string) file_get_contents($path))->toContain('A=1');
});

it('restore() skips the safety backup when auto_backup is off', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    $backup = EnvKit::backup();        // backup() ignores auto_backup → 1 file
    file_put_contents($path, "A=2\n");

    EnvKit::restore($backup->name);

    // no extra safety backup is taken (would be 2 under autoBackup||is_file)
    expect(glob(dirname($path).'/backups/*.bak'))->toHaveCount(1)
        ->and((string) file_get_contents($path))->toContain('A=1');
});

it('restore() rewrites through the configured custom writer', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    $backup = EnvKit::backup();        // snapshot A=1
    file_put_contents($path, "A=2\n");

    EnvKit::configure()->useWriter(new class implements WriterInterface
    {
        public function write(string $path, string $contents): void
        {
            file_put_contents($path, $contents."\n# WRITTEN_BY_CUSTOM_WRITER\n");
        }
    });

    EnvKit::restore($backup->name);

    expect((string) file_get_contents($path))
        ->toContain('A=1')
        ->toContain('# WRITTEN_BY_CUSTOM_WRITER');
});

it('restore() fails when the backup file is unreadable', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    $backup = EnvKit::backup();
    chmod($backup->path, 0o000);

    try {
        expect(fn () => EnvKit::restore($backup->name))
            ->toThrow(BackupNotFoundException::class);
    } finally {
        chmod($backup->path, 0o644);
    }
});
