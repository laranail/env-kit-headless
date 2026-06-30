<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Audit;

/** Reads the JSON-lines audit log back into recent-first entries (for `env:history`). */
final class HistoryReader
{
    public function __construct(
        private readonly string $path,
    ) {}

    /**
     * @return list<array<string, mixed>> most-recent-first, at most $limit entries
     */
    public function recent(int $limit = 20): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $entries = [];
        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $entries[] = $decoded;
            }
            if (count($entries) >= max(1, $limit)) {
                break;
            }
        }

        return $entries;
    }
}
