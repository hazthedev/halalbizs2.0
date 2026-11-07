<?php

namespace Tests\Fixtures;

use Illuminate\Notifications\Notification;

/**
 * A database notification shaped like the 21 real ones (message + url). A
 * named class because the queued push listener serializes the notification,
 * which an anonymous class cannot be.
 */
class ApiPushProbeNotification extends Notification
{
    public function __construct(public string $url) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => 'Order shipped — SO-1', 'url' => $this->url];
    }
}
