# Configuration

All settings live in `config/env-kit.php`. Every key has a safe default; you only
need to publish and edit the file to change behaviour.

## Reference

| Key | Default | Purpose |
|-----|---------|---------|
| `path` | `base_path('.env')` | The file EnvKit edits (env: `ENV_KIT_PATH`). |
| `auto_commit` | `true` | When `true`, `set()/forget()/rename()` commit immediately. When `false`, they stage onto an implicit session you flush with `EnvKit::save()`. |
| `auto_backup` | `true` | Snapshot the file before each write. |
| `backup_path` | `storage_path('env-kit/backups')` | Where timestamped `.bak` files go. |
| `backup_retention` | `30` | Keep the newest N backups (`0` = keep all). |
| `protect_production` | `true` | Block writes in production unless explicitly overridden. |
| `protected_keys` | `['APP_KEY', 'DB_PASSWORD']` | Keys that can never be written. |
| `hidden_keys` | `['APP_KEY', '*_PASSWORD', '*_SECRET', '*_TOKEN']` | Glob patterns whose values are masked in listings/exports. |
| `editable_keys` | `[]` | Optional allowlist (supports wildcards). When non-empty, only matching keys may be written — enforced on **every** surface (programmatic, CLI, TUI, WebUI). |
| `interpolation.on_undefined` | `'empty'` | `'empty'` substitutes `''`; `'throw'` raises a `ValidationException`. Call `EnvKit::interpolated()` to resolve `${VAR}` on read (`get()` always returns the raw at-rest value). |
| `audit.enabled` | `true` | Record an audit trail of every commit. |
| `audit.path` | `storage_path('env-kit/audit.log')` | JSON-lines file for the default audit sink. |
| `encryption.driver` | `'laravel'` | The cipher driver for per-value encryption-at-rest. |

## Layered key policy

Three independent lists govern keys at different layers:

- **`protected_keys`** — a *write* guard. Attempting to change one raises
  `ProtectedKeyException` on every surface. Use it for `APP_KEY`, DB credentials,
  anything that must only change through a deliberate, separate process.
- **`editable_keys`** — a *write allowlist*. When non-empty, any write (set, delete,
  rename) to a key that matches no pattern raises `NotEditableException`. Empty = no
  restriction. Use it to lock the editable surface down to a known set.
- **`hidden_keys`** — a *display* guard. Matching values are masked (`••••`) in
  `env:list`, the web panel, and exports unless secrets are explicitly revealed.

All three can be extended at runtime — see [Extending](extending.md):

```php
EnvKit::configure()->protectKeys(['STRIPE_SECRET'])->onlyEditable(['APP_*', 'MAIL_*']);
```

## Production guard

When the app environment is `production` and `protect_production` is `true`, every
write is refused with `ProductionGuardException` (HTTP 403 in the web companion).
Override deliberately, per operation:

```php
EnvKit::allowProduction()->set('MAINTENANCE', 'true');   // programmatic
php artisan env:set MAINTENANCE=true --force-production   // CLI
```

A production warning banner is shown in the TUI and web panel.

## `${VAR}` interpolation

Values are stored literally and resolved on demand. Only the brace form is
resolved (never bare `$VAR`), matching phpdotenv:

```dotenv
DB_HOST=localhost
DB_DSN=pgsql://${DB_HOST}:5432
```

```php
EnvKit::get('DB_DSN');           // 'pgsql://${DB_HOST}:5432'  (literal, at rest)
EnvKit::interpolated('DB_DSN');  // 'pgsql://localhost:5432'   (resolved)
```

---

[← Docs index](../README.md#documentation)
