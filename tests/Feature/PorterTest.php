<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Contracts\PortFormatInterface;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\PortException;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Porter\Formats\CsvFormat;
use Simtabi\Laranail\EnvKit\Headless\Porter\Formats\JsonFormat;
use Simtabi\Laranail\EnvKit\Headless\Porter\Porter;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('exports values as json', function () {
    $this->bindEnv("A=1\nB=hello world\n");

    expect(json_decode(EnvKit::export('json'), true))->toBe(['A' => '1', 'B' => 'hello world']);
});

it('imports json through the commit pipeline', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::import('{"NEW":"val","COUNT":"3"}', 'json');

    expect(EnvKit::get('NEW'))->toBe('val')
        ->and(EnvKit::get('COUNT'))->toBe('3');
});

it('round-trips csv with quoted values', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::import("KEY,VALUE\nGREETING,\"hello, world\"\nN,5\n", 'csv');

    expect(EnvKit::get('GREETING'))->toBe('hello, world')
        ->and(EnvKit::get('N'))->toBe('5')
        ->and(EnvKit::export('csv'))->toContain('"hello, world"');
});

it('writes an export to a file with env:export --output', function () {
    $path = $this->bindEnv("A=1\nB=2\n");
    $out = dirname($path).'/export.json';

    $this->artisan('env:export', ['--output' => $out])->assertExitCode(0);

    expect(json_decode((string) file_get_contents($out), true))->toBe(['A' => '1', 'B' => '2']);
});

it('imports from a file with env:import', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    $src = dirname($path).'/in.json';
    file_put_contents($src, '{"IMPORTED":"yes"}');

    $this->artisan('env:import', ['source' => $src])->assertExitCode(0);

    expect(EnvKit::get('IMPORTED'))->toBe('yes');
});

it('fails env:import when the source is missing (exit 2)', function () {
    $this->bindEnv("A=1\n");

    $this->artisan('env:import', ['source' => '/no/such/file.json'])->assertExitCode(2);
});

it('rejects an unknown format', function () {
    $this->bindEnv("A=1\n");

    expect(fn () => EnvKit::export('yaml'))->toThrow(PortException::class);
});

it('supports a custom format registered via configure()', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);

    EnvKit::configure()->registerPortFormat(new class implements PortFormatInterface
    {
        public function name(): string
        {
            return 'lines';
        }

        public function export(array $values): string
        {
            $out = '';
            foreach ($values as $key => $value) {
                $out .= "{$key}={$value}\n";
            }

            return $out;
        }

        public function import(string $content): array
        {
            $out = [];
            foreach (explode("\n", trim($content)) as $line) {
                if ($line === '' || ! str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $out[$key] = $value;
            }

            return $out;
        }
    });

    EnvKit::import("FOO=bar\n", 'lines');

    expect(EnvKit::get('FOO'))->toBe('bar');
});

/*
|--------------------------------------------------------------------------
| CsvFormat (direct unit tests)
|--------------------------------------------------------------------------
*/

it('csv export: emits a KEY,VALUE header followed by exact rows', function () {
    expect((new CsvFormat)->export(['A' => '1', 'B' => '2']))
        ->toBe("KEY,VALUE\nA,1\nB,2\n");
});

it('csv export: an empty value set is just the header', function () {
    expect((new CsvFormat)->export([]))->toBe("KEY,VALUE\n");
});

it('csv export: rfc-4180 quotes commas, quotes (doubled) and newlines', function () {
    $csv = (new CsvFormat)->export([
        'COMMA' => 'hello, world',
        'QUOTE' => 'he said "hi"',
        'PLAIN' => 'safe',
    ]);

    expect($csv)->toBe("KEY,VALUE\nCOMMA,\"hello, world\"\nQUOTE,\"he said \"\"hi\"\"\"\nPLAIN,safe\n");
});

it('csv import: skips the header row and returns only data', function () {
    expect((new CsvFormat)->import("KEY,VALUE\nA,1\nB,2\n"))
        ->toBe(['A' => '1', 'B' => '2']);
});

it('csv import: keeps the first row when there is no header', function () {
    expect((new CsvFormat)->import("A,1\nB,2"))
        ->toBe(['A' => '1', 'B' => '2']);
});

it('csv import: only skips a KEY header when it is the first row', function () {
    // A "KEY" row that is not row 0 is treated as data, not a header.
    expect((new CsvFormat)->import("A,1\nKEY,VALUE"))
        ->toBe(['A' => '1', 'KEY' => 'VALUE']);
});

