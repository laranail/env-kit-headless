<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Simtabi\Laranail\EnvKit\Headless\Notifications\EnvKitEventNotification;

function envKitNotification(array $summary, array $channels = ['mail']): EnvKitEventNotification
{
    return new EnvKitEventNotification($summary, $channels);
}

it('routes to its configured channels and returns the summary as the array payload', function () {
    $summary = ['event' => 'after_write', 'path' => '/app/.env', 'actor' => 'alice'];
    $notification = envKitNotification($summary, ['mail', 'slack']);

    expect($notification->via(new AnonymousNotifiable))->toBe(['mail', 'slack'])
        ->and($notification->toArray(new AnonymousNotifiable))->toBe($summary);
});

it('renders a mail message from the summary fields', function () {
    $notification = envKitNotification([
        'event' => 'write_rejected',
        'path' => '/srv/app/.env',
        'actor' => 'deploy-bot',
        'reason' => 'protected',
    ]);

    $mail = $notification->toMail(new AnonymousNotifiable);
    $body = implode("\n", array_map(fn ($line): string => is_string($line) ? $line : '', $mail->introLines));

    expect($mail->subject)->toBe('EnvKit: write_rejected')
        ->and($body)->toContain('/srv/app/.env')
        ->and($body)->toContain('deploy-bot')
        ->and($body)->toContain('Reason: protected')
        ->and($body)->toContain('redacted');
});

it('falls back gracefully when summary fields are missing or non-string', function () {
    $mail = envKitNotification(['event' => ['not', 'a', 'string']])->toMail(new AnonymousNotifiable);
    $body = implode("\n", array_map(fn ($line): string => is_string($line) ? $line : '', $mail->introLines));

    expect($mail->subject)->toBe('EnvKit: change')   // non-string event → 'change'
        ->and($body)->toContain('File: unknown')      // missing path → 'unknown'
        ->and($body)->toContain('Actor: unknown')     // missing actor → 'unknown'
        ->and($body)->not->toContain('Reason:');      // no reason line when absent
});
