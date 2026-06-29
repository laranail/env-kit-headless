<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Backup;

use Simtabi\Laranail\EnvKit\Headless\Exceptions\FileNotWritableException;

/**
 * Timestamped local backups of an .env file. `retain = 0` keeps everything;
 * otherwise the oldest are pruned after each new backup. Remote disks
 * (Laravel Filesystem) layer in behind a store driver in a later slice.
 */
final class BackupManager
{
    public function __construct(
        private readonly string $directory,
        private readonly int $retain = 0,
    ) {}

    /** Snapshot $envPath. Returns null when there is nothing to back up. */
    public function backup(string $envPath): ?BackupFile
    {
        if (! is_file($envPath)) {
            return null;
        }

        // 0700: backups hold full plaintext .env contents — keep the dir owner-only.
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw FileNotWritableException::for($this->directory);
        }

        // Microsecond precision keeps names lexicographically chronological, so
        // retention prunes the genuinely-oldest even for rapid same-second backups.
        // Strip the leading dot of `.env` so backups are not hidden dotfiles.
        $base = ltrim(basename($envPath), '.') ?: 'env';
        $micros = (int) (fmod(microtime(true), 1) * 1_000_000);
        $name = sprintf('%s.%s-%06d-%s.bak', $base, date('Ymd-His'), $micros, bin2hex(random_bytes(2)));
        $destination = $this->directory.'/'.$name;

        if (! @copy($envPath, $destination)) {
            throw FileNotWritableException::for($destination);
        }

        @chmod($destination, 0600); // owner-only: a backup is a plaintext secrets copy

        $this->prune();

        return new BackupFile($name, $destination, @filemtime($destination) ?: time(), @filesize($destination) ?: 0);
    }

    /** @return list<BackupFile> newest first */
    public function all(): array
    {
        if (! is_dir($this->directory)) {
            return [];
        }

        $backups = array_map(
            static fn (string $p): BackupFile => new BackupFile(basename($p), $p, @filemtime($p) ?: 0, @filesize($p) ?: 0),
            glob($this->directory.'/*.bak') ?: [],
        );

        usort($backups, static fn (BackupFile $a, BackupFile $b): int => $b->name <=> $a->name);

        return $backups;
    }

    public function latest(): ?BackupFile
    {
        return $this->all()[0] ?? null;
    }

    /** Locate a backup by its file name. */
    public function find(string $name): ?BackupFile
    {
        foreach ($this->all() as $backup) {
            if ($backup->name === $name) {
                return $backup;
            }
        }

        return null;
    }

    public function prune(): void
    {
        if ($this->retain <= 0) {
            return;
        }

        foreach (array_slice($this->all(), $this->retain) as $old) {
            @unlink($old->path);
        }
    }
}
