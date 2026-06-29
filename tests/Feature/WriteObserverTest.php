<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Authorization\AbstractWriteObserver;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteContext;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\WriteVetoedException;
use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('lets an observer veto a write via saving()', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    app(EnvKitConfigurator::class)->observe(new class extends AbstractWriteObserver
    {
        public function saving(WriteContext $context): bool
        {
            return false;
        }
    });

    expect(fn () => EnvKit::set('A', '2'))->toThrow(WriteVetoedException::class);
    expect(EnvKit::get('A'))->toBe('1'); // unchanged
});

it('runs saved() only after a successful write', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    $observer = new class extends AbstractWriteObserver
    {
        /** @var list<string> */
        public array $log = [];

        public function saving(WriteContext $context): null
        {
            $this->log[] = 'saving';

            return null;
        }

        public function saved(WriteContext $context): void
        {
            $this->log[] = 'saved';
        }
    };
    app(EnvKitConfigurator::class)->observe($observer);

    EnvKit::set('A', '2');

    expect($observer->log)->toBe(['saving', 'saved']);
});

it('fires the granular updating hook and can veto a single key', function () {
    $this->bindEnv("A=1\nB=2\n", ['env-kit.auto_backup' => false]);
    app(EnvKitConfigurator::class)->observe(new class extends AbstractWriteObserver
    {
        public function updating(string $key, ?string $old, ?string $new): bool
        {
            return $key !== 'A'; // veto updates to A
        }
    });

    expect(fn () => EnvKit::set('A', '9'))->toThrow(WriteVetoedException::class);
    EnvKit::set('B', '9'); // a different key is fine
    expect(EnvKit::get('B'))->toBe('9');
});

it('fires creating for a new key and deleting for a removed key', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    $observer = new class extends AbstractWriteObserver
    {
        /** @var list<string> */
        public array $log = [];

        public function creating(string $key, ?string $old, ?string $new): null
        {
            $this->log[] = "create:{$key}";

            return null;
        }

        public function deleting(string $key, ?string $old, ?string $new): null
        {
            $this->log[] = "delete:{$key}";

            return null;
        }
    };
    app(EnvKitConfigurator::class)->observe($observer);

    EnvKit::set('C', '3'); // a new key → creating
    EnvKit::forget('A');   // a removed key → deleting

    expect($observer->log)->toContain('create:C')->toContain('delete:A');
});

it('fires restoring/restored (not saving) on a restore', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    $observer = new class extends AbstractWriteObserver
    {
        /** @var list<string> */
        public array $log = [];

        public function saving(WriteContext $context): null
        {
            $this->log[] = 'saving';

            return null;
        }

        public function restoring(WriteContext $context): null
        {
            $this->log[] = 'restoring';

            return null;
        }

        public function restored(WriteContext $context): void
        {
            $this->log[] = 'restored';
        }
    };
    app(EnvKitConfigurator::class)->observe($observer);

    $backup = EnvKit::backup();
    EnvKit::set('A', '2');        // saving
    EnvKit::restore($backup->name); // restoring + restored (no saving)

    expect($observer->log)->toBe(['saving', 'restoring', 'restored']);
});
