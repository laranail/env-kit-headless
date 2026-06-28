<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Tests;

use Illuminate\Support\Facades\Facade;
use Orchestra\Testbench\TestCase as Orchestra;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\EnvKitServiceProvider;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;

abstract class TestCase extends Orchestra
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [EnvKitServiceProvider::class];
    }

    /** @return array<string, class-string> */
    protected function getPackageAliases($app): array
    {
        return ['EnvKit' => EnvKit::class];
    }

    /** A fixed AES-256-CBC key so the encryption cipher resolves in tests. */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    /**
     * Point EnvKit at a fresh temp .env with the given contents, then rebind so
     * both the container and the facade pick up the new config.
     *
     * @param  array<string, mixed>  $overrides  dot-keyed config overrides
     */
    protected function bindEnv(string $contents, array $overrides = []): string
    {
        $dir = sys_get_temp_dir().'/envkit-ft-'.bin2hex(random_bytes(5));
        @mkdir($dir, 0777, true);
        $path = $dir.'/.env';
        file_put_contents($path, $contents);

        config(array_merge([
            'env-kit.path' => $path,
            'env-kit.backup_path' => $dir.'/backups',
            'env-kit.audit.enabled' => false,        // opt-in per test
            'env-kit.audit.path' => $dir.'/audit.log',
        ], $overrides));

        $this->app->forgetInstance(EnvKitInterface::class);
        Facade::clearResolvedInstance(EnvKitInterface::class);

        return $path;
    }
}
