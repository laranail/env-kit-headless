# CLI

EnvKit ships 21 Artisan commands. Each has a fully-qualified
`laranail::env-kit-headless.<cmd>` name and a short `env:<cmd>` alias — use either.

## Commands

| Command | Alias | Purpose |
|---------|-------|---------|
| `…​.set {key} {value?}` | `env:set` | Set/create a key. Accepts `KEY VALUE` or `KEY=VALUE`. `--export` adds the export prefix. |
| `…​.get {key}` | `env:get` | Print a value (`--default=` when absent). |
| `…​.unset {key}` | `env:unset` | Remove a key. |
| `…​.keys` | `env:keys` | List every key. |
| `…​.list` | `env:list` | List `KEY=VALUE`, secrets masked (`--reveal` to show). |
| `…​.rename {from} {to}` | `env:rename` | Rename a key in place. |
| `…​.backup` | `env:backup` | Snapshot the file. |
| `…​.backups` | `env:backups` | List backups (newest first). |
| `…​.backup-delete {name?}` | `env:backup-delete` | Delete a named backup, or `--older-than=DAYS` to prune by age. |
| `…​.restore {name?}` | `env:restore` | Restore a backup (latest if unnamed). |
| `…​.validate` | `env:validate` | Check every key/value for well-formedness **and** the configured schema. |
| `…​.sync` | `env:sync` | Add keys present in `.env.example` but missing from `.env` (`--example=`). |
| `…​.check` | `env:check` | List missing example keys; non-zero exit on drift (`--example=`, CI-friendly). |
| `…​.generate {type=token}` | `env:generate` | Generate a secret (`--bytes=`); `--set=KEY` writes it to a key. |
| `…​.encrypt-value {key}` | `env:encrypt-value` | Encrypt a single key's value in place. |
| `…​.decrypt-value {key}` | `env:decrypt-value` | Decrypt a single key's value back to plaintext. |
| `…​.edit` | `env:edit` | Interactive TUI editor (see [TUI](tui.md)). |
| `…​.doctor` | `env:doctor` | Run health-check rules (see [Doctor](doctor.md)). |
| `…​.diff {against}` | `env:diff` | Compare against another file, by key. |
| `…​.export` | `env:export` | Export as `--format=json\|csv` to stdout or `--output=`. |
| `…​.import {source}` | `env:import` | Import from a json/csv file. |

```bash
php artisan env:set MAIL_HOST=smtp.acme.test
php artisan env:get APP_NAME --default=Laravel
php artisan env:list --reveal
php artisan env:export --format=json --output=storage/env.json
php artisan env:import storage/env.json
php artisan env:restore                 # latest backup
php artisan env:backup-delete --older-than=30   # prune backups older than 30 days
php artisan env:check                   # CI: exit 3 if .env drifts from .env.example
php artisan env:sync                    # add the missing example keys
php artisan env:generate app_key --set=APP_KEY  # generate a secret and write it
php artisan env:encrypt-value STRIPE_SECRET     # encrypt one value in place
```

## Per-value encryption vs. Laravel core

`env:encrypt-value` / `env:decrypt-value` encrypt a **single value** in place
(read it back with `EnvKit::getDecrypted()` — see [Encryption](encryption.md)).
They are deliberately **not** aliased to Laravel's core `env:encrypt` /
`env:decrypt`, which encrypt the **whole file** — EnvKit never shadows those.

## Global options

- `--file=PATH` — operate on a custom `.env` file instead of the configured one.
- `--force-production` — permit the write in production (on write commands).

## Exit codes

Commands return a stable contract, so scripts and CI can branch on them:

| Code | Meaning |
|------|---------|
| `0` | Success |
| `2` | Usage error (bad arguments) |
| `3` | Validation / policy failure (invalid key, protected key, production guard) |
| `4` | Conflict (file changed underneath the edit) |
| `5` | I/O error (not writable, lock failure, integrity mismatch) |

```bash
php artisan env:set APP_NAME=Acme || echo "failed with code $?"
```

## Why `::` in the name?

The `laranail::env-kit-headless.*` shape mirrors the package's composer slug so the
source of a command is unambiguous across the laranail family. The `::` separator
is enabled by the command base from `laranail/console`; the short `env:*` aliases
are always available too.

---

[← Docs index](../../README.md#documentation)
