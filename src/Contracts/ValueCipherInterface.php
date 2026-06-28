<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Contracts;

/**
 * Encrypts/decrypts individual .env values at rest. This is per-value
 * encryption — distinct from, and never a re-binding of, Laravel's whole-file
 * `env:encrypt`. Ciphertext carries a marker so {@see isEncrypted()} is cheap.
 */
interface ValueCipherInterface
{
    /** Encrypt a plaintext value, returning marked ciphertext. */
    public function encrypt(string $plain): string;

    /** Decrypt marked ciphertext back to plaintext (returns input as-is if unmarked). */
    public function decrypt(string $cipher): string;

    public function isEncrypted(string $value): bool;
}
