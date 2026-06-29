<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Session;

use Illuminate\Contracts\Events\Dispatcher;
use Simtabi\Laranail\EnvKit\Headless\Contracts\WriterInterface;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Events\ConflictDetected;
use Simtabi\Laranail\EnvKit\Headless\Events\WriteRejected;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\ConflictException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\EnvKitException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\IntegrityException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\KeyNotFoundException;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;
use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitPipeline;
use Simtabi\Laranail\EnvKit\Headless\Security\ValueSanitizer;

/**
 * A transactional editing session over a single .env file.
 *
 * Stages mutations against an in-memory working document (reads reflect staged
 * changes — read-your-writes), then {@see save()} commits in one shot: no-op if
 * clean → optimistic-lock check → {@see CommitPipeline} (validate → guard →
 * backup → atomic write → verify → auto-rollback). The pipeline is the seam where
 * encryption, audit and consumer middleware attach.
 */
final class EditSession
{
    private EnvDocument $working;

    private bool $allowProduction = false;

    public function __construct(
        private readonly string $path,
        private readonly EnvDocument $original,
        private readonly string $fingerprint,
        private readonly ConflictDetector $conflicts,
        private readonly CommitPipeline $pipeline,
        private readonly ValueSanitizer $sanitizer = new ValueSanitizer,
        private readonly ?string $actor = null,
        private readonly ?Dispatcher $events = null,
    ) {
        $this->working = $original;
    }

    /** Open a session for $path (an absent file starts as an empty document). */
    public static function open(string $path, ?WriterInterface $writer = null, ?CommitPipeline $pipeline = null, ?string $actor = null, ?Dispatcher $events = null): self
    {
        $raw = is_file($path) ? (string) @file_get_contents($path) : '';
        $conflicts = new ConflictDetector;

        return new self(
            path: $path,
            original: EnvDocument::parse($raw),
            fingerprint: $conflicts->fingerprint($path),
            conflicts: $conflicts,
            pipeline: $pipeline ?? CommitPipeline::default($writer),
            actor: $actor,
            events: $events,
        );
    }

    /** Permit this commit to run in production (overrides the production guard). */
    public function allowProduction(): self
    {
        $this->allowProduction = true;

        return $this;
    }

    public function get(string $key): ?string
    {
        return $this->working->get($key);
    }

    public function has(string $key): bool
    {
        return $this->working->has($key);
    }

    public function set(string $key, string $value, bool $export = false): self
    {
        // Sanitize at the single write chokepoint: NUL is rejected, other control
        // characters stripped — covering programmatic, CLI and WebUI writes alike.
        $this->working = $this->working->withValue($key, $this->sanitizer->sanitize($value, $key), $export);

        return $this;
    }

    public function forget(string $key): self
    {
        $this->working = $this->working->without($key);

        return $this;
    }

    public function rename(string $from, string $to): self
    {
        if (! $this->working->has($from)) {
            throw KeyNotFoundException::for($from);
        }

        $this->working = $this->working->renamed($from, $to);

        return $this;
    }

    public function isDirty(): bool
    {
        return $this->working->render() !== $this->original->render();
    }

    /**
     * Key-level diff of staged changes.
     *
     * @return array<string, array{old: ?string, new: ?string}>
     */
    public function changes(): array
    {
        $before = $this->original->toArray();
        $after = $this->working->toArray();
        $changes = [];

        foreach ($after as $key => $value) {
            if (! \array_key_exists($key, $before) || $before[$key] !== $value) {
                $changes[$key] = ['old' => $before[$key] ?? null, 'new' => $value];
            }
        }

        foreach ($before as $key => $value) {
            if (! \array_key_exists($key, $after)) {
                $changes[$key] = ['old' => $value, 'new' => null];
            }
        }

        return $changes;
    }

    /** Abandon staged changes. */
    public function discard(): self
    {
        $this->working = $this->original;

        return $this;
    }

    /** The exact text that would be written. */
    public function preview(): string
    {
        return $this->working->render();
    }

    /**
     * Commit staged changes atomically through the pipeline. No-op when nothing
     * changed. May throw ConflictException, ProductionGuardException,
     * ProtectedKeyException, InvalidKeyException, or IntegrityException
     * (post-write failure → rolled back).
     */
    public function save(): self
    {
        if (! $this->isDirty()) {
            return $this;
        }

        try {
            $this->conflicts->ensureUnchanged($this->path, $this->fingerprint);
        } catch (ConflictException $e) {
            $this->events?->dispatch(new ConflictDetected(
                $this->path,
                $this->fingerprint,
                $this->conflicts->fingerprint($this->path),
                $this->actor,
            ));
            throw $e;
        }

        try {
            $this->pipeline->run(new CommitContext(
                path: $this->path,
                document: $this->working,
                original: $this->original,
                allowProduction: $this->allowProduction,
                actor: $this->actor,
            ));
        } catch (IntegrityException $e) {
            // A post-write rollback already emitted WriteRolledBack; not a rejection.
            throw $e;
        } catch (EnvKitException $e) {
            $this->events?->dispatch(new WriteRejected(
                $this->path,
                $e->envKitReason(),
                array_keys($this->changes()),
                $this->actor,
            ));
            throw $e;
        }

        return $this;
    }
}
