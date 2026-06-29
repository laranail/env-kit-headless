<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes;

use Closure;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteContext;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteDecision;
use Simtabi\Laranail\EnvKit\Headless\Contracts\WriteObserverInterface;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\WriteVetoedException;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;

/**
 * Runs the registered write observers: `saving` (+ per-key `creating/updating/
 * deleting`) before the write — a `false`/denying return vetoes — and `saved`
 * after a successful write. A restore fires `restoring`/`restored` instead.
 */
final class Observe
{
    /** @param list<WriteObserverInterface> $observers */
    public function __construct(
        private readonly array $observers,
        private readonly bool $isProduction,
    ) {}

    public function handle(CommitContext $context, Closure $next): mixed
    {
        $write = new WriteContext($context, $this->isProduction);
        $isRestore = $context->operation === 'restore';

        foreach ($this->observers as $observer) {
            $this->veto($isRestore ? $observer->restoring($write) : $observer->saving($write));

            if (! $isRestore) {
                $this->observeKeys($observer, $write, $context->changedKeys());
            }
        }

        $result = $next($context);

        foreach ($this->observers as $observer) {
            $isRestore ? $observer->restored($write) : $observer->saved($write);
        }

        return $result;
    }

    /** @param list<string> $keys */
    private function observeKeys(WriteObserverInterface $observer, WriteContext $write, array $keys): void
    {
        foreach ($keys as $key) {
            ['old' => $old, 'new' => $new] = $write->change($key);

            $outcome = match (true) {
                $old === null => $observer->creating($key, $old, $new),
                $new === null => $observer->deleting($key, $old, $new),
                default => $observer->updating($key, $old, $new),
            };

            if ($outcome === false) {
                throw WriteVetoedException::because("Observer vetoed change to {$key}.");
            }
        }
    }

    private function veto(bool|WriteDecision|null $outcome): void
    {
        if ($outcome === false) {
            throw WriteVetoedException::because('Observer vetoed the write.');
        }

        if ($outcome instanceof WriteDecision && ! $outcome->allowed) {
            throw WriteVetoedException::because($outcome->reason ?? 'Observer vetoed the write.');
        }
    }
}
