<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Manager;
use LogicException;
use Simtabi\Laranail\EnvKit\Headless\Contracts\ValueCipherInterface;
use Simtabi\Laranail\EnvKit\Headless\Security\LaravelValueCipher;

/**
 * The cipher driver registry (Illuminate\Support\Manager). Ships a `laravel`
 * driver; consumers add their own with `EnvKitManager::extend('vault', fn () => …)`
 * and select it via `env-kit.encryption.driver`. The canonical Open/Closed
 * "named driver" seam alongside the {@see Extension\EnvKitConfigurator} DSL.
 */
final class EnvKitManager extends Manager
{
    public function __construct(
        private readonly Application $app,
    ) {
        parent::__construct($app);
    }

    public function getDefaultDriver(): string
    {
        $driver = $this->app->make(Repository::class)->get('env-kit.encryption.driver', 'laravel');

        return \is_string($driver) ? $driver : 'laravel';
    }

    public function createLaravelDriver(): ValueCipherInterface
    {
        return new LaravelValueCipher($this->app->make(Encrypter::class));
    }

    /** Resolve a cipher driver (the default when $driver is null). */
    public function cipher(?string $driver = null): ValueCipherInterface
    {
        $resolved = $this->driver($driver);

        if (! $resolved instanceof ValueCipherInterface) {
            throw new LogicException('An EnvKit cipher driver must implement ValueCipherInterface.');
        }

        return $resolved;
    }
}
