<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Authorization;

use Simtabi\Laranail\EnvKit\Headless\Contracts\WriteObserverInterface;

/** No-op base: extend it and override only the lifecycle hooks you care about. */
abstract class AbstractWriteObserver implements WriteObserverInterface
{
    public function saving(WriteContext $context): bool|WriteDecision|null
    {
        return null;
    }

    public function saved(WriteContext $context): void {}

    public function creating(string $key, ?string $old, ?string $new): ?bool
    {
        return null;
    }

    public function updating(string $key, ?string $old, ?string $new): ?bool
    {
        return null;
    }

    public function deleting(string $key, ?string $old, ?string $new): ?bool
    {
        return null;
    }

    public function restoring(WriteContext $context): bool|WriteDecision|null
    {
        return null;
    }

    public function restored(WriteContext $context): void {}
}