it('csv import: drops rows whose key is empty', function () {
    expect((new CsvFormat)->import("KEY,VALUE\n,orphan\nA,1"))
        ->toBe(['A' => '1']);
});

it('csv import: a key with no value column yields an empty string', function () {
    expect((new CsvFormat)->import("KEY,VALUE\nLONELY"))
        ->toBe(['LONELY' => '']);
});

it('csv import: trims surrounding whitespace before parsing', function () {
    // Leading newline must not push the header off row 0 (which would leak it as data).
    expect((new CsvFormat)->import("\nKEY,VALUE\nA,1\n"))
        ->toBe(['A' => '1']);
});

it('csv import: skips blank lines between rows', function () {
    expect((new CsvFormat)->import("KEY,VALUE\nA,1\n\nB,2"))
        ->toBe(['A' => '1', 'B' => '2']);
});

it('csv: round-trips comma and quote values', function () {
    $values = [
        'COMMA' => 'a, b',
        'QUOTE' => 'a"b',
        'PLAIN' => 'x',
    ];

    $csv = new CsvFormat;

    expect($csv->import($csv->export($values)))->toBe($values);
});

/*
|--------------------------------------------------------------------------
| JsonFormat (direct unit tests)
|--------------------------------------------------------------------------
*/

it('json export: is pretty-printed with unescaped slashes', function () {
    expect((new JsonFormat)->export(['URL' => 'http://x/y']))
        ->toBe("{\n    \"URL\": \"http://x/y\"\n}");
});

it('json import: casts scalars to string and nested arrays to json', function () {
    expect((new JsonFormat)->import('{"NUM":5,"FLAG":true,"NESTED":{"x":1},"STR":"keep"}'))
        ->toBe([
            'NUM' => '5',
            'FLAG' => '1',
            'NESTED' => '{"x":1}',
            'STR' => 'keep',
        ]);
});

it('json import: throws PortException on malformed json', function () {
    expect(fn () => (new JsonFormat)->import('{not valid'))
        ->toThrow(PortException::class);
});

it('json import: throws PortException when the payload is not an object/array', function () {
    expect(fn () => (new JsonFormat)->import('"just a string"'))
        ->toThrow(PortException::class);
});

it('json import: accepts nesting at the configured depth limit', function () {
    $atLimit = str_repeat('[', 511).str_repeat(']', 511);

    expect((new JsonFormat)->import($atLimit))->toBeArray();
});

it('json import: rejects nesting beyond the configured depth limit', function () {
    $tooDeep = str_repeat('[', 512).str_repeat(']', 512);

    expect(fn () => (new JsonFormat)->import($tooDeep))
        ->toThrow(PortException::class);
});

/*
|--------------------------------------------------------------------------
| Porter (direct unit tests)
|--------------------------------------------------------------------------
*/

it('porter: resolves a registered format by name', function () {
    $porter = (new Porter)->register(new JsonFormat);

    expect($porter->format('json'))->toBeInstanceOf(JsonFormat::class);
});

it('porter: throws PortException for an unknown format', function () {
    expect(fn () => (new Porter)->format('yaml'))
        ->toThrow(PortException::class);
});

it('porter: register adds and later overrides a format under its name', function () {
    $first = new JsonFormat;
    $second = new JsonFormat;

    $porter = (new Porter)->register($first)->register($second);

    expect($porter->names())->toBe(['json'])
        ->and($porter->format('json'))->toBe($second);
});

it('porter: names reflects registration order', function () {
    $porter = (new Porter)->register(new CsvFormat)->register(new JsonFormat);

    expect($porter->names())->toBe(['csv', 'json']);
});

it('porter: withDefaults registers json and csv', function () {
    expect(Porter::withDefaults()->names())->toBe(['json', 'csv'])
        ->and(Porter::withDefaults()->format('json'))->toBeInstanceOf(JsonFormat::class)
        ->and(Porter::withDefaults()->format('csv'))->toBeInstanceOf(CsvFormat::class);
});

it('porter: withDefaults appends consumer-registered extras', function () {
    $extra = new class implements PortFormatInterface
    {
        public function name(): string
        {
            return 'lines';
        }

        public function export(array $values): string
        {
            return '';
        }

        public function import(string $content): array
        {
            return [];
        }
    };

    expect(Porter::withDefaults($extra)->names())->toBe(['json', 'csv', 'lines']);
});
