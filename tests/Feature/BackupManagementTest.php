<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('creates a labelled backup', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    $backup = EnvKit::backup('pre-deploy');

    expect($backup)->not->toBeNull()
        ->and($backup->name)->toContain('pre-deploy');
});

it('deletes a backup by name', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    $backup = EnvKit::backup();

    expect(EnvKit::backups()->delete($backup->name))->toBeTrue()
        ->and(EnvKit::backups()->find($backup->name))->toBeNull()
        ->and(EnvKit::backups()->delete('does-not-exist.bak'))->toBeFalse();
});

it('deletes backups older than N days', function () {
    $dir = sys_get_temp_dir().'/envkit-old-'.bin2hex(random_bytes(5));
    @mkdir($dir, 0700, true);
    $old = $dir.'/env.20200101-000000-000000-aaaa.bak';
    $recent = $dir.'/env.20260101-000000-000000-bbbb.bak';
    file_put_contents($old, "A=1\n");
    file_put_contents($recent, "A=1\n");
    touch($old, time() - 40 * 86400);

    $manager = new BackupManager($dir);

    expect($manager->deleteOlderThan(30))->toBe(1)
        ->and(is_file($old))->toBeFalse()
        ->and(is_file($recent))->toBeTrue();

    array_map('unlink', glob($dir.'/*') ?: []);
    @rmdir($dir);
});

it('the backup-delete command deletes by name, prunes by age, and errors without args', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    $backup = EnvKit::backup();

    $this->artisan('env:backup-delete', ['name' => $backup->name])
        ->expectsOutputToContain('Deleted')
        ->assertExitCode(0);

    $this->artisan('env:backup-delete')->assertExitCode(2);                 // usage error
    $this->artisan('env:backup-delete', ['--older-than' => 30])->assertExitCode(0);
});

it('the fake labels its stub backup', function () {
    $fake = EnvKit::fake(['A' => '1']);

    expect($fake->backup('snap')->name)->toBe('snap');
});
