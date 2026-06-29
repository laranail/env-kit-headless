<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Document;

use Simtabi\Laranail\EnvKit\Headless\Contracts\EntryInterface;
use Simtabi\Laranail\EnvKit\Headless\Document\Entry\Setter;

/**
 * Immutable, comment/format-preserving representation of an .env file.
 *
 * Reads are O(n) scans (env files are tiny). Mutators return a NEW document;
 * untouched entries keep their original raw text, so rendering is minimal-diff.
 */
final class EnvDocument
{
    private const BOM = "\xEF\xBB\xBF";

    /** @param list<EntryInterface> $entries */
    public function __construct(
        private readonly array $entries,
        private readonly string $eol = "\n",
        private readonly bool $hasBom = false,
        private readonly bool $trailingNewline = true,
    ) {}

    public static function parse(string $raw): self
    {
        return (new EnvParser)->parse($raw);
    }

    /** Serialize back to text, reproducing BOM, line-endings and trailing newline. */
    public function render(): string
    {
        $body = implode($this->eol, array_map(static fn (EntryInterface $e): string => $e->render(), $this->entries));

        $out = ($this->hasBom ? self::BOM : '').$body;

        if ($this->trailingNewline && $this->entries !== []) {
            $out .= $this->eol;
        }

        return $out;
    }

    public function has(string $key): bool
    {
        return $this->find($key) instanceof Setter;
    }

    /** Logical value, or null when the key is absent. */
    public function get(string $key): ?string
    {
        return $this->find($key)?->value;
    }

    /** @return list<string> */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->entries as $entry) {
            if ($entry instanceof Setter) {
                $keys[] = $entry->key;
            }
        }

        return $keys;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $map = [];
        foreach ($this->entries as $entry) {
            if ($entry instanceof Setter) {
                $map[$entry->key] = $entry->value;
            }
        }

        return $map;
    }

    /**
     * The setter entries (key/value/export/comment metadata), in file order.
     *
     * @return list<Setter>
     */
    public function setters(): array
    {
        $setters = [];
        foreach ($this->entries as $entry) {
            if ($entry instanceof Setter) {
                $setters[] = $entry;
            }
        }

        return $setters;
    }

    /** Set or update a key, returning a new document. New keys are appended. */
    public function withValue(string $key, string $value, bool $export = false): self
    {
        $entries = $this->entries;
        $found = false;

        foreach ($entries as $i => $entry) {
            if ($entry instanceof Setter && $entry->key === $key) {
                $entries[$i] = new Setter($key, $value, $entry->export || $export, $entry->alwaysQuote);
                $found = true;
                break;
            }
        }

        if (! $found) {
            $entries[] = new Setter($key, $value, $export);
        }

        return new self($entries, $this->eol, $this->hasBom, $this->trailingNewline);
    }

    /** Explicitly set or clear the `export ` prefix on an existing key (value unchanged). */
    public function withExport(string $key, bool $export): self
    {
        $entries = $this->entries;

        foreach ($entries as $i => $entry) {
            if ($entry instanceof Setter && $entry->key === $key) {
                $entries[$i] = new Setter($key, $entry->value, $export, $entry->alwaysQuote);
                break;
            }
        }

        return new self($entries, $this->eol, $this->hasBom, $this->trailingNewline);
    }

    /** Remove a key (and only the setter line), returning a new document. */
    public function without(string $key): self
    {
        $entries = array_values(array_filter(
            $this->entries,
            static fn (EntryInterface $e): bool => ! ($e instanceof Setter && $e->key === $key),
        ));

        return new self($entries, $this->eol, $this->hasBom, $this->trailingNewline);
    }

    /** Rename a key in place (preserving its position), returning a new document. */
    public function renamed(string $from, string $to): self
    {
        $entries = $this->entries;

        foreach ($entries as $i => $entry) {
            if ($entry instanceof Setter && $entry->key === $from) {
                $entries[$i] = new Setter($to, $entry->value, $entry->export, $entry->alwaysQuote);
                break;
            }
        }

        return new self($entries, $this->eol, $this->hasBom, $this->trailingNewline);
    }

    public function eol(): string
    {
        return $this->eol;
    }

    public function hasBom(): bool
    {
        return $this->hasBom;
    }

    public function hasTrailingNewline(): bool
    {
        return $this->trailingNewline;
    }

    private function find(string $key): ?Setter
    {
        foreach ($this->entries as $entry) {
            if ($entry instanceof Setter && $entry->key === $key) {
                return $entry;
            }
        }

        return null;
    }
}
