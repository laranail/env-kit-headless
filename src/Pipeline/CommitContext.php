<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Pipeline;

use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;

/** The payload that flows through the commit pipeline. */
final class CommitContext
{
    /** Captured by the Write pipe for rollback if verification fails. */
    public ?string $previous = null;

    public function __construct(
        public readonly string $path,
        public readonly EnvDocument $document,
        public readonly EnvDocument $original,
        public readonly bool $allowProduction = false,
        public readonly ?string $actor = null,
        public readonly string $operation = 'write',
    ) {}

    /**
     * Keys added, changed, or removed by this commit — what guards and
     * validation operate on (not the whole file).
     *
     * @return list<string>
     */
    public function changedKeys(): array
    {
        $before = $this->original->toArray();
        $after = $this->document->toArray();
        $keys = [];

        foreach ($after as $key => $value) {
            if (! \array_key_exists($key, $before) || $before[$key] !== $value) {
                $keys[] = $key;
            }
        }

        foreach (array_keys($before) as $key) {
            if (! \array_key_exists($key, $after)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
