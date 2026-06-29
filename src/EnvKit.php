<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use Simtabi\Laranail\EnvKit\Headless\Backup\BackupFile;
use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Contracts\AuditSinkInterface;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Contracts\ValueCipherInterface;
use Simtabi\Laranail\EnvKit\Headless\Doctor\Diagnostic;
use Simtabi\Laranail\EnvKit\Headless\Doctor\Doctor;
use Simtabi\Laranail\EnvKit\Headless\Document\Entry\Setter;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Events\AfterRestore;
use Simtabi\Laranail\EnvKit\Headless\Events\BackupCreated;
use Simtabi\Laranail\EnvKit\Headless\Events\BeforeRestore;
use Simtabi\Laranail\EnvKit\Headless\Events\WriteRejected;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\BackupNotFoundException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\EncryptionException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\EnvKitException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\IntegrityException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\InvalidEnvironmentException;
use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitPipeline;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Audit;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Authorize;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Backup;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Notify;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Observe;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Verify;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\Pipes\Write;
use Simtabi\Laranail\EnvKit\Headless\Porter\Porter;
use Simtabi\Laranail\EnvKit\Headless\Security\EditableKeys;
use Simtabi\Laranail\EnvKit\Headless\Security\ProductionGuard;
use Simtabi\Laranail\EnvKit\Headless\Security\ProtectedKeys;
use Simtabi\Laranail\EnvKit\Headless\Security\SecretRedactor;
use Simtabi\Laranail\EnvKit\Headless\Session\EditSession;
use Simtabi\Laranail\EnvKit\Headless\Support\Interpolator;
use Simtabi\Laranail\EnvKit\Headless\Support\TypedAccessor;
use Simtabi\Laranail\EnvKit\Headless\Writer\AtomicEnvWriter;
use Simtabi\Laranail\EnvKit\Headless\Writer\IntegrityVerifier;

/**
 * The bound root service — the single instance the `EnvKit` facade, constructor
 * injection of {@see EnvKitInterface}, and the `env_kit()` helper all resolve.
 *
 * Reads hit the file directly (env files are tiny). Writes flow through an
 * {@see EditSession}/{@see CommitPipeline}; three persistence modes are exposed:
 * immediate one-shots (when `auto_commit`), `transaction()` batch, and `open()`
 * for a manually-saved staged session.
 */
final class EnvKit implements EnvKitInterface
{
    use Macroable;

    private ?EditSession $pending = null;

    private bool $allowProduction = false;

    /**
     * @param  list<string>  $protectedKeys
     * @param  list<string>  $editableKeys
     */
    public function __construct(
        private readonly string $path,
        private readonly bool $autoCommit,
        private readonly bool $autoBackup,
        private readonly bool $isProduction,
        private readonly bool $protectProduction,
        private readonly array $protectedKeys,
        private readonly array $editableKeys,
        private readonly BackupManager $backups,
        private readonly TypedAccessor $typed,
        private readonly Interpolator $interpolator,
        private readonly EnvKitConfigurator $configurator,
        private readonly AuditSinkInterface $auditSink,
        private readonly SecretRedactor $redactor,
        private readonly ?Dispatcher $events = null,
    ) {}

    /** The fluent runtime-configuration DSL (Open/Closed; §2A). */
    public function configure(): EnvKitConfigurator
    {
        return $this->configurator;
    }

