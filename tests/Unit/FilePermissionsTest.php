<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Writer\AtomicEnvWriter;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/envkit-perms-'.bin2hex(random_bytes(5));
    @mkdir($this->dir, 0777, true);
});

afterEach(function () {
    $remove = function (string $dir) use (&$remove): void {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
            $path = $dir.'/'.$entry;
            is_dir($path) ? $remove($path) : unlink($path);
        }
        rmdir($dir);
    };

    if (is_dir($this->dir)) {
        $remove($this->dir);
    }
});

it('writes a new .env owner-only (0600)', function () {
    $path = $this->dir.'/.env';
    (new AtomicEnvWriter)->write($path, "A=1\n");

    expect(fileperms($path) & 0777)->toBe(0600);
});

it('preserves an existing file mode (never widens)', function () {
    $path = $this->dir.'/.env';
    file_put_contents($path, "A=1\n");
    chmod($path, 0640);

    (new AtomicEnvWriter)->write($path, "A=2\n");

    expect(fileperms($path) & 0777)->toBe(0640);
});

it('writes backups owner-only (0600) into a 0700 dir', function () {
    $path = $this->dir.'/.env';
    file_put_contents($path, "A=1\n");

    $backupDir = $this->dir.'/backups';
    $backup = (new BackupManager($backupDir))->backup($path);

    expect($backup)->not->toBeNull()
        ->and(fileperms($backup->path) & 0777)->toBe(0600)
        ->and(fileperms($backupDir) & 0777)->toBe(0700);
});
