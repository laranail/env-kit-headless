<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Events;

use Simtabi\Laranail\EnvKit\Headless\Backup\BackupFile;

/** Dispatched after a named backup has been restored over the current .env. */
final class AfterRestore
{
    public function __construct(
        public readonly string $path,
        public readonly BackupFile $backup,
        public readonly ?string $actor = null,
    ) {}
}
