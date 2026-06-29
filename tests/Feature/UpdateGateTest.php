<?php

declare(strict_types=1);

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteContext;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteDecision;
use Simtabi\Laranail\EnvKit\Headless\Contracts\UpdateGateInterface;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\UnauthorizedUpdateException;
use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('allows writes by default', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::set('A', '2');

    expect(EnvKit::get('A'))->toBe('2');
});

it('honours a defined env-kit.update Laravel ability', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    Gate::define('env-kit.update', fn (?Authenticatable $user) => false);

    expect(fn () => EnvKit::set('A', '2'))->toThrow(UnauthorizedUpdateException::class);
    expect(EnvKit::get('A'))->toBe('1');
});

it('surfaces the ability deny message', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    Gate::define('env-kit.update', fn (?Authenticatable $user) => Response::deny('nope, not you'));

    expect(fn () => EnvKit::set('A', '2'))->toThrow(UnauthorizedUpdateException::class, 'nope, not you');
});

it('replaces the gate with useUpdateGate', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    app(EnvKitConfigurator::class)->useUpdateGate(new class implements UpdateGateInterface
    {
        public function inspect(WriteContext $context): WriteDecision
        {
            return WriteDecision::deny('replaced');
        }
    });

    expect(fn () => EnvKit::set('A', '2'))->toThrow(UnauthorizedUpdateException::class, 'replaced');
});

it('composes decorators with the last as the outermost wrapper', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    // The outer decorator denies regardless of the inner gate — outermost wins.
    app(EnvKitConfigurator::class)->decorateUpdateGate(fn (UpdateGateInterface $inner) => new class($inner) implements UpdateGateInterface
    {
        public function __construct(private readonly UpdateGateInterface $inner) {}

        public function inspect(WriteContext $context): WriteDecision
        {
            return WriteDecision::deny('outermost');
        }
    });

    expect(fn () => EnvKit::set('A', '2'))->toThrow(UnauthorizedUpdateException::class, 'outermost');
});
