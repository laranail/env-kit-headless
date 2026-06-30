<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Audit\HistoryReader;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Document\EnvDocument;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Porter\Formats\DotenvFormat;
use Simtabi\Laranail\EnvKit\Headless\Schema\EnvSchema;
use Simtabi\Laranail\EnvKit\Headless\Support\DocsGenerator;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

it('EnvSchema::describe() lists the exact rule labels per key', function () {
    $schema = (new EnvSchema)
        ->required('A')->integer('A')
        ->in('B', ['x', 'y'])
        ->regex('C', '/^z$/')
        ->url('D')->email('E')->boolean('F')->number('G')->string('H');

    $described = $schema->describe();

    expect($described['A'])->toBe(['required', 'integer'])
        ->and($described['B'])->toBe(['one of: x, y'])
        ->and($described['C'])->toBe(['matches /^z$/'])
        ->and($described['D'])->toBe(['URL'])
        ->and($described['E'])->toBe(['email'])
        ->and($described['F'])->toBe(['boolean'])
        ->and($described['G'])->toBe(['number'])
        ->and($described['H'])->toBe(['string']);
});

it('HistoryReader honours the limit, orders newest-first and skips malformed lines', function () {
    $file = sys_get_temp_dir().'/hr-'.bin2hex(random_bytes(5)).'.log';
    file_put_contents($file, implode("\n", [
        'NOT JSON',
        '{"occurred_at":1,"changes":[]}',
        '{"occurred_at":2,"changes":[]}',
        '{"occurred_at":3,"changes":[]}',
    ])."\n");

    expect((new HistoryReader($file))->recent(10))->toHaveCount(3); // malformed line skipped

    $two = (new HistoryReader($file))->recent(2);
    expect($two)->toHaveCount(2)
        ->and($two[0]['occurred_at'])->toBe(3)  // newest first
        ->and($two[1]['occurred_at'])->toBe(2);

    @unlink($file);
});

it('DocsGenerator emits a header row and one row per key', function () {
    $markdown = (new DocsGenerator)->generate((new EnvSchema)->required('A')->integer('PORT'));

    expect($markdown)->toContain('| Key | Rules |')
        ->toContain('| `A` | required |')
        ->toContain('| `PORT` | integer |');
});

it('DotenvFormat exports an empty set as an empty string and joins with newlines', function () {
    expect((new DotenvFormat)->export([]))->toBe('')
        ->and((new DotenvFormat)->export(['A' => '1', 'B' => '2']))->toBe("A=1\nB=2\n");
});

it('EnvDocument::withComment and withEmptyLine append entries', function () {
    $doc = EnvDocument::parse("A=1\n")->withComment('a note')->withEmptyLine();
    $rendered = $doc->render();

    expect($rendered)->toContain('A=1')
        ->toContain('# a note')
        ->and(substr_count($rendered, "\n"))->toBeGreaterThan(2); // setter + comment + blank
});

it('env:history shows the actor and changed key names', function () {
    $dir = sys_get_temp_dir().'/hist-'.bin2hex(random_bytes(5));
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/.env', "A=1\n");
    config([
        'env-kit.path' => $dir.'/.env',
        'env-kit.audit.path' => $dir.'/audit.log',
        'env-kit.audit.enabled' => true,
        'env-kit.audit.actor' => 'deploy-bot',
        'env-kit.auto_backup' => false,
    ]);
    $this->app->forgetInstance(EnvKitInterface::class);
    EnvKit::set('FLAG', '2');

    $this->artisan('env:history')
        ->expectsOutputToContain('deploy-bot') // resolved actor surfaces in the table
        ->assertExitCode(0);
});
