# BUILD-LOG — laranail/env-kit-headless

Append-only log of build progress. Each entry: what changed · tests covering it · what's still open.
Spec: `_scratch-files/dotenv-editor-consolidation-plan.md`. Process: the EnvKit build-runner prompt.

## Phase checklist

- [x] **Phase 0 — repo setup** — git init + identity, `.gitignore`/`.gitattributes`, BUILD-LOG.
- [x] **Phase 1 — discovery** — 26 repos cloned → `research/_src/` (gitignored); `research/INDEX.md` complete, all licenses ✅.
- [x] **Phase 2 — feature inventory** — 26 `research/<pkg>.FEATURES.md`; signatures re-verified vs source.
- [x] **Phase 3 — gap analysis** — `research/FEATURE_MATRIX.md` finalized, every row decided (no TBD).
- [~] **Phase 4 — build headless** — engine, EditSession, guardrails, CLI, TUI, audit, encryption, extensibility; full test regime green.
  - [x] slice 1 — document layer (Entry/Parser/Document/ValueFormatter) + round-trip & phpdotenv conformance.
  - [x] slice 2 — atomic writer + ConflictDetector + IntegrityVerifier + EditSession (+ exception base).
  - [x] slice 3 — security core (KeyValidator/ValueSanitizer/SecretRedactor/ProtectedKeys/ProductionGuard) + Rules.
  - [x] slice 2b — CommitPipeline (validate→guard→backup→write→verify) + BackupManager, wired into EditSession.
  - [x] slice 4 — root EnvKit service + facade + helper + service provider (programmatic API).
  - [x] slice 5 — CLI commands (set/get/unset/keys/list/rename) + namespaced names & env:* aliases.
  - [x] slice 5b — CLI backup/backups/restore/validate (+ EnvKit backup()/restore()/path(), BackupManager find()).
  - [ ] slice 5c — CLI diff/doctor/import/export.
  - [x] slice 7a — runtime extensibility: EnvKitConfigurator (configure() DSL) + Macroable on EnvKit.
  - [x] slice 7b — audit sinks (file/null) + AfterWrite event, wired into the commit pipeline (redacted).
  - [x] slice 7c-i — EnvKit::fake() test seam (in-memory EnvKitFake + assertions).
  - [ ] slice 7c-ii — encryption-at-rest · EnvKitManager driver registry.
  - [x] slice 6 — interactive TUI (env:edit) on laravel/prompts.
- [ ] **Phase 6 — docs** — README + docs/ set incl. `extending.md`.
- [ ] **Phase 7 — release** — only after explicit approval.

## Open items (from the approved Phase 1 plan)

- TUI engine fork (`symfony/tui` vs `laravel/prompts`) — **parked until Phase 3→4 gate**; do not require `symfony/tui` yet.
- `laranail/package-scaffolder` does not exist — decision 14 relaxed (manual scaffold).
- Regression tests authored **new** (no copied third-party test code); license-gated per INDEX.
- Infection start thresholds: MSI ≥ 85% / covered-MSI ≥ 90% on engine core.
- Confirm `vlucas/phpdotenv` constraint matches Laravel 13's during Phase 2.

## Log

### Phase 0 — repo setup (done 2026-06-28)
- `git init` (branch `main`) + GitHub-noreply identity in headless & webui.
- Added `.gitignore` (ignores `/research/_src/`, vendor, caches), `.gitattributes` (export-ignore
  research/tests/.github), `BUILD-LOG.md`. Committed scaffold baseline.

### Phase 1 — discovery (done 2026-06-28)
- Resolved Packagist metadata + shallow-cloned **26** mining repos into `research/_src/` (gitignored).
- `research/INDEX.md`: 27 rows (26 cloned + filament/spatie doc-inventoried). **All licenses ✅** (MIT,
  except `vlucas/phpdotenv` BSD-3-Clause). Only `encodia` flagged abandoned.
- Corrections logged: phpdotenv is BSD-3 + v5.6 (no v6); `jtant/laravel-env-sync` (not `juliantant`);
  jobmetric latest 2.2.1; `filament-general-settings` latest-by-date is the Filament-3 line (re-verify 3.x).
- `research/FEATURE_MATRIX.md` seeded (finalize in Phase 3).

