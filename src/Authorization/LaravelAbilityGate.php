<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Authorization;

use Illuminate\Support\Facades\Gate;
use Simtabi\Laranail\EnvKit\Headless\Contracts\UpdateGateInterface;

/**
 * Bridges the engine's update authorization to Laravel's Gate. When a consumer has
 * defined an `env-kit.update` ability/policy, its decision (and message) wins;
 * otherwise it delegates to the wrapped gate. Provider-wired over the
 * {@see DefaultUpdateGate}, so the engine core never touches a facade.
 */
final class LaravelAbilityGate implements UpdateGateInterface
{
    public function __construct(
        private readonly UpdateGateInterface $inner,
        private readonly string $ability = 'env-kit.update',
    ) {}

    public function inspect(WriteContext $context): WriteDecision
    {
        if (Gate::has($this->ability)) {
            $response = Gate::inspect($this->ability, $context);

            return $response->allowed()
                ? WriteDecision::allow()
                : WriteDecision::deny($response->message() ?? "Denied by the {$this->ability} gate.");
        }

        return $this->inner->inspect($context);
    }
}
