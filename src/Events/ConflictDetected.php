<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Events;

/**
 * Dispatched when an optimistic-lock check fails — the file changed underneath a
 * staged session (a concurrent edit). The fingerprints are content hashes.
 */
final class ConflictDetected
{
    public function __construct(
        public readonly string $path,
        public readonly string $expected,
        public readonly string $actual,
        public readonly ?string $actor = null,
    ) {}
}
