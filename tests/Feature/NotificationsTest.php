<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Simtabi\Laranail\EnvKit\Headless\Facades\EnvKit;
use Simtabi\Laranail\EnvKit\Headless\Notifications\EnvKitEventNotification;
use Simtabi\Laranail\EnvKit\Headless\Tests\TestCase;

uses(TestCase::class);

function enableEnvKitNotifications(): array
{
    return [
        'env-kit.auto_backup' => false,
        'env-kit.notifications.enabled' => true,
        'env-kit.notifications.channels' => ['mail'],
        'env-kit.notifications.routes' => ['mail' => 'ops@example.com'],
        'env-kit.notifications.events' => ['after_write'],
        'env-kit.notifications.production_only' => false,
    ];
}

it('sends an on-demand notification on a configured event', function () {
    $this->bindEnv("A=1\n", enableEnvKitNotifications());
    Notification::fake();

    EnvKit::set('A', '2');

    Notification::assertSentOnDemand(
        EnvKitEventNotification::class,
        fn (EnvKitEventNotification $n) => $n->summary['event'] === 'after_write',
    );
});

it('sends nothing when disabled', function () {
    $this->bindEnv("A=1\n", ['env-kit.auto_backup' => false, 'env-kit.notifications.enabled' => false]);
    Notification::fake();

    EnvKit::set('A', '2');

    Notification::assertNothingSent();
});

it('suppresses notifications outside production when production_only', function () {
    $this->bindEnv("A=1\n", [...enableEnvKitNotifications(), 'env-kit.notifications.production_only' => true]);
    Notification::fake();

    EnvKit::set('A', '2'); // tests do not run as production

    Notification::assertNothingSent();
});

it('skips a channel whose route target is null', function () {
    $this->bindEnv("A=1\n", [...enableEnvKitNotifications(), 'env-kit.notifications.routes' => ['mail' => null]]);
    Notification::fake();

    EnvKit::set('A', '2');

    Notification::assertNothingSent();
});
