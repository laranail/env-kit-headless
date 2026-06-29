# Secure-update authorization

EnvKit guards writes at several layers. This page covers the **pluggable** ones — the
update gate and write observers — and explains how they relate to the others so you know
which seam to reach for.

## Which seam do I use?

| Layer | What it does | Veto? | Configure |
|---|---|:--:|---|
| WebUI surface access | Can a request reach the editor at all (enabled / IP / token / schedule / gate) | — | `env-kit-webui.*` (WebUI package) |
| Config guards | Production protection, protected keys, editable allowlist | yes | `env-kit.protect_production` / `protected_keys` / `editable_keys` |
| **Update gate** | A single, decoratable "is this actor allowed to make this change?" decision | yes | `configure()->useUpdateGate()` / `decorateUpdateGate()` / a Laravel `env-kit.update` ability |
| **Write observers** | Eloquent-style lifecycle hooks that react to / veto a write | yes | `configure()->observe()` / the `env-kit.observers` tag |
| Lifecycle events | Notifications after the fact (`AfterWrite`, …) — see [events](events.md) | no | event listeners |
| Mutation middleware | Low-level pipeline pipes | yes | `configure()->pushMutationMiddleware()` |

> **Two different `gate`s.** The engine ability `env-kit.update` (this page) authorizes a
> *write*. The WebUI's `env-kit-webui.gate` authorizes *reaching the web surface*. They are
> independent.

## The update gate

The shipped `DefaultUpdateGate` is **permissive** — production protection stays the job of
the production guard, so the gate never conflicts with `protect_production`. Its value is as
a seam:

### A Laravel ability

Define an `env-kit.update` ability and the engine enforces it on every write (and restore):

```php
use Illuminate\Auth\Access\Response;

Gate::define('env-kit.update', fn ($user) =>
    $user->can('manage-env') ? Response::allow() : Response::deny('Not allowed to edit .env.'));
```

A denial throws `UnauthorizedUpdateException` (the WebUI maps it to **403**, with the gate's
message).

### Swap or decorate at runtime

From your own service provider:

```php
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;

// Replace the gate outright:
EnvKit::configure()->useUpdateGate(new MyGate);

// Or wrap the current gate (decorators compose; the LAST registered is the OUTERMOST):
EnvKit::configure()->decorateUpdateGate(fn ($inner) => new BusinessHoursGate($inner));
```

The effective gate is resolved per commit, so you can reshape it dynamically — e.g. relax it
outside production:

```php
EnvKit::configure()->decorateUpdateGate(fn ($inner) =>
    app()->isProduction() ? $inner : new AlwaysAllowGate);
```

A gate implements `UpdateGateInterface::inspect(WriteContext): WriteDecision` (`WriteDecision::allow()`
/ `WriteDecision::deny('reason')`).

## Write observers

Mirror Eloquent's model observers. Extend `AbstractWriteObserver` and override only what you
need; return `false` (or a denying `WriteDecision`) from a *before* hook to veto:

```php
use Simtabi\Laranail\EnvKit\Headless\Authorization\AbstractWriteObserver;
use Simtabi\Laranail\EnvKit\Headless\Authorization\WriteContext;

final class AuditObserver extends AbstractWriteObserver
{
    public function saving(WriteContext $c): bool        // before a write (false vetoes)
    {
        return ! str_contains($c->path(), 'production');
    }

    public function updating(string $key, ?string $old, ?string $new): ?bool { /* per-key */ }

    public function saved(WriteContext $c): void { /* after a successful write */ }
}

EnvKit::configure()->observe(new AuditObserver);
```

Hooks: `saving`/`saved`, per-key `creating`/`updating`/`deleting`, and `restoring`/`restored`
(fired instead of `saving`/`saved` for a restore). A veto throws `WriteVetoedException` (WebUI
→ **403**).

> Gates and observers are trusted consumer code, so they receive **raw** values — events and
> notifications stay redacted.

---

[← Docs index](../README.md#documentation)
