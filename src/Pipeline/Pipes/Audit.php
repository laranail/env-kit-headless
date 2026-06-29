<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Simtabi\Laranail\EnvKit\Headless\Audit\AuditEvent;
use Simtabi\Laranail\EnvKit\Headless\Contracts\AuditSinkInterface;
use Simtabi\Laranail\EnvKit\Headless\Events\AfterWrite;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;
use Simtabi\Laranail\EnvKit\Headless\Security\SecretRedactor;

/**
 * The final pipe: after a successful write+verify, record the (redacted) change
 * set to every sink and dispatch {@see AfterWrite}. Runs only on success — a
 * failed/rolled-back commit is never audited.
 */
final class Audit
{
    /** @param list<AuditSinkInterface> $sinks */
    public function __construct(
        private readonly array $sinks,
        private readonly SecretRedactor $redactor,
        private readonly ?Dispatcher $events = null,
    ) {}

    public function handle(CommitContext $context, Closure $next): mixed
    {
        $result = $next($context);

        $changes = $this->redactedChanges($context);

        $event = new AuditEvent($context->path, $changes, $context->actor, time());
        foreach ($this->sinks as $sink) {
            $sink->record($event);
        }

        $this->events?->dispatch(new AfterWrite($context->path, $changes));

        return $result;
    }

    /** @return list<array{key: string, old: ?string, new: ?string}> */
    private function redactedChanges(CommitContext $context): array
    {
        $before = $context->original->toArray();
        $after = $context->document->toArray();
        $changes = [];

        foreach ($context->changedKeys() as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            $changes[] = [
                'key' => $key,
                'old' => $old === null ? null : $this->redactor->forKey($key, $old),
                'new' => $new === null ? null : $this->redactor->forKey($key, $new),
            ];
        }

        return $changes;
    }
}
