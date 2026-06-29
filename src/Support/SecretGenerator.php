<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Support;

/**
 * Cryptographically-strong secret generators for `.env` values: random tokens
 * and Laravel-format APP_KEYs. Backed by {@see random_bytes()}.
 */
final class SecretGenerator
{
    /** A random token of $bytes entropy, hex- or url-safe-base64-encoded. */
    public function token(int $bytes = 32, string $encoding = 'hex'): string
    {
        $raw = random_bytes(max(1, $bytes));

        return match ($encoding) {
            'base64' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='),
            default => bin2hex($raw),
        };
    }

    /** A Laravel-style `base64:`-prefixed APP_KEY (16 bytes for *-128-*, else 32). */
    public function appKey(string $cipher = 'AES-256-CBC'): string
    {
        $length = str_contains($cipher, '128') ? 16 : 32;

        return 'base64:'.base64_encode(random_bytes($length));
    }
}
