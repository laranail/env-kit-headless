<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Simtabi\Laranail\EnvKit\Headless\Contracts\WriterInterface;
use Simtabi\Laranail\EnvKit\Headless\Events\WriteRolledBack;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\IntegrityException;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;
use Simtabi\Laranail\EnvKit\Headless\Writer\IntegrityVerifier;

/** Re-reads the written file; on mismatch, rolls back to the captured bytes and throws. */
final class Verify
{
    public function __construct(
        private readonly WriterInterface $writer,
        private readonly IntegrityVerifier $verifier,
        private readonly ?Dispatcher $events = null,
    ) {}

    public function handle(CommitContext $context, Closure $next): mixed
    {
        if (! $this->verifier->verify($context->path, $context->document)) {
            $this->rollback($context);
            $this->events?->dispatch(new WriteRolledBack($context->path, 'integrity-check-failed', $context->actor));

            throw IntegrityException::for($context->path);
        }

        return $next($context);
    }

    private function rollback(CommitContext $context): void
    {
        if ($context->previous !== null) {
            $this->writer->write($context->path, $context->previous);
        } elseif (is_file($context->path)) {
            @unlink($context->path);
        }
    }
}
