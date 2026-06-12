<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Topbar notification bell — one component for all three contexts. The
 * `context` prop only decides where "View all" points; the notification
 * stream is always the authenticated user's.
 */
class NotificationBell extends Component
{
    public string $context = 'storefront'; // storefront | seller | admin

    public function open(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->where('id', $notificationId)->first();

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $this->redirect($url, navigate: true);
        }
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'notifications' => $user->notifications()->latest()->limit(10)->get(),
            'unreadCount' => $user->unreadNotifications()->count(),
            'viewAllUrl' => match ($this->context) {
                'seller' => route('seller.notifications'),
                'admin' => route('admin.notifications'),
                default => route('account.notifications'),
            },
        ]);
    }
}
