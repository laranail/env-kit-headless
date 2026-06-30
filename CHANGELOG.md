# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0] - 2026-06-29

Completes the v1.0 feature surface — the last breadth items from the consolidation plan.

### Added

- **Import/export formats** — Porter now ships `dotenv` and `yaml` formats alongside json/csv
  (`yaml` is registered only when `symfony/yaml` is installed — added to `suggest`).
- **`env:history`** — a redacted audit-log viewer (`--limit`; shows who changed which keys, when —
  never values), backed by a new `Audit\HistoryReader`.
- **`env:docs`** — renders the resolved validation schema as a Markdown table (`--output`), via
  `Support\DocsGenerator` + `EnvSchema::describe()`.
- **Comment / blank-line authoring** — `EnvKit::addComment()` and `addEmptyLine()` (new
  `EnvDocument::withComment()` / `withEmptyLine()` + `EditSession` methods).
- **jackiedo/dotenv-editor compatibility** — drop-in aliases on EnvKit (`getValue`, `setKey`,
  `setKeys`, `deleteKey`, `deleteKeys`, `keyExists`, `getKeys`, `getEntries`, `getContent`) and a
  `Compat\DotenvEditor` facade that resolves the same bound instance, for one-line migration.
- **Production banner** — every CLI command prints a one-line `PRODUCTION` warning when
  `APP_ENV=production` (`Security\ProductionBanner`; the WebUI panel already shows its own).

### Notes

- All additions are backward-compatible; the `EnvKitFake` seam mirrors the new surface.

## [0.3.0] - 2026-06-29

A feature-completeness release benchmarked against the full Laravel `.env`-editor ecosystem —
closing the remaining breadth gaps so EnvKit is a strict superset of every mined package.

### Added

- **Write-API completeness** — `update()` (existing-only, throws `KeyNotFoundException`),
  `setOrUpdate()`, `setIfMissing()`, `forgetMany()`, `setExport()` (explicit `export ` toggle, via
  new `EnvDocument::withExport()` / `EditSession::setExport()`), and `entry()` (single-key setter).
- **Schema validation** — a fluent `EnvKit::schema()` builder (`required/string/integer/boolean/
  number/in/regex/url/email` + `define()` for `'required|integer|in:a,b'` specs), `validate()` →
  `ValidationResult`, `isValid()`, `assertValid()` (throws `SchemaException`), a `MatchesEnvSchema`
  validation rule for FormRequests, and a config-seeded `env-kit.schema` (runtime rules merge in).
  `env:validate` now checks the schema in addition to well-formedness.
- **`.env.example` sync** — `missingFromExample()` / `syncFromExample()` / `examplePath()` and the
  `env:sync` + `env:check` commands (`env:check` exits non-zero on drift — CI-friendly).
- **Secret generators** — `EnvKit::generate()` (token/hex/base64/app_key) + the `env:generate`
  command (`--bytes`, `--set`).
- **Named backups + lifecycle** — `EnvKit::backup(?string $name)`, `backups()->delete()`,
  `backups()->deleteOlderThan()`, and the `env:backup-delete` command.
