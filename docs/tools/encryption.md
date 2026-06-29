# Encryption-at-rest

EnvKit can encrypt **individual values** so secrets are stored as ciphertext in the
file. This is distinct from — and never a re-binding of — Laravel's whole-file
`env:encrypt`.

> ⚠️ **Only encrypt keys you read back through `EnvKit::getDecrypted()`.** Laravel's
> own `env()` / `config()` read the raw `.env` file directly, so they would receive
> the **ciphertext** — encrypting a key the application consumes that way (e.g.
> `DB_PASSWORD`, `APP_KEY`) will break it. Reserve per-value encryption for secrets
> your code fetches via EnvKit.

## Usage

```php
EnvKit::setEncrypted('STRIPE_SECRET', $plaintext); // writes envkit:<ciphertext>
EnvKit::getDecrypted('STRIPE_SECRET');             // → $plaintext

EnvKit::encrypt('EXISTING_KEY');  // encrypt a value already in the file, in place
EnvKit::decrypt('EXISTING_KEY');  // reverse it
```

At rest the value carries an `envkit:` marker, so the file round-trips normally and
encrypted values are trivially recognisable. Plain `get()` returns the ciphertext
(the at-rest value); use `getDecrypted()` to read the plaintext.

## Drivers

The default `laravel` driver uses your app's `APP_KEY` `Encrypter`. The cipher is
resolved through `EnvKitManager`, so you can register and select your own backend:

```php
use Simtabi\Laranail\EnvKit\Headless\EnvKitManager;
use Simtabi\Laranail\EnvKit\Headless\Contracts\ValueCipherInterface;

app(EnvKitManager::class)->extend('vault', fn () => new VaultCipher);
// config/env-kit.php → 'encryption' => ['driver' => 'vault']
```

Or swap it for a one-off via the configurator:

```php
EnvKit::configure()->useCipher(new VaultCipher);
```

A `ValueCipherInterface` implements `encrypt()`, `decrypt()`, and `isEncrypted()`.

---

[← Docs index](../../README.md#documentation)
