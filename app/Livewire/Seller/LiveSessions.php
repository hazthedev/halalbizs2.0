<?php

namespace App\Livewire\Seller;

use App\Livewire\Concerns\CurrentStore;
use App\Models\LiveSession;
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

    public function mount(): void
    {
        abort_unless(config('live.enabled', true), 404);
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
        ])->title(__('Live shopping'));
    }
}
