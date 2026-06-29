<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Authorization;

use Simtabi\Laranail\EnvKit\Headless\Contracts\UpdateGateInterface;

/**
 * The allow/deny outcome of an {@see UpdateGateInterface}.
 * Framework-neutral so the engine core depends on no auth package.
 */
final class WriteDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}
