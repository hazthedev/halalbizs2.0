<?php

namespace App\Livewire\Storefront\Live;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\LiveSession;
use App\Services\LiveSessionService;
use App\Support\LiveVideoUrl;
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
            'video' => $this->video(),
            'wishlistedIds' => $this->wishlistedIds(),
        ])->title($this->session->title);
    }

    /** Parsed once per render: ['platform', 'embed', 'link'] or null. See LiveVideoUrl. */
    private function video(): ?array
    {
        return LiveVideoUrl::parse($this->session->video_url);
    }
}
