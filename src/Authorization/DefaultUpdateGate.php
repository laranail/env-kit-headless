<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Authorization;

use Simtabi\Laranail\EnvKit\Headless\Contracts\UpdateGateInterface;
use Simtabi\Laranail\EnvKit\Headless\Security\ProductionGuard;

/**
 * The shipped default: permissive and facade-free, so it never conflicts with the
 * config-driven production protection ({@see ProductionGuard},
 * which already blocks prod writes unless overridden / `protect_production=false`).
 * The gate is the *pluggable authorization seam*: tighten it with a Laravel ability
 * (see {@see LaravelAbilityGate}) or a decorator — `$context->isProduction` is
 * available for environment-aware policies.
 */
final class DefaultUpdateGate implements UpdateGateInterface
{
    public function inspect(WriteContext $context): WriteDecision
    {
        return WriteDecision::allow();
    }
}
