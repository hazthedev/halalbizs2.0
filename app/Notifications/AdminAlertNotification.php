<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Operational alert for admin-role users — DATABASE ONLY (the admin bell
 * and /admin/notifications page; never mail). Fired by AdminAlertObserver
 * on pending stores, payout requests, escalated returns and iPay88
 * signature mismatches.
 */
class AdminAlertNotification extends Notification
{
    public function __construct(
        public string $message,
        public ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
