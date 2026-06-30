<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Audit\HistoryReader;
use Simtabi\Laranail\EnvKit\Headless\Contracts\EnvKitInterface;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Schema\EnvSchema;
use Simtabi\Laranail\EnvKit\Headless\Support\DocsGenerator;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

function envkitAuditDir(): string
{
    $dir = sys_get_temp_dir().'/envkit-hist-'.bin2hex(random_bytes(5));
    @mkdir($dir, 0777, true);

    return $dir;
}

it('records changes and reads them back most-recent-first', function () {
    $dir = envkitAuditDir();
    file_put_contents($dir.'/.env', "A=1\n");
    config([
        'env-kit.path' => $dir.'/.env',
        'env-kit.audit.path' => $dir.'/audit.log',
        'env-kit.audit.enabled' => true,
        'env-kit.auto_backup' => false,
    ]);
    $this->app->forgetInstance(EnvKitInterface::class);

    EnvKit::set('B', '2');
    EnvKit::set('C', '3');

    $entries = (new HistoryReader($dir.'/audit.log'))->recent(10);

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['changes'][0]['key'])->toBe('C')  // newest first (list of {key,old,new})
        ->and($entries[1]['changes'][0]['key'])->toBe('B');
});

it('HistoryReader returns empty for a missing log', function () {
    expect((new HistoryReader('/no/such/envkit-audit.log'))->recent())->toBe([]);
});

it('the env:history command tables changes and reports an empty log', function () {
    $dir = envkitAuditDir();
    file_put_contents($dir.'/.env', "A=1\n");
    config([
        'env-kit.path' => $dir.'/.env',
        'env-kit.audit.path' => $dir.'/audit.log',
        'env-kit.audit.enabled' => true,
        'env-kit.auto_backup' => false,
    ]);
    $this->app->forgetInstance(EnvKitInterface::class);
    EnvKit::set('NEW_FLAG', '2');

    $this->artisan('env:history')
        ->expectsOutputToContain('Keys changed')
        ->expectsOutputToContain('NEW_FLAG') // shows the key NAME, not an index
        ->assertExitCode(0);

    config(['env-kit.audit.path' => $dir.'/empty.log']);
    $this->artisan('env:history')->expectsOutputToContain('No audit history')->assertExitCode(0);
});

it('DocsGenerator renders the schema as a markdown table', function () {
    $schema = (new EnvSchema)->required('APP_KEY')->in('APP_ENV', ['local', 'production'])->integer('PORT');

    $markdown = (new DocsGenerator)->generate($schema);

    expect($markdown)->toContain('# Environment schema')
        ->toContain('`APP_KEY`')->toContain('required')
        ->toContain('one of: local, production')
        ->toContain('`PORT`')->toContain('integer');
});

it('DocsGenerator renders a placeholder when no schema is defined', function () {
    expect((new DocsGenerator)->generate(new EnvSchema))->toContain('No schema rules');
});

it('the env:docs command prints markdown and writes to a file', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false]);
    EnvKit::schema()->required('A');

    $this->artisan('env:docs')->expectsOutputToContain('Environment schema')->assertExitCode(0);

    $out = sys_get_temp_dir().'/envkit-docs-'.bin2hex(random_bytes(5)).'.md';
    $this->artisan('env:docs', ['--output' => $out])->expectsOutputToContain('Wrote')->assertExitCode(0);
    expect((string) file_get_contents($out))->toContain('`A`');
    @unlink($out);
});
