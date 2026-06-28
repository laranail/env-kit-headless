<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Contracts\WriterInterface;
use Simtabi\Laranail\EnvKit\Headless\EnvKit as EnvKitService;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ProtectedKeyException;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

afterEach(fn () => EnvKitService::flushMacros());

it('lets a consumer add a macro via configure() (no subclassing)', function () {
    $this->bindEnv("A=1\n");

    EnvKit::configure()->macro('tagged', function () {
        return 'env:'.$this->get('A');
    });

    expect(EnvKit::tagged())->toBe('env:1');
});

it('runs consumer mutation middleware added via configure()', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::configure()->pushMutationMiddleware(new class
    {
        public function handle(object $context, Closure $next): mixed
        {
            throw new RuntimeException('vetoed by middleware');
        }
    });

    expect(fn () => EnvKit::set('B', '2'))->toThrow(RuntimeException::class, 'vetoed by middleware');
});

it('protects extra keys added via configure()', function () {
    $this->bindEnv("SECRET_TOKEN=abc\n", ['env-kit.auto_backup' => false]);

    EnvKit::configure()->protectKeys(['SECRET_TOKEN']);

    expect(fn () => EnvKit::set('SECRET_TOKEN', 'new'))->toThrow(ProtectedKeyException::class);
});

it('uses a custom writer registered via configure()', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    $spy = new class implements WriterInterface
    {
        public int $calls = 0;

        public function write(string $path, string $contents): void
        {
            $this->calls++;
            file_put_contents($path, $contents);
        }
    };

    EnvKit::configure()->useWriter($spy);
    EnvKit::set('B', '2');

    expect($spy->calls)->toBeGreaterThan(0)
        ->and(EnvKit::get('B'))->toBe('2');
});
