<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Porter\Formats;

use Simtabi\Laranail\EnvKit\Headless\Contracts\PortFormatInterface;

/** A `KEY,VALUE` CSV with a header row (RFC-4180 quoting). */
final class CsvFormat implements PortFormatInterface
{
    public function name(): string
    {
        return 'csv';
    }

    public function export(array $values): string
    {
        $rows = ['KEY,VALUE'];
        foreach ($values as $key => $value) {
            $rows[] = $this->encodeField((string) $key).','.$this->encodeField($value);
        }

        return implode("\n", $rows)."\n";
    }

    public function import(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $out = [];

        foreach ($lines as $index => $line) {
            if ($line === '') {
                continue;
            }

            $fields = str_getcsv($line, ',', '"', '');
            $key = $fields[0] ?? '';

            if ($index === 0 && $key === 'KEY') {
                continue; // header row
            }
            if ($key === '') {
                continue;
            }

            $out[$key] = $fields[1] ?? '';
        }

        return $out;
    }

    private function encodeField(string $field): string
    {
        if (preg_match('/[",\r\n]/', $field) === 1) {
            return '"'.str_replace('"', '""', $field).'"';
        }

        return $field;
    }
}
