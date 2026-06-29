<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Testing;

use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use Simtabi\Laranail\EnvKit\Headless\Backup\BackupFile;
use Simtabi\Laranail\EnvKit\Headless\Backup\BackupManager;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Doctor\Diagnostic;
use Simtabi\Laranail\EnvKit\Headless\Doctor\Doctor;
use Simtabi\Laranail\EnvKit\Headless\Document\Entry\Setter;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\KeyNotFoundException;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\SchemaException;
use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
use Simtabi\Laranail\EnvKit\Headless\Porter\Porter;
use Simtabi\Laranail\EnvKit\Headless\Results\ValidationResult;
use Simtabi\Laranail\EnvKit\Headless\Schema\EnvSchema;
use Simtabi\Laranail\EnvKit\Headless\Support\Interpolator;
use Simtabi\Laranail\EnvKit\Headless\Support\TypedAccessor;

/**
 * An in-memory EnvKit for consumer tests: reads are answered from a map, writes
 * mutate it and are recorded, and NOTHING touches disk. Installed via
 * `EnvKit::fake()`; assert with `assertSet()` / `assertForgotten()`.
 */
final class EnvKitFake implements EnvKitInterface
{
    /** @var array<string, string> */
    private array $values;

    /** @var list<array{action: string, key: string, value: ?string}> */
    public array $recorded = [];

    private TypedAccessor $typed;

    private Interpolator $interpolator;

    private ?EnvKitConfigurator $configurator = null;

    /** @param array<string, string> $initial */
    public function __construct(array $initial = [])
    {
        $this->values = $initial;
        $this->typed = new TypedAccessor;
        $this->interpolator = new Interpolator;
    }

