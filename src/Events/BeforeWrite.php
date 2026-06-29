<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Events;

/**
 * Dispatched just before a commit is written to disk. `$changes` carry key names
 * with ALREADY-REDACTED values. This is a notification, not a veto seam — to block
 * a write, push a mutation-pipeline pipe that skips `$next`, or use a write gate /
 * observer (Part 6).
 */
final class BeforeWrite
{
    /** @param list<array{key: string, old: ?string, new: ?string}> $changes */
    public function __construct(
        public readonly string $path,
        public readonly array $changes,
        public readonly ?string $actor = null,
        public readonly string $operation = 'write',
    ) {}
}
