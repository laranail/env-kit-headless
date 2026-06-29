<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\FileNotWritableException;
use Simtabi\Laranail\EnvKit\Headless\Writer\AtomicEnvWriter;
use Simtabi\Laranail\EnvKit\Headless\Writer\IntegrityVerifier;

it('writes exact bytes and creates the file', function () {
    $path = envkit_temp();

    (new AtomicEnvWriter)->write($path, "A=1\nB=2\n");

    expect(file_get_contents($path))->toBe("A=1\nB=2\n");
});

it('replaces existing content and leaves no temp files behind', function () {
    $path = envkit_temp();
    file_put_contents($path, "OLD=1\n");

    (new AtomicEnvWriter)->write($path, "NEW=2\n");

    expect(file_get_contents($path))->toBe("NEW=2\n");

    $leftovers = array_filter(
        scandir(dirname($path)) ?: [],
        static fn (string $f): bool => str_starts_with($f, '.env-kit-'),
    );
    expect($leftovers)->toBeEmpty();
});

it('preserves the mode of an existing file (never widens)', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");
    chmod($path, 0600);

    (new AtomicEnvWriter)->write($path, "A=2\n");

    expect(substr(sprintf('%o', fileperms($path)), -4))->toBe('0600');
});

it('creates the new file in the target directory with mode 0644', function () {
    $path = envkit_temp();
    $before = scandir(dirname($path));

    (new AtomicEnvWriter)->write($path, "A=1\n");

    // Exactly one new entry — the target itself — appeared in the target dir,
    // so the write landed in-place (no stray temp file, no other directory).
    $after = array_values(array_diff(scandir(dirname($path)), $before));

    expect(is_file($path))->toBeTrue()
        ->and($after)->toBe([basename($path)])
        ->and(substr(sprintf('%o', fileperms($path)), -4))->toBe('0644');
});

it('preserves an existing mode that carries an execute bit (never widens)', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");
    chmod($path, 0755);

    (new AtomicEnvWriter)->write($path, "A=2\n");

    expect(substr(sprintf('%o', fileperms($path)), -4))->toBe('0755');
});

it('throws when the target directory is not writable', function () {
    (new AtomicEnvWriter)->write('/this/dir/does/not/exist/.env', "A=1\n");
})->throws(FileNotWritableException::class);

it('throws when the target parent is a regular file, not a directory', function () {
    $parent = envkit_temp(); // a real, writable file path
    file_put_contents($parent, 'x');

    (new AtomicEnvWriter)->write($parent.'/.env', "A=1\n");
})->throws(FileNotWritableException::class);

it('verifies a file that matches the expected document', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\nB=2\n");

    $verified = (new IntegrityVerifier)->verify($path, EnvDocument::parse("A=1\nB=2\n"));

    expect($verified)->toBeTrue();
});

it('fails verification when the file does not match the expected document', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\nB=999\n");

    $verified = (new IntegrityVerifier)->verify($path, EnvDocument::parse("A=1\nB=2\n"));

    expect($verified)->toBeFalse();
});

it('fails verification when the file is missing', function () {
    $path = envkit_temp(); // not created

    $verified = (new IntegrityVerifier)->verify($path, EnvDocument::parse("A=1\n"));

    expect($verified)->toBeFalse();
});
