<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Authorization;

use Simtabi\Laranail\EnvKit\Headless\Pipeline\CommitContext;

/**
 * What an update gate / observer sees about a pending commit. A read-only view
 * over the {@see CommitContext}; gates and observers are trusted consumer code, so
 * they receive RAW values (events and notifications stay redacted).
 */
final class WriteContext
{
    public function __construct(
        private readonly CommitContext $context,
        public readonly bool $isProduction,
    ) {}

    public function path(): string
    {
        return $this->context->path;
    }

    public function actor(): ?string
    {
        return $this->context->actor;
    }

    public function operation(): string
    {
        return $this->context->operation;
    }

    public function allowProduction(): bool
    {
        return $this->context->allowProduction;
    }

    /** @return list<string> */
    public function changedKeys(): array
    {
        return $this->context->changedKeys();
    }

    /** @return array{old: ?string, new: ?string} */
    public function change(string $key): array
    {
        $before = $this->context->original->toArray();
        $after = $this->context->document->toArray();

        return ['old' => $before[$key] ?? null, 'new' => $after[$key] ?? null];
    }
}
