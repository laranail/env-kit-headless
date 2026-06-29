<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Events;

/**
 * Dispatched when a post-write integrity check fails and the previous contents are
 * rolled back — a possible sign of disk faults or tampering, worth alerting on.
 */
final class WriteRolledBack
{
    public function __construct(
        public readonly string $path,
        public readonly string $reason,
        public readonly ?string $actor = null,
    ) {}
}
