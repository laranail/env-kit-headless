<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Authorization\AbstractWriteObserver;
use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Session\EditSession;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('observers receive the exact old/new values for create, update and delete', function () {
    $this->bindEnv("KEEP=1\nUPD=old\nDEL=x\n", ['env-kit.auto_backup' => false]);

    $observer = new class extends AbstractWriteObserver
    {
        /** @var array<string, array{0: string, 1: ?string, 2: ?string}> */
        public array $seen = [];

        public function creating(string $key, ?string $old, ?string $new): bool
        {
            $this->seen['create'] = [$key, $old, $new];

            return true;
        }

        public function updating(string $key, ?string $old, ?string $new): bool
        {
            $this->seen['update'] = [$key, $old, $new];

            return true;
        }

        public function deleting(string $key, ?string $old, ?string $new): bool
        {
            $this->seen['delete'] = [$key, $old, $new];

            return true;
        }
    };

    app(EnvKitConfigurator::class)->observe($observer);

    EnvKit::transaction(fn (EditSession $s) => $s->set('NEW', 'n')->set('UPD', 'new')->forget('DEL'));

    expect($observer->seen['create'])->toBe(['NEW', null, 'n'])   // created → old null
        ->and($observer->seen['update'])->toBe(['UPD', 'old', 'new']) // updated → exact old + new
        ->and($observer->seen['delete'])->toBe(['DEL', 'x', null]);   // deleted → new null

    // The committed file reflects all three mutations.
    expect(EnvKit::get('NEW'))->toBe('n')
        ->and(EnvKit::get('UPD'))->toBe('new')
        ->and(EnvKit::has('DEL'))->toBeFalse()
        ->and(EnvKit::get('KEEP'))->toBe('1'); // untouched key absent from the change set
});
