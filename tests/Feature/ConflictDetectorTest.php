<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Exceptions\ConflictException;
use Simtabi\Laranail\EnvKit\Headless\Session\ConflictDetector;

if (! function_exists('envkit_is_root')) {
    /** True when the process can read files it has chmod-ed 0000 (i.e. running as root). */
    function envkit_is_root(): bool
    {
        return function_exists('posix_getuid') ? posix_getuid() === 0 : false;
    }
}

it('reports a stable sha256 fingerprint for identical content', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\nB=2\n");

    $detector = new ConflictDetector;
    $first = $detector->fingerprint($path);

    expect($first)
        ->toBe($detector->fingerprint($path))
        ->toBe(hash('sha256', "A=1\nB=2\n"));
});

it('produces a different fingerprint after the file content changes', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");

    $detector = new ConflictDetector;
    $before = $detector->fingerprint($path);

    file_put_contents($path, "A=2\n");

    expect($detector->fingerprint($path))->not->toBe($before);
});

it('fingerprints a missing file as "absent"', function () {
    $path = envkit_temp(); // never created

    expect((new ConflictDetector)->fingerprint($path))->toBe('absent');
});

it('falls back to an mtime fingerprint when the file cannot be hashed', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");
    chmod($path, 0000);

    // The src already silences the failed hash with `@`, but PHPUnit's error
    // handler (failOnWarning) ignores `@`; swallow only this expected warning.
    set_error_handler(static fn (): bool => true);
    try {
        $fingerprint = (new ConflictDetector)->fingerprint($path);
    } finally {
        restore_error_handler();
    }

    expect($fingerprint)->toBe('mtime:'.((string) filemtime($path)));
})->skip(envkit_is_root(), 'unreadable-file behaviour cannot be exercised as root');

it('passes ensureUnchanged when the file still matches the fingerprint', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");

    $detector = new ConflictDetector;
    $expected = $detector->fingerprint($path);

    $detector->ensureUnchanged($path, $expected);
})->throwsNoExceptions();

it('throws ConflictException when the file changed since the fingerprint', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");

    $detector = new ConflictDetector;
    $expected = $detector->fingerprint($path);

    file_put_contents($path, "A=2\n");

    $detector->ensureUnchanged($path, $expected);
})->throws(ConflictException::class);
