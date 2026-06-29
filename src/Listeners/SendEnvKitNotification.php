<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Simtabi\Laranail\EnvKit\Headless\Events\AfterRestore;
use Simtabi\Laranail\EnvKit\Headless\Events\AfterWrite;
use Simtabi\Laranail\EnvKit\Headless\Events\BackupCreated;
use Simtabi\Laranail\EnvKit\Headless\Events\BeforeWrite;
use Simtabi\Laranail\EnvKit\Headless\Events\ConflictDetected;
use Simtabi\Laranail\EnvKit\Headless\Events\WriteRejected;
use Simtabi\Laranail\EnvKit\Headless\Events\WriteRolledBack;
use Simtabi\Laranail\EnvKit\Headless\Notifications\EnvKitEventNotification;

/**
 * Opt-in bridge from lifecycle events to Laravel on-demand notifications. Subscribed
 * always; every guard (enabled / configured event / production-only / routed channel)
 * is checked at dispatch so config changes take effect without re-subscribing.
 */
final class SendEnvKitNotification
{
    /** @var array<class-string, string> */
    private const EVENT_KEYS = [
        BeforeWrite::class => 'before_write',
        AfterWrite::class => 'after_write',
        WriteRejected::class => 'write_rejected',
        BackupCreated::class => 'backup_created',
        AfterRestore::class => 'after_restore',
        ConflictDetected::class => 'conflict_detected',
        WriteRolledBack::class => 'write_rolled_back',
    ];

    public function handle(object $event): void
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('env-kit.notifications', []);

        if (! ($config['enabled'] ?? false)) {
            return;
        }

        $key = self::EVENT_KEYS[$event::class] ?? null;
        $events = is_array($config['events'] ?? null) ? $config['events'] : [];
        if ($key === null || ! in_array($key, $events, true)) {
            return;
        }

        if (($config['production_only'] ?? true) && ! app()->environment('production')) {
            return;
        }

        $summary = $this->summarize($key, $event);
        $channels = is_array($config['channels'] ?? null) ? $config['channels'] : [];
        $routes = is_array($config['routes'] ?? null) ? $config['routes'] : [];

        foreach ($channels as $channel) {
            if (! is_string($channel)) {
                continue;
            }

            $target = $routes[$channel] ?? null;
            if (! is_string($target) || $target === '') {
                continue; // skip channels without a configured route target
            }

            Notification::route($channel, $target)
                ->notify(new EnvKitEventNotification($summary, [$channel]));
        }
    }

    /** @return array<string, list<string>> */
    public function subscribe(Dispatcher $events): array
    {
        $map = [];
        foreach (array_keys(self::EVENT_KEYS) as $event) {
            $map[$event] = ['handle'];
        }

        return $map;
    }

    /** @return array<string, mixed> */
    private function summarize(string $key, object $event): array
    {
        $summary = ['event' => $key];

        foreach (['path', 'actor', 'reason'] as $field) {
            if (property_exists($event, $field)) {
                $summary[$field] = $event->{$field};
            }
        }

        if (property_exists($event, 'changes')) {
            $summary['changes'] = $event->changes; // already redacted at the source
        }

        return $summary;
    }
}
