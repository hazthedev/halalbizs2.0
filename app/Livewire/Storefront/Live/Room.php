<?php

namespace App\Livewire\Storefront\Live;

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\LiveSession;
use App\Services\LiveSessionService;
use App\Support\UrlHost;
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

    /** Hosts whose watch URLs we will read a video id out of. */
    private const WATCH_HOSTS = ['youtube.com', 'youtu.be'];

    /** Hosts whose embed URLs we will place in an iframe verbatim. */
    private const EMBED_HOSTS = ['youtube.com', 'youtube-nocookie.com', 'facebook.com'];

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

    /**
     * Convert a seller's watch URL into something safe to put in an iframe src;
     * null for anything else.
     *
     * Two paths, and the difference matters. A recognised watch URL is not
     * passed through — we extract the id and BUILD a known-good embed URL, so
     * whatever else was in the seller's string is discarded. Only the second
     * path hands their string to the browser verbatim, and that one is gated on
     * the parsed HOST via UrlHost.
     *
     * It used to be gated on `str_contains($url, 'youtube.com/embed/')`, which
     * `https://evil.com/#youtube.com/embed/` satisfies — a substring test asks
     * about the whole URL when only the host decides where the browser goes.
     */
    private function embedUrl(): ?string
    {
        $url = trim((string) $this->session->video_url);

        if ($url === '') {
            return null;
        }

        // Rebuilt from the id, so this path is safe regardless of the rest of
        // the string — but it must still be a real YouTube host, or a link to
        // evil.example that merely mentions youtube.com/watch?v= would silently
        // render someone else's video.
        if (UrlHost::isOn($url, self::WATCH_HOSTS)
            && preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]{11})~', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        // Passed through verbatim — so the host has to be provably ours.
        if (UrlHost::isOn($url, self::EMBED_HOSTS)
            && (str_contains($url, '/embed/') || str_contains($url, '/plugins/'))) {
            return $url;
        }

        return null;
    }
}
