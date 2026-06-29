<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Security;

use Simtabi\Laranail\EnvKit\Headless\Exceptions\InvalidValueException;

/**
 * Cleans untrusted input before it enters a document. A NUL byte is rejected
 * outright (it cannot live in an .env file); other disallowed control characters
 * are stripped. Tab, newline and carriage-return are kept (the writer escapes
 * them per §3B). Never trims meaningful surrounding whitespace — that is encoded.
 */
final class ValueSanitizer
{
    /** C0/C1 control chars except \t (\x09), \n (\x0A), \r (\x0D); plus DEL (\x7F). */
    private const STRIP = '/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/';

    public function __construct(
        private readonly ?int $maxLength = null,
    ) {}

    public function sanitize(string $value, ?string $key = null): string
    {
        if (str_contains($value, "\0")) {
            throw InvalidValueException::nulByte($key);
        }

        $clean = preg_replace(self::STRIP, '', $value) ?? $value;

        if ($this->maxLength !== null && \strlen($clean) > $this->maxLength) {
            throw InvalidValueException::tooLong($key, $this->maxLength);
        }

        return $clean;
    }

    /** Would sanitize() change this value (i.e. is it already clean)? */
    public function isClean(string $value): bool
    {
        return ! str_contains($value, "\0") && preg_match(self::STRIP, $value) !== 1;
    }
}
