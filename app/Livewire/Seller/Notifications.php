<?php

namespace App\Livewire\Seller;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Full-page notification list for the seller centre — same row pattern as
 * the buyer page; clicking a row marks it read and follows data['url'].
 */
#[Layout('layouts.seller')]
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
        return view('livewire.seller.notifications', [
            'notifications' => auth()->user()->notifications()->latest()->limit(50)->get(),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ])->title(__('Notifications'));
    }
}
