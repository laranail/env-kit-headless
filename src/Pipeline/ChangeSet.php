<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Pipeline;

use Simtabi\Laranail\EnvKit\Headless\Security\SecretRedactor;

/**
 * Builds the redacted change list shared by the audit + event-dispatching pipes,
 * so secret values are masked once, consistently, before they reach any sink,
 * listener, log, or notification.
 */
final class ChangeSet
{
    /** @return list<array{key: string, old: ?string, new: ?string}> */
    public static function redacted(CommitContext $context, SecretRedactor $redactor): array
    {
        $before = $context->original->toArray();
        $after = $context->document->toArray();
        $changes = [];

        foreach ($context->changedKeys() as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            $changes[] = [
                'key' => $key,
                'old' => $old === null ? null : $redactor->forKey($key, $old),
                'new' => $new === null ? null : $redactor->forKey($key, $new),
            ];
        }

        return $changes;
    }
}
