<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Porter\Formats;

use Simtabi\Laranail\EnvKit\Headless\Contracts\PortFormatInterface;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\PortException;
use Symfony\Component\Yaml\Yaml;

/**
 * YAML map of key => value. Registered only when `symfony/yaml` is installed
 * (the class autoloads safely; {@see Yaml} is only touched inside the methods).
 */
final class YamlFormat implements PortFormatInterface
{
    public function name(): string
    {
        return 'yaml';
    }

    public function export(array $values): string
    {
        return Yaml::dump($values);
    }

    public function import(string $content): array
    {
        try {
            $parsed = Yaml::parse($content);
        } catch (\Throwable) {
            throw PortException::malformed('yaml');
        }

        if (! is_array($parsed)) {
            throw PortException::malformed('yaml');
        }

        $out = [];
        foreach ($parsed as $key => $value) {
            $out[(string) $key] = match (true) {
                is_string($value) => $value,
                is_scalar($value) => (string) $value,
                default => (string) json_encode($value),
            };
        }

        return $out;
    }
}