- **Per-value encryption CLI** — `env:encrypt-value` / `env:decrypt-value` (deliberately NOT
  aliased to Laravel's whole-file core `env:encrypt`/`env:decrypt`, which EnvKit never shadows).

### Notes

- All additions are backward-compatible. The `EnvKitFake` test seam mirrors the new surface.

## [0.2.1] - 2026-06-29

### Fixed

- `configure()->limitValueLength()` (the DSL) now takes precedence over the `env-kit.limits`
  config instead of being overwritten on each resolution; the config value also accepts a
  numeric string.
- A production-blocked `restore()` now emits `WriteRejected` and resets the override via
  `finally`, consistent with the normal write path.

## [0.2.0] - 2026-06-29

### Added

- **Secure-update authorization.** A pluggable `UpdateGateInterface` over every write +
  restore, with a shipped permissive `DefaultUpdateGate` bridged to a Laravel
  `env-kit.update` ability (`LaravelAbilityGate`). Swap or wrap it at runtime via
  `configure()->useUpdateGate()` / `decorateUpdateGate()` (decorators compose,
  last-outermost).
- **Write observers** (`WriteObserverInterface` + `AbstractWriteObserver`): Eloquent-style
  `saving`/`saved`/`creating`/`updating`/`deleting`/`restoring`/`restored`; a `false`/deny
  return vetoes. Register via `configure()->observe()` or the `env-kit.observers` tag.
- **Lifecycle events**: `BeforeWrite`, `WriteRejected`, `BackupCreated`,
  `BeforeRestore`/`AfterRestore`, `ConflictDetected`, `WriteRolledBack` (all redacted +
  actor-attributed; `AfterWrite` now carries the actor + operation).
- **Opt-in notifications** (`env-kit.notifications`): a queueable listener turns chosen
  events into Laravel on-demand notifications (mail/slack/…), with `production_only`.
- **Audit actor attribution**: the audit trail + events record who made the change
  (`configure()->resolveActorUsing()` / `env-kit.audit.actor`).
- **Tag-based registration** for doctor rules / port formats / audit sinks / observers
  (`env-kit.doctor_rules`, …).

### Fixed (security)

- `EnvKit::on()` rejects path-traversal environment names.
- Every write is sanitized at the single chokepoint (NUL rejected, control chars stripped);
  added a configurable value-length cap (`env-kit.limits`).
- New `.env` files and backups are written `0600` (owner-only); backup dir `0700`.
- `restore()` no longer leaks the production override to the next op on failure.

### Changed

- New deps: `illuminate/auth` (Gate integration), `illuminate/notifications`.

## [0.1.1] - 2026-06-29

### Added

- **`editable_keys` allowlist** — now enforced on every surface (set/delete/rename of a
  non-allowlisted key raises `NotEditableException`; CLI exit 3, WebUI 403). Configure at
  runtime with `EnvKit::configure()->onlyEditable([...])`.

### Fixed

- **Secret masking honours `config('env-kit.hidden_keys')`** in `env:list` and the WebUI
  (previously they used the built-in patterns only, so a custom-hidden key could leak).
- **`restore()` is now audited** and dispatches `AfterWrite` — it runs the real
  Backup → Write → Verify → Audit pipeline (atomic, rolls back on a verify failure).
- **Immediate (`auto_commit`) writes are truly atomic on failure** — a failed commit no
  longer leaves the staged change in the pending session to corrupt the next operation.
- **TUI `--force-production`** now persists across multiple edits in one session (the
  override is re-armed per action instead of being consumed once).
- **`EnvKit::fake()` implements the full surface** (file/on/encrypt/getDecrypted/inspect/
  diff/export/import/configure/backup/restore/…), so faked code never hits a missing method.

### Changed

- Removed the dead `interpolation.resolve` config flag (use `EnvKit::interpolated()`).
- Removed the misleading `suggest` entries (a DB audit sink / AWS KMS driver that were
  never shipped — the `AuditSinkInterface` / `ValueCipherInterface` extension points remain).
- Switched mutation testing from Infection (which has no Pest-suite support) to
  Pest's native `--mutate`; added a `composer mutate` script and a scheduled CI gate.

### Tests

- Hardened the suite against mutation testing to **~87% covered-MSI** (engine-core
  classes individually 85–100%; the remaining survivors are equivalent or
  tooling-unreachable mutants). Test count grew from 156 to 350+.

## [0.1.0] - 2026-06-28

### Added

- Immutable, comment-preserving `.env` document model with phpdotenv-compatible
  round-tripping (whitespace, comments, quoting and ordering survive a read/write
  cycle).
- Atomic writes with optimistic concurrency control, post-write integrity
  verification and automatic rollback on failure.
- Transactional `EditSession` that batches mutations and commits them as a single
  atomic unit.
- Rich programmatic API: `EnvKit` Facade, DI of the `EnvKitInterface` contract,
  and the `env_kit()` helper; typed getters; and three persistence modes —
  immediate auto-commit, `transaction()`, and staged `open()`.
- 14 Artisan commands under the `laranail::env-kit-headless.*` names, each with an
  `env:*` alias: `set`, `get`, `unset`, `keys`, `list`, `rename`, `backup`,
  `backups`, `restore`, `validate`, `edit`, `doctor`, `diff`, `export`, `import`.
- Interactive TUI (`env:edit`) built on `laravel/prompts`.
- Runtime extensibility: the `EnvKit::configure()` DSL, `Macroable` support, and
  the `EnvKitManager` cipher driver registry.
- Pluggable audit sinks and an `AfterWrite` lifecycle event (secret-redacted).
- Per-value encryption-at-rest that never re-binds Laravel's `env:encrypt`.
- Secret redaction across logs and exception messages.
- Layered key policy with protected and hidden keys.
- Production-write guard to prevent accidental edits in production.
- Timestamped backups with restore.
- Doctor health-check rules.
- Key and value diffing.
- JSON and CSV import/export via the Porter.
- `EnvKit::fake()` test seam.

[Unreleased]: https://github.com/laranail/env-kit-headless/compare/v0.2.1...HEAD
[0.2.1]: https://github.com/laranail/env-kit-headless/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/laranail/env-kit-headless/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/laranail/env-kit-headless/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/laranail/env-kit-headless/releases/tag/v0.1.0
