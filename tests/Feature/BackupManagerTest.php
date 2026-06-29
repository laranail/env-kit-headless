<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\FileNotWritableException;

it('creates a timestamped backup of an existing file', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");

    $backup = (new BackupManager(dirname($path).'/backups'))->backup($path);

    expect($backup)->not->toBeNull()
        ->and(is_file($backup->path))->toBeTrue()
        ->and(file_get_contents($backup->path))->toBe("A=1\n")
        ->and($backup->name)->toEndWith('.bak');
});

it('returns null when there is nothing to back up', function () {
    $path = envkit_temp(); // file intentionally not created

    expect((new BackupManager(dirname($path).'/backups'))->backup($path))->toBeNull();
});

it('lists newest-first and prunes beyond the retention count', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");
    $manager = new BackupManager(dirname($path).'/backups', retain: 2);

    for ($i = 0; $i < 4; $i++) {
        $manager->backup($path);
        usleep(2000);
    }

    expect($manager->all())->toHaveCount(2)
        ->and($manager->latest())->not->toBeNull();
});

it('records the real byte size on the returned backup', function () {
    $path = envkit_temp();
    file_put_contents($path, "HELLO\n"); // 6 bytes

    $backup = (new BackupManager(dirname($path).'/backups'))->backup($path);

    expect($backup->size)->toBe(6);
});

it('records a zero size when backing up an empty file', function () {
    $path = envkit_temp();
    file_put_contents($path, '');

    $backup = (new BackupManager(dirname($path).'/backups'))->backup($path);

    expect($backup->size)->toBe(0);
});

it('reports the real size and timestamp when listing backups', function () {
    $path = envkit_temp();
    file_put_contents($path, "HELLO\n"); // 6 bytes
    $manager = new BackupManager(dirname($path).'/backups');
    $manager->backup($path);

    $listed = $manager->latest();

    expect($listed->size)->toBe(6)
        ->and($listed->timestamp)->toBe(filemtime($listed->path))
        ->and($listed->timestamp)->toBeGreaterThan(0);
});

it('reports a zero size for an empty backup when listing', function () {
    $path = envkit_temp();
    file_put_contents($path, '');
    $manager = new BackupManager(dirname($path).'/backups');
    $manager->backup($path);

    expect($manager->all()[0]->size)->toBe(0);
});

it('orders listed backups newest name first', function () {
    $dir = dirname(envkit_temp()).'/backups';
    mkdir($dir, 0o777, true);
    file_put_contents($dir.'/env.20260101-000000-000000-aaaa.bak', '1');
    file_put_contents($dir.'/env.20260102-000000-000000-bbbb.bak', '22');
    file_put_contents($dir.'/env.20260103-000000-000000-cccc.bak', '333');

    $all = (new BackupManager($dir))->all();

    expect($all)->toHaveCount(3)
        ->and($all[0]->name)->toBe('env.20260103-000000-000000-cccc.bak')
        ->and($all[1]->name)->toBe('env.20260102-000000-000000-bbbb.bak')
        ->and($all[2]->name)->toBe('env.20260101-000000-000000-aaaa.bak');
});

it('keeps exactly the retained count when pruning down to one', function () {
    $dir = dirname(envkit_temp()).'/backups';
    mkdir($dir, 0o777, true);
    file_put_contents($dir.'/env.20260101-000000-000000-aaaa.bak', '1');
    file_put_contents($dir.'/env.20260102-000000-000000-bbbb.bak', '2');
    file_put_contents($dir.'/env.20260103-000000-000000-cccc.bak', '3');

    $manager = new BackupManager($dir, retain: 1);
    $manager->prune();

    expect($manager->all())->toHaveCount(1)
        ->and($manager->all()[0]->name)->toBe('env.20260103-000000-000000-cccc.bak');
});

it('throws with the directory path when the backup directory cannot be created', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");
    $blocker = dirname($path).'/blocker';
    file_put_contents($blocker, 'x'); // a regular file blocks mkdir beneath it
    $badDir = $blocker.'/nested/backups';

    $message = null;
    set_error_handler(static fn (): bool => true); // swallow the expected mkdir() warning
    try {
        (new BackupManager($badDir))->backup($path);
    } catch (FileNotWritableException $e) {
        $message = $e->getMessage();
    } finally {
        restore_error_handler();
    }

    expect($message)->toBe("Path is not writable: {$badDir}");
});

it('creates the backup directory recursively across missing levels', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");
    $nested = dirname($path).'/a/b/c/backups'; // none of these levels exist yet

    $backup = (new BackupManager($nested))->backup($path);

    expect($backup)->not->toBeNull()
        ->and(is_dir($nested))->toBeTrue()
        ->and(is_file($backup->path))->toBeTrue();
});

it('creates the backup directory with 0755 permissions (umask applied)', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");
    $dir = dirname($path).'/backups';

    (new BackupManager($dir))->backup($path);

    // mkdir() applies the process umask, so the on-disk bits are 0755 & ~umask.
    expect(fileperms($dir) & 0o777)->toBe(0o755 & ~umask());
});

it('derives the backup base name from the source file name', function () {
    $dir = dirname(envkit_temp());
    $env = $dir.'/.env.production';
    file_put_contents($env, "A=1\n");

    $backup = (new BackupManager($dir.'/backups'))->backup($env);

    expect($backup->name)->toStartWith('env.production.');
});

it('suffixes the backup name with a four-character hex token', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");

    $backup = (new BackupManager(dirname($path).'/backups'))->backup($path);

    expect($backup->name)->toMatch('/-[0-9a-f]{4}\.bak$/');
});

it('embeds a sub-second component in the backup name', function () {
    $path = envkit_temp();
    file_put_contents($path, "A=1\n");
    $manager = new BackupManager(dirname($path).'/backups');

    $micros = [];
    for ($i = 0; $i < 12; $i++) {
        $backup = $manager->backup($path);
        preg_match('/-(\d+)-[0-9a-f]+\.bak$/', $backup->name, $m);
        $micros[] = $m[1];
        usleep(1500);
    }

    // The micros field is the fractional second scaled to [0, 999999] and is
    // always a 6-digit, sub-million value...
    foreach ($micros as $value) {
        expect($value)->toMatch('/^\d{6}$/')
            ->and((int) $value)->toBeLessThan(1_000_000);
    }

    // ...and across many rapid backups it is overwhelmingly never all-zero.
    expect(array_filter($micros, static fn (string $v): bool => $v !== '000000'))
        ->not->toBeEmpty();
});
