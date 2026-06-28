<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Contracts\PortFormatInterface;
use Simtabi\Laranail\EnvKit\Headless\Exceptions\PortException;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
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