### Phase 2 — feature inventory (done 2026-06-28)
- Wrote 26 `research/<pkg>.FEATURES.md` (3 parallel agents read cloned source). Signatures verified.
- Confirmed corrections (see INDEX "Phase 2 verified findings"): jackiedo `getValue` no-default;
  amdadulhaq service-not-facade; msztorc facade unbound; jobmetric LOCK_EX in-place (we do better);
  tamer-dev `env:read`; geo-sot options-array insertion; phpdotenv parse/escape model → locks §3B;
  filament-general-settings HEAD is FL5/3.x (DB-settings, not env editor); filament-edit-env guard is
  render-time only (our pipeline guard is stronger); fadllabanie auth is broken (anti-pattern).

### Phase 3 — gap analysis (done 2026-06-28)
- `research/FEATURE_MATRIX.md` finalized: every feature resolved (no TBD). ~22 propose / 14 adopt /
  10 keep / 9 merge / 5 drop. Headless = behavioral superset; drops = obsolete stacks + anti-patterns.
- **Phase 3→4 gate: needs the TUI-engine decision (`symfony/tui` vs `laravel/prompts`) + Infection
  thresholds before engine coding.**

### Phase 4 — build headless (in progress, 2026-06-28)
- Decisions: **TUI on `laranail/console`** (latest v1.0.0 → built on `laravel/prompts` + symfony/console
  8; `symfony/tui` is only suggested). Infection thresholds **MSI ≥ 85% / covered-MSI ≥ 90%**.
- `composer update` resolves & installs cleanly (Laravel 13, testbench 11.1, Pest 4.7, PHPStan 2.2,
  Infection 0.33.3, symfony/console 8.1, laravel/prompts 0.3.21, phpdotenv 5.6.3). Fixed: infection
  `^0.29 → ^0.33` (symfony/console 8 support); added `laranail/console` + `ext-mbstring`.
- **Slice 1 (document layer) green:** `Entry/{AbstractEntry,Setter,Comment,EmptyLine}`,
  `Document/{EnvParser,EnvDocument}`, `Support/ValueFormatter`, `Contracts/{EntryInterface,EnvKitInterface(read API)}`.
  Tests: 44 passing / 64 assertions — byte-for-byte round-trip (LF/CRLF/BOM/comments/empty/no-trailing-NL),
  encode↔decode property, phpdotenv-loadable conformance. PHPStan L9 clean; Pint clean.
- Spec refinement found in impl: a bare `=` does **not** force quoting (dotenv splits on first `=`) →
  updated §3B in the plan + ValueFormatter.
- **Slice 2 (atomic write + transaction) green:** `Exceptions/{EnvKitException + 5 subclasses}`,
  `Contracts/WriterInterface`, `Writer/{AtomicEnvWriter,IntegrityVerifier}` (LOCK_EX + tmpfile + fsync
  + rename; preserves mode), `Session/{ConflictDetector,EditSession}` (+ `EnvDocument::renamed`).
  Commit path: no-op-if-clean → optimistic-lock check → atomic write → integrity verify →
  auto-rollback. Tests: **55 passing** incl. a **real parallel-process concurrency test** (4 writers,
  reader never sees a partial file), rollback-on-failure, conflict detection, in-place rename. L9 + Pint clean.
- **Slice 3 (security/validation core) green:** `Security/{KeyValidator,ValueSanitizer,SecretRedactor,
  ProtectedKeys,ProductionGuard}`, reusable `Rules/{ValidEnvKey,ValidEnvValue}`, exceptions
  `Invalid{Key,Value}/ProtectedKey/ProductionGuard`. KeyValidator allows digits (`S3_BUCKET`);
  ValueSanitizer rejects NUL + strips control chars (keeps \t\n\r); redactor = length-preserving
  partial mask + key-pattern masking + message scrub; guards throw with override paths. **85 tests**
  incl. a secret-leak test (no raw value in exception messages). L9 + Pint clean.
- **Slice 2b (commit pipeline + backups) green:** `Backup/{BackupFile,BackupManager}` (timestamped,
  microsecond-ordered, count-retention; non-dotfile names), `Pipeline/{CommitContext,CommitPipeline}`
  + `Pipes/{ValidateKeys,Guard,Backup,Write,Verify}` (Illuminate Pipeline). `EditSession.save()`
  refactored to commit through the pipeline; `allowProduction()` added. Guards/validation/backup now
  enforce on the real save path; `push()` lets consumers add middleware. **93 tests** incl.
  production-block + override, protected-key refusal, invalid-key rejection, auto-backup snapshot,
  custom-middleware. L9 + Pint clean. Bug found & fixed: `.env`-prefixed backups were hidden dotfiles.
