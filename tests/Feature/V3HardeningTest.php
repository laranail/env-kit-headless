<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Support\SecretGenerator;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('SecretGenerator produces correctly-sized tokens and keys', function () {
    $gen = new SecretGenerator;

    expect($gen->token(8, 'hex'))->toHaveLength(16)          // 8 bytes → 16 hex chars
        ->and($gen->token(16, 'hex'))->toHaveLength(32)
        ->and($gen->token(8, 'base64'))->not->toContain('=') // url-safe, unpadded
        ->and($gen->token(8, 'base64'))->not->toContain('+')
        ->and(strlen((string) base64_decode(substr($gen->appKey(), 7))))->toBe(32)               // default 32-byte key
        ->and(strlen((string) base64_decode(substr($gen->appKey('AES-128-CBC'), 7))))->toBe(16)  // 128 → 16 bytes
        ->and($gen->appKey())->toStartWith('base64:');
});

it('a labelled backup tags the file name; empty labels do not', function () {
    $dir = sys_get_temp_dir().'/envkit-lbl-'.bin2hex(random_bytes(5));
    @mkdir($dir, 0700, true);
    $env = $dir.'/.env';
    file_put_contents($env, "A=1\n");
    $manager = new BackupManager($dir.'/backups');

    expect($manager->backup($env, 'pre deploy!')->name)->toContain('pre-deploy-') // sanitized, hyphenated
        ->and($manager->backup($env, '')->name)->not->toContain('--')             // empty → no tag segment
        ->and($manager->backup($env)->name)->toStartWith('env.');                  // unlabelled

    array_map('unlink', glob($dir.'/backups/*') ?: []);
});

it('deleteOlderThan deletes strictly-older backups and counts them', function () {
    $dir = sys_get_temp_dir().'/envkit-age-'.bin2hex(random_bytes(5));
    @mkdir($dir, 0700, true);
    $recent = $dir.'/env.recent.bak';
    $old = $dir.'/env.old.bak';
    file_put_contents($recent, 'x');
    file_put_contents($old, 'x');
    touch($recent, time() - 2 * 86400);  // 2 days old
    touch($old, time() - 10 * 86400);    // 10 days old

    $manager = new BackupManager($dir);

    expect($manager->deleteOlderThan(5))->toBe(1)  // only the 10-day file
        ->and(is_file($old))->toBeFalse()
        ->and(is_file($recent))->toBeTrue()
        ->and($manager->deleteOlderThan(5))->toBe(0); // nothing left older than 5 days

    array_map('unlink', glob($dir.'/*') ?: []);
});

it('examplePath resolves the sibling .env.example', function () {
    $dir = sys_get_temp_dir().'/envkit-xp-'.bin2hex(random_bytes(5));
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/.env', "A=1\n");
    config(['env-kit.path' => $dir.'/.env']);
    $this->app->forgetInstance(EnvKitInterface::class);

    expect(EnvKit::examplePath())->toBe($dir.'/.env.example');
});

it('generate defaults to a 32-byte hex token', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    expect(EnvKit::generate())->toHaveLength(64)              // 32 bytes → 64 hex chars
        ->and(EnvKit::generate('token'))->toHaveLength(64)
        ->and(EnvKit::generate('hex', ['bytes' => 16]))->toHaveLength(32);
});

it('syncFromExample is a no-op when already in sync', function () {
    $dir = sys_get_temp_dir().'/envkit-ns-'.bin2hex(random_bytes(5));
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/.env', "A=1\nB=2\n");
    file_put_contents($dir.'/.env.example', "A=\nB=\n");
    config(['env-kit.path' => $dir.'/.env', 'env-kit.auto_backup' => false]);
    $this->app->forgetInstance(EnvKitInterface::class);

    EnvKit::syncFromExample();

    expect(EnvKit::all())->toBe(['A' => '1', 'B' => '2']); // unchanged
});
