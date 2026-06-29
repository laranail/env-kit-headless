<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes;

use Closure;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteContext;
use Simtabi\Laranail\EnvKit\Headless\Contracts\UpdateGateInterface;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\UnauthorizedUpdateException;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;

/** Runs the (decorated) update gate; a denial aborts the commit before the write. */
final class Authorize
{
    public function __construct(
        private readonly UpdateGateInterface $gate,
        private readonly bool $isProduction,
    ) {}

    public function handle(CommitContext $context, Closure $next): mixed
    {
        $decision = $this->gate->inspect(new WriteContext($context, $this->isProduction));

        if (! $decision->allowed) {
            throw UnauthorizedUpdateException::because($decision->reason ?? 'Update not authorized.');
        }

        return $next($context);
    }
}
