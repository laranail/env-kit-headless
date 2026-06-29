<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Events;

/** Dispatched before a named backup is restored over the current .env. */
final class BeforeRestore
{
    public function __construct(
        public readonly string $path,
        public readonly string $backupName,
        public readonly ?string $actor = null,
    ) {}
}
