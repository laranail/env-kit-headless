<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless;

use Illuminate\Contracts\Events\Dispatcher;
use Simtabi\Laranail\EnvKit\Headless\Audit\FileAuditSink;
use Simtabi\Laranail\EnvKit\Headless\Audit\NullAuditSink;
use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
use Simtabi\Laranail\EnvKit\Headless\Security\SecretRedactor;
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

        // The cipher driver registry (named drivers + extend()).
        $this->app->singleton(EnvKitManager::class);

        // The redactor honours config('env-kit.hidden_keys') and is shared by the
        // engine, the CLI (env:list), and the WebUI so masking is consistent everywhere.
        $this->app->scoped(SecretRedactor::class, function ($app): SecretRedactor {
            $maskKeys = $app['config']->get('env-kit.hidden_keys', []);
            $maskKeys = is_array($maskKeys) ? array_values(array_filter($maskKeys, 'is_string')) : [];

            return $maskKeys === [] ? new SecretRedactor : new SecretRedactor($maskKeys);
        });

        // Bound `scoped` (resets per request) so the optional pending-session state
        // never leaks between requests on Octane. Config is read lazily at resolve
        // time, so it is always merged by then.
        $this->app->scoped(EnvKitInterface::class, function ($app): EnvKit {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('env-kit', []);

            $protectedKeys = $config['protected_keys'] ?? [];
            $protectedKeys = is_array($protectedKeys) ? array_values(array_filter($protectedKeys, 'is_string')) : [];

            $editableKeys = $config['editable_keys'] ?? [];
            $editableKeys = is_array($editableKeys) ? array_values(array_filter($editableKeys, 'is_string')) : [];

            $retention = $config['backup_retention'] ?? 0;
            $retention = is_numeric($retention) ? (int) $retention : 0;

            $interpolation = $config['interpolation'] ?? [];
            $throwOnUndefined = is_array($interpolation) && ($interpolation['on_undefined'] ?? 'empty') === 'throw';

            $audit = is_array($config['audit'] ?? null) ? $config['audit'] : [];
            $auditEnabled = (bool) ($audit['enabled'] ?? true);
            $auditPath = is_string($audit['path'] ?? null) ? $audit['path'] : (string) $app->storagePath('env-kit/audit.log');

            return new EnvKit(
                path: (string) ($config['path'] ?? $app->basePath('.env')),
                autoCommit: (bool) ($config['auto_commit'] ?? true),
                autoBackup: (bool) ($config['auto_backup'] ?? true),
                isProduction: $app->environment('production'),
                protectProduction: (bool) ($config['protect_production'] ?? true),
                protectedKeys: $protectedKeys,
                editableKeys: $editableKeys,
                backups: new BackupManager(
                    (string) ($config['backup_path'] ?? $app->storagePath('env-kit/backups')),
                    $retention,
                ),
                typed: new TypedAccessor,
                interpolator: new Interpolator(throwOnUndefined: $throwOnUndefined),
                configurator: $app->make(EnvKitConfigurator::class),
                auditSink: $auditEnabled ? new FileAuditSink($auditPath) : new NullAuditSink,
                redactor: $app->make(SecretRedactor::class),
                events: $app->make(Dispatcher::class),
            );
        });

        $this->app->alias(EnvKitInterface::class, EnvKit::class);
    }

    public function packageBooted(): void
    {
        // Seed the default cipher lazily so the Encrypter (and APP_KEY) is only
        // touched when encryption is actually used.
        $this->app->make(EnvKitConfigurator::class)->resolveCipherUsing(
            fn () => $this->app->make(EnvKitManager::class)->cipher(),
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SetKeyCommand::class,
                Console\GetKeyCommand::class,
                Console\UnsetKeyCommand::class,
                Console\KeysCommand::class,
                Console\ListCommand::class,
                Console\RenameKeyCommand::class,
                Console\BackupCommand::class,
                Console\BackupsListCommand::class,
                Console\RestoreCommand::class,
                Console\ValidateCommand::class,
                Console\EditCommand::class,
                Console\DoctorCommand::class,
                Console\DiffCommand::class,
                Console\ExportCommand::class,
                Console\ImportCommand::class,
            ]);
        }
    }
}
