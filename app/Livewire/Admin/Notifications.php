<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Full-page notification list for the admin panel — operational alerts
 * (AdminAlertNotification) plus anything else sent to this user. Clicking
 * a row marks it read and follows data['url'].
 */
#[Layout('layouts.admin')]
class Notifications extends Component
{
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

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();

        $this->dispatch('toast', message: __('All notifications marked as read'));
    }

    public function render()
    {
        return view('livewire.admin.notifications', [
            'notifications' => auth()->user()->notifications()->latest()->limit(50)->get(),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ])->title(__('Notifications'));
    }
}
