# Extending

EnvKit is Open/Closed: you reshape it from **your own** service provider's `boot()`
with zero edits to package source. Five composable mechanisms cover every need.

Call them once, on the bound configurator singleton, via `EnvKit::configure()`.

## 1. The `configure()` DSL

```php
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;

public function boot(): void
{
    EnvKit::configure()
        ->protectKeys(['STRIPE_SECRET', 'AWS_SECRET_ACCESS_KEY'])
        ->onlyEditable(['APP_*', 'MAIL_*'])   // write-allowlist (empty = no restriction)
        ->useWriter(new MyAuditingWriter)
        ->registerAuditSink(new DatabaseAuditSink)
        ->registerDoctorRule(new RequireAppUrlRule)
        ->registerPortFormat(new YamlFormat)
        ->useCipher(new VaultCipher);
}
```

Every call returns the configurator, so it chains. The configurator is a singleton,
so what you register is read by every per-request `EnvKit` instance.

## 2. Macros (Macroable)

Add fluent methods without subclassing:

```php
EnvKit::configure()->macro('toggle', function (string $key) {
    return $this->set($key, $this->getBool($key) ? 'false' : 'true');
});

EnvKit::toggle('APP_DEBUG');   // $this is bound to the EnvKit instance
```

## 3. Pipeline middleware

Inject a pipe into the commit pipeline (it runs after the built-in guards, before
the write). A pipe is any object with `handle($context, Closure $next)`:

```php
final class BlockEmptyValues
{
    public function handle($context, \Closure $next): mixed
    {
        foreach ($context->changedKeys() as $key) {
            if ($context->document->get($key) === '') {
                throw new \RuntimeException("Refusing to write empty [$key].");
            }
        }
        return $next($context);
    }
}

EnvKit::configure()->pushMutationMiddleware(new BlockEmptyValues);
```

## 4. The `EnvKitManager` driver registry

For *named, config-selected* drivers (the canonical Laravel `Manager` pattern).
Register a cipher driver and select it via `config('env-kit.encryption.driver')`:

```php
use Simtabi\Laranail\EnvKit\Headless\EnvKitManager;

app(EnvKitManager::class)->extend('vault', fn () => new VaultCipher);
// config/env-kit.php → 'encryption' => ['driver' => 'vault']
```

## 5. Container `extend()`

Everything binds to a contract, so you can decorate any collaborator:

```php
$this->app->extend(EnvKitInterface::class, fn ($env) => new LoggingEnvKit($env));
```

## Custom contracts you can implement

| Contract | For |
|----------|-----|
| `Contracts\WriterInterface` | A custom write strategy |
| `Contracts\AuditSinkInterface` | A custom audit destination |
| `Contracts\ValueCipherInterface` | A custom encryption backend |
| `Contracts\DoctorRuleInterface` | A custom health-check rule |
| `Contracts\PortFormatInterface` | A custom import/export format |

## Tag-based registration

As an alternative to the `configure()` DSL, register extensions by container **tag** — handy
when you prefer pure container wiring. EnvKit collects them at boot:

```php
$this->app->bind(MyRule::class);
$this->app->tag([MyRule::class], 'env-kit.doctor_rules');
// also: env-kit.port_formats, env-kit.audit_sinks, env-kit.observers
```

## Authorization & observers

Reshaping *who* may write and reacting to writes has its own page —
**[Authorization](authorization.md)** (the update gate, write observers, the Laravel-ability
bridge, and a "which seam do I use?" table).

## Pattern choices (why it's built this way)

- **Manager** (`EnvKitManager`) is used for **ciphers** — the one genuinely multi-driver
  concept. Writers use a single contract + decorator seam (`useWriter()` / container
  `extend()`); rules / sinks / formats / observers are additive collections (DSL **and** tags).
- **Contextual binding** (`when()->needs()->give()`) needs no package code — it is stock
  Laravel and works against EnvKit's contracts as-is.

## Testing against EnvKit

Swap the engine for an in-memory fake — no disk I/O:

```php
$fake = EnvKit::fake(['APP_NAME' => 'Acme']);
$service->rotateToken();             // calls EnvKit::set(...)
$fake->assertSet('API_TOKEN');
```

---

[← Docs index](../README.md#documentation)
