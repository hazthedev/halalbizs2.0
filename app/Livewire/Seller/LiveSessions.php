<?php

namespace App\Livewire\Seller;

use App\Livewire\Concerns\CurrentStore;
use App\Models\LiveSession;
use App\Models\Product;
use App\Services\LiveSessionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Seller live studio (M2.4): schedule a session, build its product rail, go
 * live, spotlight a product and end the stream. Store-scoped; presentation only.
 */
#[Layout('layouts.seller')]
class LiveSessions extends Component
{
    use CurrentStore;

    public bool $showForm = false;

    public string $title = '';

    public string $videoUrl = '';

    public string $voucherCode = '';

    public string $scheduledFor = '';

    /** The session currently being managed (rail editor). */
    public ?int $managingId = null;

    public ?int $addProductId = null;

    public function mount(): void
    {
        abort_unless(config('live.enabled', true), 404);
    }

    public function create(): void
    {
        $this->reset(['title', 'videoUrl', 'voucherCode', 'scheduledFor']);
        $this->showForm = true;
    }

    public function save(LiveSessionService $live): void
    {
        $validated = $this->validate([
            'title' => 'required|string|max:120',
            'videoUrl' => 'nullable|url|max:500',
            'voucherCode' => 'nullable|string|max:40',
            'scheduledFor' => 'nullable|date',
        ]);

        $session = $live->create($this->currentStore(), [
            'title' => $validated['title'],
            'video_url' => $validated['videoUrl'] ?: null,
            'voucher_code' => $validated['voucherCode'] ? strtoupper($validated['voucherCode']) : null,
            'scheduled_for' => $validated['scheduledFor'] ?: null,
        ]);

        $this->showForm = false;
        $this->managingId = $session->id;
        $this->dispatch('toast', message: __('Session created — add products to your rail.'), type: 'success');
    }

    public function manage(int $id): void
    {
        $this->managingId = $this->managingId === $id ? null : $id;
        $this->addProductId = null;
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

    public function goLive(int $id, LiveSessionService $live): void
    {
        if ($session = $this->ownedSession($id)) {
            $live->goLive($session);
            $this->dispatch('toast', message: __('You’re live!'), type: 'success');
        }
    }

    public function end(int $id, LiveSessionService $live): void
    {
        if ($session = $this->ownedSession($id)) {
            $live->end($session);
            $this->dispatch('toast', message: __('Session ended.'), type: 'success');
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
        $store = $this->currentStore();

        return view('livewire.seller.live-sessions', [
            'sessions' => LiveSession::where('store_id', $store->id)
                ->with(['products:id', 'featuredProduct:id,name'])
                ->latest('id')
                ->get(),
            'managing' => $this->managingId ? $this->ownedSession($this->managingId)?->load('products') : null,
            'products' => Product::where('store_id', $store->id)->orderBy('id')->get(['id', 'name']),
        ])->title(__('Live shopping'));
    }
}
