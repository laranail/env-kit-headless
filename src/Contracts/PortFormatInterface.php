<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Contracts;

/** A serialization format for import/export (json, csv, …). */
interface PortFormatInterface
{
    /** The format's short name (used as the --format value). */
    public function name(): string;

    /** @param array<string, string> $values */
    public function export(array $values): string;

    /** @return array<string, string> */
    public function import(string $content): array;
}