    // --- Reads (the EnvKitInterface contract) -------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->document()->get($key) ?? $default;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        return $this->typed->string($this->document()->get($key), $default);
    }

    public function getBool(string $key, ?bool $default = null): ?bool
    {
        return $this->typed->bool($this->document()->get($key), $default);
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        return $this->typed->int($this->document()->get($key), $default);
    }

    public function getFloat(string $key, ?float $default = null): ?float
    {
        return $this->typed->float($this->document()->get($key), $default);
    }

    /**
     * @param  array<int|string, mixed>|null  $default
     * @return array<int|string, mixed>|null
     */
    public function getArray(string $key, ?array $default = null): ?array
    {
        return $this->typed->array($this->document()->get($key), $default);
    }

    public function getJson(string $key, mixed $default = null): mixed
    {
        return $this->typed->json($this->document()->get($key), $default);
    }

    public function has(string $key): bool
    {
        return $this->document()->has($key);
    }

    public function missing(string $key): bool
    {
        return ! $this->document()->has($key);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->document()->toArray();
    }

    /** @return list<string> */
    public function keys(): array
    {
        return $this->document()->keys();
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    /** @return array<string, string> */
    public function group(string $prefix): array
    {
        $needle = rtrim($prefix, '_').'_';

        return array_filter(
            $this->all(),
            static fn (string $key): bool => str_starts_with($key, $needle),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function raw(): string
    {
        return $this->document()->render();
    }

    public function interpolated(string $key, mixed $default = null): mixed
    {
        $value = $this->document()->get($key);

        return $value === null ? $default : $this->interpolator->resolve($value, $this->all());
    }

    /** @return Collection<int, Setter> */
    public function entries(): Collection
    {
        return Collection::make($this->document()->setters());
    }

    // --- Writes -------------------------------------------------------------

    /** @param array{export?: bool} $options */
    public function set(string $key, string $value, array $options = []): static
    {
        return $this->mutate(fn (EditSession $s) => $s->set($key, $value, $options['export'] ?? false));
    }

    public function forget(string $key): static
    {
        return $this->mutate(fn (EditSession $s) => $s->forget($key));
    }

    public function rename(string $from, string $to): static
    {
        return $this->mutate(fn (EditSession $s) => $s->rename($from, $to));
    }

    /** @param array<string, string> $pairs */
    public function setMany(array $pairs): static
    {
        return $this->mutate(function (EditSession $s) use ($pairs): void {
            foreach ($pairs as $key => $value) {
                $s->set($key, $value);
            }
        });
    }

    /** Run a batch of mutations and commit them as ONE transaction. */
    public function transaction(Closure $callback): mixed
    {
        $session = $this->newSession();

        if ($this->allowProduction) {
            $session->allowProduction();
            $this->allowProduction = false;
        }

        $result = $callback($session);
        $session->save();

        return $result;
    }

    /** A staged session the caller drives and saves manually. */
    public function open(): EditSession
    {
        return $this->newSession();
    }

    /** Commit a pending (auto_commit=false) staged batch. */
    public function save(): static
    {
        if ($this->pending !== null) {
            if ($this->allowProduction) {
                $this->pending->allowProduction();
            }

            $this->pending->save();
            $this->pending = null;
            $this->allowProduction = false;
        }

        return $this;
    }

    /** Permit the next commit to run in production. */
    public function allowProduction(): static
    {
        $this->allowProduction = true;

        return $this;
    }

    public function backups(): BackupManager
    {
        return $this->backups;
    }

    /** The .env path this instance operates on. */
    public function path(): string
    {
        return $this->path;
    }

    /** Snapshot the current file. Returns null when there is nothing to back up. */
    public function backup(): ?BackupFile
    {
        $backup = $this->backups->backup($this->path);

        if ($backup !== null) {
            $this->events?->dispatch(new BackupCreated($this->path, $backup, $this->configurator->resolveActor()));
        }

        return $backup;
    }

    /**
     * Restore a named backup over the current file. Runs the same Backup → Write →
     * Verify → Audit pipeline as a normal commit (so it is atomic, rolls back on a
     * verify failure, and is audited + broadcast), but skips key validation and the
     * protected-key guard — a restore reinstates a known-good snapshot. The
     * production guard still applies.
     */
    public function restore(string $name): BackupFile
    {
        $backup = $this->backups->find($name);
        if ($backup === null) {
            throw BackupNotFoundException::named($name);
        }

        $contents = @file_get_contents($backup->path);
        if ($contents === false) {
            throw BackupNotFoundException::named($name);
        }

        (new ProductionGuard($this->isProduction, $this->protectProduction))->guard($this->allowProduction);

        $actor = $this->configurator->resolveActor();
        $this->events?->dispatch(new BeforeRestore($this->path, $name, $actor));

        $writer = $this->configurator->writer() ?? new AtomicEnvWriter;
        $context = new CommitContext(
            $this->path,
            EnvDocument::parse($contents),
            $this->document(),
            $this->allowProduction,
            actor: $actor,
            operation: 'restore',
        );

        $observers = $this->configurator->observers();

        try {
            (new Pipeline)
                ->send($context)
                ->through(array_values(array_filter([
                    new Authorize($this->configurator->updateGate(), $this->isProduction),
                    $observers === [] ? null : new Observe($observers, $this->isProduction),
                    new Notify($this->redactor, $this->events),
                    new Backup($this->autoBackup ? $this->backups : null, $this->events),
                    new Write($writer),
                    new Verify($writer, new IntegrityVerifier, $this->events),
                    $this->auditPipe(),
                ])))
                ->thenReturn();
        } catch (IntegrityException $e) {
            throw $e; // a post-write rollback already emitted WriteRolledBack
        } catch (EnvKitException $e) {
            $this->events?->dispatch(new WriteRejected($this->path, $e->envKitReason(), $context->changedKeys(), $actor));
            throw $e;
        } finally {
            // Reset the override even if the restore pipeline throws (no leak to the next op).
            $this->allowProduction = false;
        }

        $this->events?->dispatch(new AfterRestore($this->path, $backup, $actor));

        return $backup;
    }

    // --- Encryption-at-rest (per value; never Laravel's whole-file env:encrypt) --

    /** Encrypt an existing key's value in place (no-op if absent or already encrypted). */
    public function encrypt(string $key): static
    {
        return $this->mutate(function (EditSession $s) use ($key): void {
            $value = $this->document()->get($key);
            if ($value !== null && ! $this->cipher()->isEncrypted($value)) {
                $s->set($key, $this->cipher()->encrypt($value));
            }
        });
    }

    /** Decrypt an existing key's value in place (no-op if absent or not encrypted). */
    public function decrypt(string $key): static
    {
        return $this->mutate(function (EditSession $s) use ($key): void {
            $value = $this->document()->get($key);
            if ($value !== null && $this->cipher()->isEncrypted($value)) {
                $s->set($key, $this->cipher()->decrypt($value));
            }
        });
    }

    /** Set a key to the encrypted form of a plaintext value. */
    public function setEncrypted(string $key, string $value): static
    {
        return $this->mutate(fn (EditSession $s) => $s->set($key, $this->cipher()->encrypt($value)));
    }

    /** Read a key, decrypting it when stored encrypted. */
    public function getDecrypted(string $key, ?string $default = null): ?string
    {
        $value = $this->document()->get($key);
        if ($value === null) {
            return $default;
        }

        return $this->cipher()->isEncrypted($value) ? $this->cipher()->decrypt($value) : $value;
    }

    private function cipher(): ValueCipherInterface
    {
        return $this->configurator->cipher() ?? throw EncryptionException::notConfigured();
    }

    // --- Diagnostics --------------------------------------------------------

    /**
     * Run the doctor health-check (built-in rules + any configure()-registered).
     *
     * @return list<Diagnostic>
     */
    public function inspect(): array
    {
        return Doctor::withDefaults(...$this->configurator->doctorRules())->inspect($this->document());
    }

    /**
     * Compare this file against another, by key.
     *
     * @return array{only_here: list<string>, only_there: list<string>, changed: list<string>}
     */
    public function diff(string $against): array
    {
        $here = $this->all();
        $there = EnvDocument::parse(is_file($against) ? (string) @file_get_contents($against) : '')->toArray();

        $changed = [];
        foreach ($here as $key => $value) {
            if (\array_key_exists($key, $there) && $there[$key] !== $value) {
                $changed[] = $key;
            }
        }

        return [
            'only_here' => array_values(array_diff(array_keys($here), array_keys($there))),
            'only_there' => array_values(array_diff(array_keys($there), array_keys($here))),
            'changed' => $changed,
        ];
    }

    // --- Import / export ----------------------------------------------------

    /** Serialize all values in the given format (default json). */
    public function export(string $format = 'json'): string
    {
        return $this->porter()->format($format)->export($this->all());
    }

    /** Parse $content in the given format and apply every key (one commit). */
    public function import(string $content, string $format = 'json'): static
    {
        $values = $this->porter()->format($format)->import($content);

        return $this->mutate(function (EditSession $s) use ($values): void {
            foreach ($values as $key => $value) {
                $s->set($key, $value);
            }
        });
    }

    private function porter(): Porter
    {
        return Porter::withDefaults(...$this->configurator->portFormats());
    }

    /** A new EnvKit bound to a different .env file (same config). */
    public function file(string $path): self
    {
        return new self(
            $path,
            $this->autoCommit,
            $this->autoBackup,
            $this->isProduction,
            $this->protectProduction,
            $this->protectedKeys,
            $this->editableKeys,
            $this->backups,
            $this->typed,
            $this->interpolator,
            $this->configurator,
            $this->auditSink,
            $this->redactor,
            $this->events,
        );
    }

    /** Bind to a sibling `.env.{environment}` file. */
    public function on(string $environment): self
    {
        // Guard against path traversal — the name becomes part of a filename.
        if (preg_match('/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*$/', $environment) !== 1) {
            throw InvalidEnvironmentException::for($environment);
        }

        return $this->file(\dirname($this->path).'/.env.'.$environment);
    }

    public function isDirty(): bool
    {
        return $this->pending?->isDirty() ?? false;
    }

    // --- Internals ----------------------------------------------------------

    private function document(): EnvDocument
    {
        return EnvDocument::parse(is_file($this->path) ? (string) @file_get_contents($this->path) : '');
    }

    private function session(): EditSession
    {
        return $this->pending ??= $this->newSession();
    }

    private function newSession(): EditSession
    {
        return EditSession::open($this->path, pipeline: $this->pipeline(), actor: $this->configurator->resolveActor(), events: $this->events);
    }

    private function auditPipe(): Audit
    {
        return new Audit(
            [$this->auditSink, ...$this->configurator->auditSinks()],
            $this->redactor,
            $this->events,
        );
    }

    private function pipeline(): CommitPipeline
    {
        $audit = $this->auditPipe();

        $pipeline = CommitPipeline::default(
            writer: $this->configurator->writer(),
            backups: $this->autoBackup ? $this->backups : null,
            production: new ProductionGuard($this->isProduction, $this->protectProduction),
            protected: new ProtectedKeys([...$this->protectedKeys, ...$this->configurator->protectedKeys()]),
            audit: $audit,
            editable: new EditableKeys([...$this->editableKeys, ...$this->configurator->editableKeys()]),
            events: $this->events,
        );

        $pipeline->authorize(new Authorize($this->configurator->updateGate(), $this->isProduction));
        $pipeline->beforeWrite(new Notify($this->redactor, $this->events));

        if (($observers = $this->configurator->observers()) !== []) {
            $pipeline->observe(new Observe($observers, $this->isProduction));
        }

        foreach ($this->configurator->mutationMiddleware() as $pipe) {
            $pipeline->push($pipe);
        }

        return $pipeline;
    }

    private function mutate(Closure $apply): static
    {
        $apply($this->session());

        if ($this->autoCommit) {
            try {
                $this->save();
            } catch (\Throwable $e) {
                // An immediate commit is atomic: a failed write (guard, validation,
                // conflict, …) must not leave the staged change in the pending
                // session, where it would poison the next operation.
                $this->pending = null;
                $this->allowProduction = false;
                throw $e;
            }
        }

        return $this;
    }
}
