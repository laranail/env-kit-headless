<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A single, channel-agnostic notification for any EnvKit lifecycle event. Queued so
 * sending never blocks the write (falls back to sync without a worker). The summary
 * carries ALREADY-REDACTED values + the actor — no secret reaches a channel.
 */
final class EnvKitEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly array $summary,
        private readonly array $channels,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = is_string($this->summary['event'] ?? null) ? $this->summary['event'] : 'change';
        $message = (new MailMessage)
            ->subject("EnvKit: {$event}")
            ->line('File: '.($this->stringField('path') ?? 'unknown'))
            ->line('Actor: '.($this->stringField('actor') ?? 'unknown'));

        if (($reason = $this->stringField('reason')) !== null) {
            $message->line("Reason: {$reason}");
        }

        return $message->line('All values are redacted in this notification.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->summary;
    }

    private function stringField(string $key): ?string
    {
        $value = $this->summary[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
