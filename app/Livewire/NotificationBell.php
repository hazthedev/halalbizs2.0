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

    /** Fingerprint of what the last render showed; the poll tick compares against it. */
    public string $seen = '';

    /**
     * wire:poll target. One aggregate query; the component only re-renders
     * when a notification arrived or changed read state since the last render.
     */
    public function tick(): void
    {
        if ($this->fingerprint() === $this->seen) {
            $this->skipRender();
        }
    }

    private function fingerprint(): string
    {
        $row = auth()->user()->notifications()->reorder()
            ->toBase()
            ->selectRaw('count(*) as total, coalesce(sum(read_at is null), 0) as unread, max(created_at) as newest')
            ->first();

        return $row->total.'|'.$row->unread.'|'.$row->newest;
    }

    public function open(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->where('id', $notificationId)->first();

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $this->redirect($url, navigate: parse_url($url, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST));
        }
    }

    public function render()
    {
        $user = auth()->user();
        $this->seen = $this->fingerprint();

        return view('livewire.notification-bell', [
            'notifications' => $user->notifications()->latest()->limit(10)->get(),
            'unreadCount' => (int) explode('|', $this->seen)[1],
            'viewAllUrl' => match ($this->context) {
                'seller' => route('seller.notifications'),
                'admin' => route('admin.notifications'),
                default => route('account.notifications'),
            },
        ]);
    }
}
