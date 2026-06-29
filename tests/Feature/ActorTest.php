<?php

declare(strict_types=1);

use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

function lastAuditActor(string $auditPath): mixed
{
    $lines = file($auditPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $last = end($lines);

    return is_string($last) ? (json_decode($last, true)['actor'] ?? null) : null;
}

it('records the resolved actor on the audit trail', function () {
    $path = $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false, 'env-kit.audit.enabled' => true]);

    app(EnvKitConfigurator::class)->resolveActorUsing(fn () => 'alice');
    EnvKit::set('A', '2');

    expect(lastAuditActor(dirname($path).'/audit.log'))->toBe('alice');
});

it('honours a config actor override', function () {
    $path = $this->bindEnv("A=1\n", [
        'env-kit.auto_backup' => false,
        'env-kit.audit.enabled' => true,
        'env-kit.audit.actor' => 'ci-bot',
    ]);

    EnvKit::set('A', '2');

    expect(lastAuditActor(dirname($path).'/audit.log'))->toBe('ci-bot');
});
