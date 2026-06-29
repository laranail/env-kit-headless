# Lifecycle events

EnvKit dispatches Laravel events around every commit so you can react — log, broadcast,
invalidate caches, alert. All payloads carry **redacted** values and the resolved **actor**,
so listeners and logs can't leak secrets.

| Event | When | Key payload |
|---|---|---|
| `BeforeWrite` | just before the durable write | `path`, `changes`, `actor`, `operation` |
| `AfterWrite` | after a verified write | `path`, `changes`, `actor`, `operation` |
| `WriteRejected` | a refused write (guard / gate / veto / validation) | `path`, `reason`, `keys`, `actor` |
| `BackupCreated` | a backup was made (auto or explicit) | `path`, `backup`, `actor` |
| `BeforeRestore` / `AfterRestore` | around a `restore()` | `path`, `backupName`/`backup`, `actor` |
| `ConflictDetected` | an optimistic-lock collision | `path`, `expected`, `actual`, `actor` |
| `WriteRolledBack` | a post-write integrity failure was rolled back | `path`, `reason`, `actor` |

Events are **notifications, not veto seams** — to block a write use the [update gate or an
observer](authorization.md), or a mutation-pipeline pipe that skips `$next`.

```php
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\EnvKit\Headless\Events\WriteRejected;

Event::listen(WriteRejected::class, function (WriteRejected $e) {
    Log::warning("EnvKit refused a {$e->reason} write to {$e->path} by {$e->actor}");
});
```

## Who is the actor?

By default EnvKit records the authenticated user (else a console/system identity). Override it:

```php
EnvKit::configure()->resolveActorUsing(fn () => auth()->user()?->email ?? 'system');
// or statically: config(['env-kit.audit.actor' => 'deploy-bot']);
```

The actor also lands on the audit trail and on notifications.

> Deliberately **not** events: per-key `KeyEncrypted`/`KeyDecrypted` (noisy, low value) — use
> an observer if you need them.

---

[← Docs index](../README.md#documentation)
