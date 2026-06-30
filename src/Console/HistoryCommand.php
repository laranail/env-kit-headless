<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Console;

use Simtabi\Laranail\EnvKit\Headless\Audit\HistoryReader;

final class HistoryCommand extends AbstractEnvCommand
{
    /** @var string */
    protected $signature = 'laranail::env-kit-headless.history {--limit=20 : how many recent entries to show}';

    /** @var string */
    protected $description = 'Show the recent audit history (who changed which keys, when). Values are never shown.';

    /** @var list<string> */
    protected array $commandAliases = ['env:history'];

    public function handle(): int
    {
        return $this->runSafely(function (): int {
            $path = config('env-kit.audit.path');
            $path = is_string($path) ? $path : (string) storage_path('env-kit/audit.log');

            $limit = $this->option('limit');
            $limit = is_numeric($limit) ? max(1, (int) $limit) : 20;

            $entries = (new HistoryReader($path))->recent($limit);
            if ($entries === []) {
                $this->info('No audit history recorded.');

                return self::EXIT_OK;
            }

            $rows = [];
            foreach ($entries as $entry) {
                $occurredAt = $entry['occurred_at'] ?? null;
                $when = is_int($occurredAt) ? date('Y-m-d H:i:s', $occurredAt) : '—';
                $actor = is_string($entry['actor'] ?? null) ? $entry['actor'] : '—';
                $changes = $entry['changes'] ?? null;
                $keys = '';
                if (is_array($changes)) {
                    $names = array_map(
                        static fn (mixed $c): string => is_array($c) && is_string($c['key'] ?? null) ? $c['key'] : '',
                        $changes,
                    );
                    $keys = implode(', ', array_filter($names));
                }
                $rows[] = [$when, $actor, $keys];
            }

            $this->table(['When', 'Actor', 'Keys changed'], $rows);

            return self::EXIT_OK;
        });
    }
}
