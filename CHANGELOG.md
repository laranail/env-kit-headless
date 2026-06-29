# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/laranail/env-kit-headless/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/laranail/env-kit-headless/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/laranail/env-kit-headless/releases/tag/v0.1.0
