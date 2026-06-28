<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless;

use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
use Simtabi\Laranail\EnvKit\Headless\Support\Interpolator;
use Simtabi\Laranail\EnvKit\Headless\Support\TypedAccessor;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

final class EnvKitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/env-kit-headless')
            ->hasConfigFile('env-kit');
    }

    public function packageRegistered(): void
    {
        // The configurator is a singleton: consumers reshape EnvKit once (from
        // their own provider) and every per-request EnvKit reads that config.
        $this->app->singleton(EnvKitConfigurator::class);

        // Bound `scoped` (resets per request) so the optional pending-session state
        // never leaks between requests on Octane. Config is read lazily at resolve
        // time, so it is always merged by then.
        $this->app->scoped(EnvKitInterface::class, function ($app): EnvKit {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('env-kit', []);

            $protectedKeys = $config['protected_keys'] ?? [];
            $protectedKeys = is_array($protectedKeys) ? array_values(array_filter($protectedKeys, 'is_string')) : [];

            $retention = $config['backup_retention'] ?? 0;
            $retention = is_numeric($retention) ? (int) $retention : 0;

            $interpolation = $config['interpolation'] ?? [];
            $throwOnUndefined = is_array($interpolation) && ($interpolation['on_undefined'] ?? 'empty') === 'throw';

            return new EnvKit(
                path: (string) ($config['path'] ?? $app->basePath('.env')),
                autoCommit: (bool) ($config['auto_commit'] ?? true),
                autoBackup: (bool) ($config['auto_backup'] ?? true),
                isProduction: $app->environment('production'),
                protectProduction: (bool) ($config['protect_production'] ?? true),
                protectedKeys: $protectedKeys,
                backups: new BackupManager(
                    (string) ($config['backup_path'] ?? $app->storagePath('env-kit/backups')),
                    $retention,
                ),
                typed: new TypedAccessor,
                interpolator: new Interpolator(throwOnUndefined: $throwOnUndefined),
                configurator: $app->make(EnvKitConfigurator::class),
            );
        });

        $this->app->alias(EnvKitInterface::class, EnvKit::class);
    }

    public function packageBooted(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SetKeyCommand::class,
                Console\GetKeyCommand::class,
                Console\UnsetKeyCommand::class,
                Console\KeysCommand::class,
                Console\ListCommand::class,
                Console\RenameKeyCommand::class,
            ]);
        }
    }
}
