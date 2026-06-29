<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Events\BackupCreated;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;

/** Snapshots the current file before it is overwritten (when backups are enabled). */
final class Backup
{
    public function __construct(
        private readonly ?BackupManager $backups,
        private readonly ?Dispatcher $events = null,
    ) {}

    public function handle(CommitContext $context, Closure $next): mixed
    {
        $backup = $this->backups?->backup($context->path);

        if ($backup !== null) {
            $this->events?->dispatch(new BackupCreated($context->path, $backup, $context->actor));
        }

        return $next($context);
    }
}
