<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Security;

use Simtabi\Laranail\EnvKit\Headless\Exceptions\NotEditableException;

/**
 * The optional "allowlist" tier of the layered key policy (§9). When the pattern
 * list is empty, every (non-protected) key is editable; when it is non-empty, only
 * keys matching a pattern may be written — enforced on every surface. Supports
 * wildcards (e.g. `APP_*`). Reads are unaffected.
 */
final class EditableKeys
{
    /** @param list<string> $patterns */
    public function __construct(
        private readonly array $patterns = [],
    ) {}

    public function isEditable(string $key): bool
    {
        if ($this->patterns === []) {
            return true;
        }

        foreach ($this->patterns as $pattern) {
            if (fnmatch($pattern, $key, FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }

    public function guard(string $key): void
    {
        if (! $this->isEditable($key)) {
            throw NotEditableException::for($key);
        }
    }
}
