<?php

namespace App\Listeners;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Every notification that lands in the bell (database channel) also goes to
 * the user's phones (mobile plan §5) — without touching the 21 notification
 * classes. Queued so the FCM round trip never sits inside a web request, and
 * skipped entirely while no Firebase credentials are configured.
 */
class PushDatabaseNotification implements ShouldQueue
{
    public function __construct(private FcmChannel $channel) {}

    public function shouldQueue(NotificationSent $event): bool
    {
        return $event->channel === 'database' && filled(config('services.fcm.credentials'));
    }

    public function handle(NotificationSent $event): void
    {
        $this->channel->send($event->notifiable, $event->notification);
    }
}
