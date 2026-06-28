<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Porter\Formats;

use Simtabi\Laranail\EnvKit\Headless\Contracts\PortFormatInterface;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\PortException;

/** Pretty-printed JSON object of key => value. */
final class JsonFormat implements PortFormatInterface
{
    public function name(): string
    {
        return 'json';
    }

    public function export(array $values): string
    {
        return json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function import(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw PortException::malformed('json');
        }

        if (! is_array($decoded)) {
            throw PortException::malformed('json');
        }

        $out = [];
        foreach ($decoded as $key => $value) {
            $out[(string) $key] = match (true) {
                is_string($value) => $value,
                is_scalar($value) => (string) $value,
                default => (string) json_encode($value),
            };
        }

        return $out;
    }
}
