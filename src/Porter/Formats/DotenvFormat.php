<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Porter\Formats;

use Simtabi\Laranail\EnvKit\Headless\Contracts\PortFormatInterface;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Support\ValueFormatter;

/** Plain `KEY=VALUE` dotenv text — round-trips through the same encoder/parser as the engine. */
final class DotenvFormat implements PortFormatInterface
{
    public function name(): string
    {
        return 'dotenv';
    }

    public function export(array $values): string
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $lines[] = $key.'='.ValueFormatter::encode($value);
        }

        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }

    public function import(string $content): array
    {
        return EnvDocument::parse($content)->toArray();
    }
}
