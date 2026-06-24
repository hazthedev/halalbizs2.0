<?php

namespace App\Livewire\Storefront\Live;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\LiveSession;
use App\Services\LiveSessionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Live-commerce room (M2.4): the video embed, the spotlight + product rail
 * (add to cart through the unchanged checkout), a pinned voucher, and a polled
 * "just sold" feed. Read-only over catalogue/orders — no money path here.
 */
#[Layout('layouts.storefront')]
class Room extends Component
{
    use InteractsWithCart;

    public LiveSession $session;

    public function mount(LiveSession $session): void
    {
        abort_unless(config('live.enabled', true), 404);

        $this->session = $session->load([
            'store',
            'products.media', 'products.variants',
            'featuredProduct.media', 'featuredProduct.variants',
        ]);
    }

    public function render(): View
    {
        $service = app(LiveSessionService::class);

        return view('livewire.storefront.live.room', [
            'sold' => $this->session->isLive() ? $service->recentlySold($this->session) : collect(),
            'embedUrl' => $this->embedUrl(),
            'wishlistedIds' => $this->wishlistedIds(),
        ])->title($this->session->title);
    }

    /** Convert a known watch URL to an embeddable one; null for anything else. */
    private function embedUrl(): ?string
    {
        $url = trim((string) $this->session->video_url);

        if ($url === '') {
            return null;
        }

        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]{11})~', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        if (str_contains($url, 'youtube.com/embed/') || str_contains($url, 'facebook.com/plugins/')) {
            return $url;
        }

        return null;
    }
}
