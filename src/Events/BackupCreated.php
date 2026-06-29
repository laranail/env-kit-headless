<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Events;

use Simtabi\Laranail\EnvKit\Headless\Backup\BackupFile;

/** Dispatched when a backup of the .env is created (auto-backup or an explicit backup()). */
final class BackupCreated
{
    public function __construct(
        public readonly string $path,
        public readonly BackupFile $backup,
        public readonly ?string $actor = null,
    ) {}
}
