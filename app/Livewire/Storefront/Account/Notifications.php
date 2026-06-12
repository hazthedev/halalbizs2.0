<?php

namespace App\Livewire\Storefront\Account;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class Notifications extends Component
{
    public function markRead(string $notificationId): void
    {
        auth()->user()->notifications()->where('id', $notificationId)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();

        $this->dispatch('toast', message: __('All notifications marked as read'));
    }

    public function render()
    {
        return view('livewire.storefront.account.notifications', [
            'notifications' => auth()->user()->notifications()->latest()->limit(50)->get(),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ])->title(__('Notifications'));
    }
}
