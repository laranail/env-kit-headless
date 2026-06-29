# EnvKit Headless

> A view-less Laravel engine for reading and **safely editing** `.env` files — one
> code path behind a programmatic API, a CLI, and an interactive TUI.

[![Tests](https://github.com/laranail/env-kit-headless/actions/workflows/ci.yml/badge.svg)](https://github.com/laranail/env-kit-headless/actions)
[![Packagist](https://img.shields.io/packagist/v/laranail/env-kit-headless)](https://packagist.org/packages/laranail/env-kit-headless)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

`laranail/env-kit-headless` is the engine of the **EnvKit** family. Every mutation
flows through one transactional, atomic, guarded, audited commit path — whether you
call it from a controller, an Artisan command, or the interactive editor. It never
renders HTML or handles HTTP; the [`env-kit-webui`](https://github.com/laranail/env-kit-webui)
companion drives this engine for the web.

```php
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;

EnvKit::set('MAIL_HOST', 'smtp.acme.test');   // atomic · backed-up · audited
$debug = EnvKit::getBool('APP_DEBUG', false);  // typed read
```

## Why

Editing `.env` from code is deceptively risky: a half-written file, a clobbered
concurrent edit, a leaked secret in a log, or an accidental production write. EnvKit
makes those failure modes impossible by construction:

- **Format-preserving** — comments, blank lines, quoting, ordering, EOL and BOM all
  survive a round-trip (conformant with `vlucas/phpdotenv`).
- **Atomic & self-healing** — write to a temp file, `fsync`, rename; verify the result
  and **auto-rollback** on mismatch. Optimistic concurrency rejects clobbering writes.
- **Secret-safe** — secret-shaped values are redacted from logs, exceptions, audit
  records and events. Optional per-value **encryption-at-rest**.
- **Guarded** — production-write protection and a layered protected/hidden/editable key
  policy on *every* surface (programmatic, CLI, TUI), plus a pluggable
  [authorization gate + write observers](docs/authorization.md).
- **Observable** — a full [lifecycle event set](docs/events.md) (redacted, actor-attributed)
  and opt-in [operator notifications](docs/notifications.md).
- **Open/Closed** — reshape the engine from your own service provider with zero source
  edits (fluent DSL, Macroable, driver registry, pipeline middleware, container tags).

## Install

```bash
composer require laranail/env-kit-headless
```

Requires **PHP 8.4.1+** and **Laravel 13**. The service provider and `EnvKit` facade
auto-register. Publish the config if you want to tune it:

```bash
php artisan vendor:publish --tag=env-kit-config
```

See **[docs/installation.md](docs/installation.md)** for details.

## The three faces

**Programmatic** — Facade, DI of `EnvKitInterface`, or the `env_kit()` helper:

```php
EnvKit::set('FEATURE_X', 'true');
EnvKit::transaction(fn ($s) => $s->set('A', '1')->set('B', '2')); // one commit
$value = env_kit('APP_NAME', 'Laravel');
```

**CLI** — 14 commands under `laranail::env-kit-headless.*`, each with a short `env:*` alias:

```bash
php artisan env:set MAIL_HOST=smtp.acme.test
php artisan env:get APP_NAME
php artisan env:doctor          # health-check rules
php artisan env:export --format=json --output=env.json
```

**TUI** — an interactive editor on `laravel/prompts`:

```bash
php artisan env:edit
```

## Configuration

```php
// config/env-kit.php (excerpt)
'auto_commit'       => true,                       // immediate writes
'auto_backup'       => true,                       // snapshot before each write
'protect_production'=> true,                       // block prod writes unless overridden
'protected_keys'    => ['APP_KEY', 'DB_PASSWORD'], // never writable
'hidden_keys'       => ['*_PASSWORD', '*_SECRET'], // masked in listings
'audit'             => ['enabled' => true],
'encryption'        => ['driver' => 'laravel'],
```

Full reference: **[docs/configuration.md](docs/configuration.md)**.

<a id="documentation"></a>

## Documentation

| Page | What it covers |
|------|----------------|
| [Installation](docs/installation.md) | Requirements, install, publishing config, the `ENV_KIT_PATH` override |
| [Configuration](docs/configuration.md) | Every config key, layered key policy, schema precedence |
| [Architecture](docs/architecture.md) | The document model, commit pipeline, atomic writer, security core |
| [Extending](docs/extending.md) | `configure()` DSL, Macroable, `EnvKitManager`, pipeline middleware, custom drivers, tag-based registration |
| [Authorization](docs/authorization.md) | The update gate + write observers, the Laravel-ability bridge, the "which seam" table |
| [Events](docs/events.md) | The lifecycle event table, actor attribution, listening |
| [Notifications](docs/notifications.md) | Opt-in operator alerts, channels, testing |
| [Release](docs/release.md) | Versioning, the release workflow, trusted publishing |
| [Programmatic API](docs/tools/programmatic-api.md) | Reads, typed getters, the three write modes, `EnvKit::fake()` |
| [CLI](docs/tools/cli.md) | All 14 commands, exit-code contract, `--file` / `--force-production` |
| [TUI](docs/tools/tui.md) | The interactive `env:edit` editor |
| [Encryption](docs/tools/encryption.md) | Per-value encryption-at-rest, cipher drivers |
| [Doctor](docs/tools/doctor.md) | Health-check rules and writing your own |
| [Import / Export](docs/tools/import-export.md) | The Porter, JSON & CSV formats, custom formats |
| [Audit & Events](docs/tools/audit-events.md) | Audit sinks, the `AfterWrite` event, redaction |

The rendered docs live at
**<https://opensource.simtabi.com/env-kit-headless/docs/>**.

## Security

EnvKit handles secrets. Secret-shaped values never reach logs, exception messages,
audit records, or events. Found a vulnerability? See **[SECURITY.md](SECURITY.md)** —
report privately to `opensource@simtabi.com`.

## Contributing

PRs welcome — see **[CONTRIBUTING.md](CONTRIBUTING.md)**. Run `vendor/bin/pest`,
`vendor/bin/phpstan analyse` (level 9), and `vendor/bin/pint` before submitting.

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
