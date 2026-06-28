<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Porter;

use Simtabi\Laranail\EnvKit\Headless\Contracts\PortFormatInterface;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\PortException;
use Simtabi\Laranail\EnvKit\Headless\Porter\Formats\CsvFormat;
use Simtabi\Laranail\EnvKit\Headless\Porter\Formats\JsonFormat;

/** A registry of import/export formats, keyed by name. */
final class Porter
{
    /** @var array<string, PortFormatInterface> */
    private array $formats = [];

    public function register(PortFormatInterface $format): self
    {
        $this->formats[$format->name()] = $format;

        return $this;
    }

    public function format(string $name): PortFormatInterface
    {
        return $this->formats[$name] ?? throw PortException::unknownFormat($name);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->formats);
    }

    /** The built-in formats (json, csv), plus any consumer-registered extras. */
    public static function withDefaults(PortFormatInterface ...$extra): self
    {
        $porter = (new self)
            ->register(new JsonFormat)
            ->register(new CsvFormat);

        foreach ($extra as $format) {
            $porter->register($format);
        }

        return $porter;
    }
}
