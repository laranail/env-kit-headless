<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Simtabi\Laranail\EnvKit\Headless\Events\BeforeWrite;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\ChangeSet;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;
use Simtabi\Laranail\EnvKit\Headless\Security\SecretRedactor;

/** Dispatches {@see BeforeWrite} just before the durable write (after the guards/gate). */
final class Notify
{
    public function __construct(
        private readonly SecretRedactor $redactor,
        private readonly ?Dispatcher $events = null,
    ) {}

    public function handle(CommitContext $context, Closure $next): mixed
    {
        $this->events?->dispatch(new BeforeWrite(
            $context->path,
            ChangeSet::redacted($context, $this->redactor),
            $context->actor,
            $context->operation,
        ));

        return $next($context);
    }
}
