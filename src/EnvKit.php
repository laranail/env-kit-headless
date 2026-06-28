<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Document\Entry\Setter;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitPipeline;
use Simtabi\Laranail\EnvKit\Headless\Security\ProductionGuard;
use Simtabi\Laranail\EnvKit\Headless\Security\ProtectedKeys;
use Simtabi\Laranail\EnvKit\Headless\Session\EditSession;
use Simtabi\Laranail\EnvKit\Headless\Support\Interpolator;
use Simtabi\Laranail\EnvKit\Headless\Support\TypedAccessor;

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

    /** @param list<string> $protectedKeys */
    public function __construct(
        private readonly string $path,
        private readonly bool $autoCommit,
        private readonly bool $autoBackup,
        private readonly bool $isProduction,
        private readonly bool $protectProduction,
        private readonly array $protectedKeys,
        private readonly BackupManager $backups,
        private readonly TypedAccessor $typed,
        private readonly Interpolator $interpolator,
        private readonly EnvKitConfigurator $configurator,
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
            $this->backups,
            $this->typed,
            $this->interpolator,
            $this->configurator,
        );
    }

    /** Bind to a sibling `.env.{environment}` file. */
    public function on(string $environment): self
    {
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
        return EditSession::open($this->path, pipeline: $this->pipeline());
    }

    private function pipeline(): CommitPipeline
    {
        $pipeline = CommitPipeline::default(
            writer: $this->configurator->writer(),
            backups: $this->autoBackup ? $this->backups : null,
            production: new ProductionGuard($this->isProduction, $this->protectProduction),
            protected: new ProtectedKeys([...$this->protectedKeys, ...$this->configurator->protectedKeys()]),
        );

        foreach ($this->configurator->mutationMiddleware() as $pipe) {
            $pipeline->push($pipe);
        }

        return $pipeline;
    }

    private function mutate(Closure $apply): static
    {
        $apply($this->session());

        if ($this->autoCommit) {
            $this->save();
        }

        return $this;
    }
}
