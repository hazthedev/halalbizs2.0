<?php

namespace App\Livewire\Seller\LiveSessions;

use App\Livewire\Concerns\CurrentStore;
use App\Models\LiveSession;
use App\Models\Product;
use App\Services\LiveSessionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Seller live studio (M2.4): schedule a session, build its product rail, go
 * live, spotlight a product and end the stream. Store-scoped; presentation only.
 */
#[Layout('layouts.seller')]
class Detail extends Component
{
    use CurrentStore;

    /** The session currently being managed (rail editor). */
    #[Locked]
    public ?int $managingId = null;

    public ?int $addProductId = null;

    public function mount(LiveSession $session): void
    {
        abort_unless(config('live.enabled', true), 404);
        $this->managingId = LiveSession::where('store_id', $this->currentStore()->id)->findOrFail($session->id)->id;
    }

    public function addProduct(LiveSessionService $live): void
    {
        $session = $this->ownedSession($this->managingId);
        $product = Product::where('id', $this->addProductId)->where('store_id', $this->currentStore()->id)->first();

        if ($session && $product) {
            $live->addProduct($session, $product);
            $this->addProductId = null;
        }
    }

    public function removeProduct(int $sessionId, int $productId, LiveSessionService $live): void
    {
        $session = $this->ownedSession($sessionId);
        $product = Product::find($productId);

        if ($session && $product) {
            $live->removeProduct($session, $product);
        }
    }

    public function feature(int $sessionId, int $productId, LiveSessionService $live): void
    {
        $session = $this->ownedSession($sessionId);
        $product = Product::find($productId);

        if ($session && $product) {
            $live->feature($session, $product);
        }
    }

    private function ownedSession(?int $id): ?LiveSession
    {
        if ($id === null) {
            return null;
        }

        return LiveSession::where('id', $id)->where('store_id', $this->currentStore()->id)->first();
    }

    public function render(): View
    {
        return view('livewire.seller.live-sessions.detail', [
            'managing' => $this->ownedSession($this->managingId)->load('products'),
            'products' => Product::where('store_id', $this->currentStore()->id)->orderBy('id')->get(['id', 'name']),
        ])->title(__('Manage rail'));
    }
}
