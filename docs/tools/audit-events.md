# Audit & Events

Every successful commit is recorded and broadcast — with secret values redacted.

## Audit trail

The default `FileAuditSink` appends one JSON object per line (JSON-lines) to
`storage/env-kit/audit.log`. Each record carries the path, an actor (when set), a
timestamp, and the **redacted** change set:

```json
{"path":"/app/.env","actor":null,"occurred_at":1719600000,"changes":[{"key":"API_TOKEN","old":"••••","new":"••••"}]}
```

Tune it in config:

```php
'audit' => ['enabled' => true, 'path' => storage_path('env-kit/audit.log')],
```

### Custom sinks

Audit events fan out to every registered sink. Implement `AuditSinkInterface` and
register it (a database sink, a SIEM forwarder, …):

```php
use Simtabi\Laranail\EnvKit\Headless\Contracts\AuditSinkInterface;
use Simtabi\Laranail\EnvKit\Headless\Audit\AuditEvent;

final class DatabaseAuditSink implements AuditSinkInterface
{
    public function record(AuditEvent $event): void
    {
        EnvAudit::create($event->toArray());
    }
}

EnvKit::configure()->registerAuditSink(new DatabaseAuditSink);
```

## The `AfterWrite` event

A successful commit dispatches `Events\AfterWrite` (path + redacted changes). Listen
for it like any Laravel event:

```php
use Simtabi\Laranail\EnvKit\Headless\Events\AfterWrite;

Event::listen(function (AfterWrite $event) {
    Log::info('env changed', ['path' => $event->path, 'keys' => count($event->changes)]);
});
```

Because the payload is redacted, listeners and queued jobs can never leak a secret.
A no-op write (nothing actually changed) is neither audited nor broadcast.

`AfterWrite` is one of a **full lifecycle event set** (BeforeWrite, WriteRejected,
BackupCreated, Before/AfterRestore, ConflictDetected, WriteRolledBack) — all redacted and
actor-attributed. See **[Events](../events.md)** for the complete table and
**[Notifications](../notifications.md)** to turn them into operator alerts.

---

[← Docs index](../../README.md#documentation)
