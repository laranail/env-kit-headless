# Notifications

Turn [lifecycle events](events.md) into operator alerts without writing a listener. Opt-in,
queued (so they never block a write), and routed entirely from config — no notifiable model
needed.

```php
// config/env-kit.php
'notifications' => [
    'enabled'  => true,
    'channels' => ['mail', 'slack'],
    'routes'   => [                       // channel => on-demand recipient (null = skip)
        'mail'  => 'ops@example.com',
        'slack' => env('ENV_KIT_SLACK_WEBHOOK'),
    ],
    'events'   => ['after_write', 'write_rejected', 'after_restore'],
    'production_only' => true,            // only alert for production-environment changes
],
```

`events` accepts: `before_write`, `after_write`, `write_rejected`, `backup_created`,
`after_restore`, `conflict_detected`, `write_rolled_back`.

Each alert is an `EnvKitEventNotification` carrying a **redacted, actor-attributed** summary
(`event`, `path`, `actor`, `changes`/`reason`). A channel is skipped when its route target is
null.

> **Channel packages.** `mail` and `database` work out of the box. Other channels need their
> Laravel notification-channel package (e.g. `laravel/slack-notification-channel` for `slack`).
> For custom rendering, extend `EnvKitEventNotification`.

## Testing

```php
use Illuminate\Support\Facades\Notification;
use Simtabi\Laranail\EnvKit\Headless\Notifications\EnvKitEventNotification;

Notification::fake();
EnvKit::set('APP_NAME', 'Acme');
Notification::assertSentOnDemand(EnvKitEventNotification::class);
```

---

[← Docs index](../README.md#documentation)
