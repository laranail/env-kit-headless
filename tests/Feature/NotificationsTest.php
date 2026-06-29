<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Simtabi\Laranail\EnvKit\Headless\Extension\EnvKitConfigurator;
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

it('summarizes the event with redacted, attributed content', function () {
    $this->bindEnv("DB_PASSWORD=old\n", enableEnvKitNotifications());
    app(EnvKitConfigurator::class)->resolveActorUsing(fn () => 'alice');
    Notification::fake();

    EnvKit::set('DB_PASSWORD', 'newsecret');

    Notification::assertSentOnDemand(
        EnvKitEventNotification::class,
        function (EnvKitEventNotification $n): bool {
            return $n->summary['event'] === 'after_write'
                && $n->summary['actor'] === 'alice'
                && $n->summary['changes'][0]['new'] === '••••••'
                && $n->via(new AnonymousNotifiable) === ['mail']
                && $n->toArray(new AnonymousNotifiable) === $n->summary;
        },
    );
});

it('notifies each routed channel and includes the rejection reason', function () {
    $this->bindEnv("A=1\n", [
        ...enableEnvKitNotifications(),
        'env-kit.notifications.channels' => ['mail', 'slack'],
        'env-kit.notifications.routes' => ['mail' => 'ops@example.com', 'slack' => 'https://hooks.example/x'],
        'env-kit.notifications.events' => ['write_rejected'],
        'env-kit.protected_keys' => ['LOCKED'],
    ]);
    Notification::fake();

    try {
        EnvKit::set('LOCKED', 'x');
    } catch (Throwable) {
        // expected — we only care that the rejection notified
    }

    Notification::assertSentOnDemandTimes(EnvKitEventNotification::class, 2); // one per channel
    Notification::assertSentOnDemand(
        EnvKitEventNotification::class,
        fn (EnvKitEventNotification $n): bool => $n->summary['event'] === 'write_rejected' && $n->summary['reason'] === 'protected',
    );
});
