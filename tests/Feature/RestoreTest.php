<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Events\AfterWrite;
use Simtabi\Laranail\EnvKit\Headless\Events\BeforeRestore;
use Simtabi\Laranail\EnvKit\Headless\Events\WriteRejected;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ProductionGuardException;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('emits WriteRejected (and not BeforeRestore) when a restore is blocked in production', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    $backup = EnvKit::backup();
    EnvKit::set('A', '2');

    $this->app['env'] = 'production';
    $this->app->forgetInstance(EnvKitInterface::class);
    EnvKit::clearResolvedInstances(); // drop the cached non-production instance
    Event::fake([WriteRejected::class, BeforeRestore::class]);

    expect(fn () => EnvKit::restore($backup->name))->toThrow(ProductionGuardException::class);

    Event::assertDispatched(WriteRejected::class, fn (WriteRejected $e) => $e->reason === 'production');
    Event::assertNotDispatched(BeforeRestore::class);
});

it('restores a backup and records it to the audit trail', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false, 'env-kit.audit.enabled' => true]);
    $auditPath = dirname($path).'/audit.log';

    $backup = EnvKit::backup();      // snapshot A=1
    EnvKit::set('A', '2');           // drift to A=2
    EnvKit::restore($backup->name);  // back to A=1

    expect(EnvKit::get('A'))->toBe('1');

    $lines = file($auditPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $last = json_decode((string) end($lines), true);

    // the restore's audit entry shows A going 2 -> 1
    expect($last['changes'][0]['key'])->toBe('A')
        ->and($last['changes'][0]['new'])->toBe('1');
});

it('dispatches AfterWrite when restoring', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    // Fake BEFORE the engine resolves, so the EnvKit captures the fake dispatcher.
    Event::fake([AfterWrite::class]);

    $backup = EnvKit::backup();
    EnvKit::set('A', '2');
    EnvKit::restore($backup->name);

    Event::assertDispatched(AfterWrite::class, fn (AfterWrite $e) => $e->changes !== []);
});