    // --- Reads --------------------------------------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        return $this->typed->string($this->values[$key] ?? null, $default);
    }

    public function getBool(string $key, ?bool $default = null): ?bool
    {
        return $this->typed->bool($this->values[$key] ?? null, $default);
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        return $this->typed->int($this->values[$key] ?? null, $default);
    }

    public function getFloat(string $key, ?float $default = null): ?float
    {
        return $this->typed->float($this->values[$key] ?? null, $default);
    }

    /**
     * @param  array<int|string, mixed>|null  $default
     * @return array<int|string, mixed>|null
     */
    public function getArray(string $key, ?array $default = null): ?array
    {
        return $this->typed->array($this->values[$key] ?? null, $default);
    }

    public function getJson(string $key, mixed $default = null): mixed
    {
        return $this->typed->json($this->values[$key] ?? null, $default);
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->values);
    }

    public function missing(string $key): bool
    {
        return ! $this->has($key);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->values;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->values);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->values, array_flip($keys));
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->values, array_flip($keys));
    }

    /** @return array<string, string> */
    public function group(string $prefix): array
    {
        $needle = rtrim($prefix, '_').'_';

        return array_filter(
            $this->values,
            static fn (string $key): bool => str_starts_with($key, $needle),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function raw(): string
    {
        $document = EnvDocument::parse('');
        foreach ($this->values as $key => $value) {
            $document = $document->withValue($key, $value);
        }

        return $document->render();
    }

    public function interpolated(string $key, mixed $default = null): mixed
    {
        $value = $this->values[$key] ?? null;

        return $value === null ? $default : $this->interpolator->resolve($value, $this->values);
    }

    /** @return Collection<int, Setter> */
    public function entries(): Collection
    {
        return Collection::make(EnvDocument::parse($this->raw())->setters());
    }

    // --- Writes (recorded, in-memory) ---------------------------------------

    /** @param array{export?: bool} $options */
    public function set(string $key, string $value, array $options = []): static
    {
        $this->values[$key] = $value;
        $this->recorded[] = ['action' => 'set', 'key' => $key, 'value' => $value];

        return $this;
    }

    public function forget(string $key): static
    {
        unset($this->values[$key]);
        $this->recorded[] = ['action' => 'forget', 'key' => $key, 'value' => null];

        return $this;
    }

    public function rename(string $from, string $to): static
    {
        if (\array_key_exists($from, $this->values)) {
            $this->values[$to] = $this->values[$from];
            unset($this->values[$from]);
        }
        $this->recorded[] = ['action' => 'rename', 'key' => $from, 'value' => $to];

        return $this;
    }

    /** @param array<string, string> $pairs */
    public function setMany(array $pairs): static
    {
        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function update(string $key, string $value): static
    {
        if ($this->missing($key)) {
            throw KeyNotFoundException::for($key);
        }

        return $this->set($key, $value);
    }

    public function setOrUpdate(string $key, string $value): static
    {
        return $this->set($key, $value);
    }

    public function setIfMissing(string $key, string $value): static
    {
        return $this->missing($key) ? $this->set($key, $value) : $this;
    }

    /** @param list<string> $keys */
    public function forgetMany(array $keys): static
    {
        foreach ($keys as $key) {
            $this->forget($key);
        }

        return $this;
    }

    public function setExport(string $key, bool $export = true): static
    {
        if ($this->missing($key)) {
            throw KeyNotFoundException::for($key);
        }
        $this->recorded[] = ['action' => 'setExport', 'key' => $key, 'value' => $export ? '1' : '0'];

        return $this;
    }

    public function entry(string $key): ?Setter
    {
        foreach (EnvDocument::parse($this->raw())->setters() as $setter) {
            if ($setter->key === $key) {
                return $setter;
            }
        }

        return null;
    }

    public function transaction(Closure $callback): mixed
    {
        return $callback($this);
    }

    // --- Extended surface (in-memory; matches the facade so faked code never errors) ---
    //
    // Fidelity note: the fake is a single in-memory store. `file()/on()` ignore file
    // targeting; `allowProduction()/save()` are no-ops; `encrypt/decrypt/setEncrypted`
    // store/return plaintext (no real crypto); `import()` skips pipeline validation;
    // `backup/restore/backups` are stubs. Use the real engine for those behaviours.

    public function file(string $path): static
    {
        return $this;
    }

    public function on(string $environment): static
    {
        return $this;
    }

    public function allowProduction(): static
    {
        return $this;
    }

    public function save(): static
    {
        return $this;
    }

    public function setEncrypted(string $key, string $value): static
    {
        $this->values[$key] = $value;
        $this->recorded[] = ['action' => 'setEncrypted', 'key' => $key, 'value' => $value];

        return $this;
    }

    public function getDecrypted(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    public function encrypt(string $key): static
    {
        $this->recorded[] = ['action' => 'encrypt', 'key' => $key, 'value' => null];

        return $this;
    }

    public function decrypt(string $key): static
    {
        $this->recorded[] = ['action' => 'decrypt', 'key' => $key, 'value' => null];

        return $this;
    }

    /** @return list<Diagnostic> */
    public function inspect(): array
    {
        return Doctor::withDefaults()->inspect(EnvDocument::parse($this->raw()));
    }

    /** @return array{only_here: list<string>, only_there: list<string>, changed: list<string>} */
    public function diff(string $against): array
    {
        $there = EnvDocument::parse(is_file($against) ? (string) @file_get_contents($against) : '')->toArray();

        $changed = [];
        foreach ($this->values as $key => $value) {
            if (\array_key_exists($key, $there) && $there[$key] !== $value) {
                $changed[] = $key;
            }
        }

        return [
            'only_here' => array_values(array_diff(array_keys($this->values), array_keys($there))),
            'only_there' => array_values(array_diff(array_keys($there), array_keys($this->values))),
            'changed' => $changed,
        ];
    }

    public function export(string $format = 'json'): string
    {
        return Porter::withDefaults()->format($format)->export($this->values);
    }

    public function import(string $content, string $format = 'json'): static
    {
        foreach (Porter::withDefaults()->format($format)->import($content) as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    public function configure(): EnvKitConfigurator
    {
        return $this->configurator ??= new EnvKitConfigurator;
    }

    public function schema(): EnvSchema
    {
        return $this->configure()->schema();
    }

    public function validate(): ValidationResult
    {
        return $this->schema()->validate($this->values);
    }

    public function isValid(): bool
    {
        return $this->validate()->passed();
    }

    public function assertValid(): static
    {
        $result = $this->validate();

        if ($result->failed()) {
            throw SchemaException::failed($result->messages());
        }

        return $this;
    }

    public function backup(?string $name = null): ?BackupFile
    {
        if ($this->values === []) {
            return null;
        }

        $this->recorded[] = ['action' => 'backup', 'key' => $name ?? '', 'value' => null];

        return new BackupFile($name ?? 'fake.bak', '', 0, 0);
    }

    public function backups(): BackupManager
    {
        return new BackupManager(sys_get_temp_dir());
    }

    public function restore(string $name): BackupFile
    {
        $this->recorded[] = ['action' => 'restore', 'key' => $name, 'value' => null];

        return new BackupFile($name, '', 0, 0);
    }

    // --- Assertions ---------------------------------------------------------

    public function assertSet(string $key, ?string $value = null): void
    {
        Assert::assertArrayHasKey($key, $this->values, "Expected [{$key}] to have been set.");

        if ($value !== null) {
            Assert::assertSame($value, $this->values[$key]);
        }
    }

    public function assertForgotten(string $key): void
    {
        Assert::assertArrayNotHasKey($key, $this->values, "Expected [{$key}] to have been forgotten.");
    }

    public function assertNothingChanged(): void
    {
        Assert::assertSame([], $this->recorded, 'Expected no env mutations.');
    }
}