- **Slice 4 (programmatic API) green:** root `EnvKit` (implements `EnvKitInterface`) + `Facades/EnvKit`
  + `EnvKitServiceProvider` (on `laranail/package-tools`; `scoped` binding, lazy config) + `config/env-kit.php`
  + `Support/{TypedAccessor,Interpolator}` + `ValidationException`. Full read API (typed getters, group/
  only/except, `${VAR}` interpolation, entries) + three write modes (immediate `auto_commit`,
  `transaction()`, `open()`), `allowProduction()`, `backups()`. **107 tests** incl. Testbench feature
  tests (facade + DI + `env_kit()` helper, immediate write, transaction, interpolated reads, auto-backup).
  L9 + Pint clean. Note: provider needs `name('laranail/env-kit-headless')` (vendor/package) +
  `hasConfigFile('env-kit')`.
- **Slice 5 (CLI core) green:** `Console/AbstractEnvCommand` (extends Illuminate Command + `use
  SupportsNamespacedNames` for `laranail::env-kit-headless.*`; `$commandAliases` → `env:*`; exit-code
  contract; `--file` + `--force-production`) and `Console/{Set,Get,Unset,Keys,List,Rename}KeyCommand`
  (thin wrappers over the same `EnvKit` engine). Registered in `packageBooted()`. **114 tests** incl.
  console tests for both name forms, `KEY=VALUE` shorthand, mixed-form (exit 2), invalid-key (exit 3),
  get-with-default, unset, rename, and `env:list` secret masking (+ `--reveal`). L9 + Pint clean.
- **Slice 7a (runtime extensibility) green:** `Extension/EnvKitConfigurator` (the `EnvKit::configure()`
  DSL — `pushMutationMiddleware`/`protectKeys`/`useWriter`/`macro`, bound singleton) + `Macroable` on
  `EnvKit`; `pipeline()` merges the configurator's middleware/protected-keys/writer each commit. **118
  tests** incl. a consumer macro, a veto mutation-middleware, extra protected keys, and a custom writer
  — all applied from "consumer" code with zero source edits (Open/Closed). L9 + Pint clean.
- **Slice 7b (audit + events) green:** `Contracts/AuditSinkInterface`, `Audit/{AuditEvent,FileAuditSink
  (JSON-lines),NullAuditSink}`, `Events/AfterWrite`, `Pipeline/Pipes/Audit` (final pipe; records to all
  sinks + dispatches the event, **values redacted**, only on success). Default sink + redactor +
  dispatcher built lazily in the provider closure (honors test config + `Event::fake()`); consumers add
  sinks via `configure()->registerAuditSink()`. **123 tests** incl. redacted JSON-lines audit, AfterWrite
  redaction, sink fan-out, no-op-skips-audit. L9 + Pint clean.
- **Slice 7c-i (test seam) green:** `Testing/EnvKitFake` (implements the full `EnvKitInterface`
  in-memory, records writes, never touches disk) + `EnvKit::fake()` on the facade (swaps the binding).
  Assertions: `assertSet`/`assertForgotten`/`assertNothingChanged`. **126 tests** incl. in-memory
  read/record, no-disk-I/O-when-faked, and DI/`env_kit()` resolving the fake. L9 + Pint clean.
- **Slice 5b (CLI backup family + validate) green:** `Console/{Backup,BackupsList,Restore,Validate}Command`
  (+ `env:backup`/`env:backups`/`env:restore`/`env:validate`); `EnvKit::backup()`/`restore()`/`path()`
  convenience + `BackupManager::find()` + `BackupNotFoundException`. Restore is production-guarded and
  safety-backs-up first; validate runs every key through `KeyValidator` + every value through
  `ValueSanitizer::isClean`. **132 tests** incl. backup/list/restore round-trip, restore-with-no-backups
  (exit 3), clean validate, unsafe-value validate (exit 3). L9 + Pint clean.
- **Slice 6 (interactive TUI) green:** `Console/EditCommand` (`env:edit`) — a browse/edit loop on
  laravel/prompts (`select`/`text`/`confirm`, the UI layer of laranail/console). Pure orchestration over
  the engine (edit value / add / rename / delete-with-confirm); engine errors surface inline without
  breaking the loop; production banner. Tested with `expectsQuestion`/`expectsConfirmation` (Laravel
  routes prompts to the Symfony fallback under `runningUnitTests()`). **137 tests** incl. quit, edit,
  add, delete, and error-without-crash. L9 + Pint clean.
