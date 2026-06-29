<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes;

use Closure;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;
use Simtabi\Laranail\EnvKit\Headless\Security\EditableKeys;
use Simtabi\Laranail\EnvKit\Headless\Security\ProductionGuard;
use Simtabi\Laranail\EnvKit\Headless\Security\ProtectedKeys;

/** Enforces the production guard + protected-key + editable-allowlist policy before any write. */
final class Guard
{
    public function __construct(
        private readonly ProductionGuard $production,
        private readonly ProtectedKeys $protected,
        private readonly EditableKeys $editable = new EditableKeys,
    ) {}

    public function handle(CommitContext $context, Closure $next): mixed
    {
        $this->production->guard($context->allowProduction);

        foreach ($context->changedKeys() as $key) {
            // Protected first → a protected key raises the more specific exception.
            $this->protected->guard($key);
            $this->editable->guard($key);
        }

        return $next($context);
    }
}
