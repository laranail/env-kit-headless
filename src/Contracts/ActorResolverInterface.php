<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Contracts;

/**
 * Resolves "who" is performing a commit, for the audit trail + lifecycle events.
 * Returns a stable identifier (user id / username / a system label), never raw
 * PII beyond what an audit log should hold. Resolved once per commit.
 */
interface ActorResolverInterface
{
    public function resolve(): ?string;
}
