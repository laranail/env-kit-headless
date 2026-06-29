<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Writer;

use Simtabi\Laranail\EnvKit\Headless\Contracts\WriterInterface;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\FileNotWritableException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\LockException;

/**
 * Crash-safe writer: write to a temp file ON THE SAME FILESYSTEM, flush + fsync,
 * then atomically rename over the target. A reader therefore only ever sees the
 * old file or the complete new one — never a half-written one.
 *
 * The mode of an existing target is preserved (never widened); a NEW file gets
 * 0600 (owner-only) because an .env holds plaintext secrets. NFS/Windows:
 * rename-over-existing is atomic on POSIX local filesystems; on other setups the
 * LockManager (later slice) adds an advisory guard.
 */
final class AtomicEnvWriter implements WriterInterface
{
    public function write(string $path, string $contents): void
    {
        $dir = \dirname($path);

        if (! is_dir($dir) || ! is_writable($dir)) {
            throw FileNotWritableException::for($dir);
        }

        $tmp = @tempnam($dir, '.env-kit-');
        if ($tmp === false) {
            throw FileNotWritableException::for($dir);
        }

        try {
            $this->writeLocked($tmp, $contents, $path);
            $this->mirrorPermissions($path, $tmp);

            if (! @rename($tmp, $path)) {
                throw FileNotWritableException::for($path);
            }
        } catch (\Throwable $e) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
            throw $e;
        }
    }

    private function writeLocked(string $tmp, string $contents, string $target): void
    {
        $handle = @fopen($tmp, 'wb');
        if ($handle === false) {
            throw FileNotWritableException::for($tmp);
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw LockException::for($target);
            }

            if (@fwrite($handle, $contents) !== \strlen($contents)) {
                throw FileNotWritableException::for($tmp);
            }

            fflush($handle);
            @fsync($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private function mirrorPermissions(string $target, string $tmp): void
    {
        $mode = is_file($target) ? @fileperms($target) : false;

        // Preserve the existing target's mode; never widen. A new secrets file
        // defaults to owner-only (0600).
        @chmod($tmp, $mode !== false ? ($mode & 0777) : 0600);
    }
}
