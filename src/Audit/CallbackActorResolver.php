<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Audit;

use Closure;
use Simtabi\Laranail\EnvKit\Headless\Contracts\ActorResolverInterface;

/** Resolves the commit actor from a provider-supplied closure (e.g. auth()->id()). */
final class CallbackActorResolver implements ActorResolverInterface
{
    /** @param Closure(): ?string $resolver */
    public function __construct(
        private readonly Closure $resolver,
    ) {}

    public function resolve(): ?string
    {
        return ($this->resolver)();
    }
}
