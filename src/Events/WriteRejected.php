<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Events;

/**
 * Dispatched when a commit is refused — by the production guard, a protected /
 * non-editable key, the update gate, an observer veto, or validation. Useful for
 * alerting on blocked or unauthorised attempts.
 */
final class WriteRejected
{
    /** @param list<string> $keys */
    public function __construct(
        public readonly string $path,
        public readonly string $reason,
        public readonly array $keys = [],
        public readonly ?string $actor = null,
    ) {}
}
