<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\EnvKit\Headless\Contracts\WriterInterface;
use Simtabi\Laranail\EnvKit\Headless\Events\AfterRestore;
use Simtabi\Laranail\EnvKit\Headless\Events\AfterWrite;
use Simtabi\Laranail\EnvKit\Headless\Events\BackupCreated;
use Simtabi\Laranail\EnvKit\Headless\Events\BeforeRestore;
use Simtabi\Laranail\EnvKit\Headless\Events\BeforeWrite;
use Simtabi\Laranail\EnvKit\Headless\Events\ConflictDetected;
use Simtabi\Laranail\EnvKit\Headless\Events\WriteRejected;
use Simtabi\Laranail\EnvKit\Headless\Events\WriteRolledBack;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ConflictException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\IntegrityException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ProtectedKeyException;
use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('dispatches BeforeWrite then AfterWrite with redacted, attributed changes', function () {
    $this->bindEnv("DB_PASSWORD=old\n", ['env-kit.auto_backup' => false]);
    app(EnvKitConfigurator::class)->resolveActorUsing(fn () => 'alice');

    Event::fake([BeforeWrite::class, AfterWrite::class]);
    EnvKit::set('DB_PASSWORD', 'newsecret');

    Event::assertDispatched(BeforeWrite::class, fn (BeforeWrite $e) => $e->actor === 'alice'
        && $e->operation === 'write'
        && $e->changes[0]['new'] === '••••••'); // secret masked, never the raw value
    Event::assertDispatched(AfterWrite::class, fn (AfterWrite $e) => $e->actor === 'alice');
});

it('dispatches WriteRejected (not AfterWrite) when a protected key is refused', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false, 'env-kit.protected_keys' => ['LOCKED']]);

    Event::fake([WriteRejected::class, AfterWrite::class]);
    expect(fn () => EnvKit::set('LOCKED', 'x'))->toThrow(ProtectedKeyException::class);

    Event::assertDispatched(WriteRejected::class, fn (WriteRejected $e) => $e->reason === 'protected'
        && in_array('LOCKED', $e->keys, true));
    Event::assertNotDispatched(AfterWrite::class);
});

it('dispatches BackupCreated on an explicit backup', function () {
    $this->bindEnv("A=1\n");

    Event::fake([BackupCreated::class]);
    EnvKit::backup();

    Event::assertDispatched(BackupCreated::class, fn (BackupCreated $e) => str_ends_with($e->backup->name, '.bak'));
});

it('dispatches BeforeRestore and AfterRestore around a restore', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    Event::fake([BeforeRestore::class, AfterRestore::class]);
    $backup = EnvKit::backup();
    EnvKit::set('A', '2');
    EnvKit::restore($backup->name);

    Event::assertDispatched(BeforeRestore::class, fn (BeforeRestore $e) => $e->backupName === $backup->name);
    Event::assertDispatched(AfterRestore::class);
});

it('dispatches ConflictDetected on a concurrent external edit', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    Event::fake([ConflictDetected::class]);
    $session = EnvKit::open();
    $session->set('A', '2');
    file_put_contents($path, "A=99\n"); // external change between open and save

    expect(fn () => $session->save())->toThrow(ConflictException::class);
    Event::assertDispatched(ConflictDetected::class, fn (ConflictDetected $e) => $e->expected !== $e->actual);
});

it('dispatches WriteRolledBack when post-write verification fails', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    Event::fake([WriteRolledBack::class]);
    EnvKit::configure()->useWriter(new class implements WriterInterface
    {
        public function write(string $path, string $contents): void
        {
            file_put_contents($path, "A=CORRUPT\n"); // diverges from the document → verify fails
        }
    });

    expect(fn () => EnvKit::set('A', '2'))->toThrow(IntegrityException::class);
    Event::assertDispatched(WriteRolledBack::class, fn (WriteRolledBack $e) => $e->reason === 'integrity-check-failed');
});
