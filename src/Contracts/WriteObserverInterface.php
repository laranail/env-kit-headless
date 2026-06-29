<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Contracts;

use Simtabi\Laranail\EnvKit\Headless\Authorization\AbstractWriteObserver;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteContext;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteDecision;

/**
 * Eloquent-style lifecycle hooks around a commit. A "before" hook returning
 * `false` (or a denying {@see WriteDecision}) vetoes the write. Extend
 * {@see AbstractWriteObserver}
 * to override only the hooks you need.
 */
interface WriteObserverInterface
{
    public function saving(WriteContext $context): bool|WriteDecision|null;

    public function saved(WriteContext $context): void;

    public function creating(string $key, ?string $old, ?string $new): ?bool;

    public function updating(string $key, ?string $old, ?string $new): ?bool;

    public function deleting(string $key, ?string $old, ?string $new): ?bool;

    public function restoring(WriteContext $context): bool|WriteDecision|null;

    public function restored(WriteContext $context): void;
}
