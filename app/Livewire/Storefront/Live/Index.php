<?php

namespace App\Livewire\Storefront\Live;

use App\Enums\LiveSessionStatus;
use App\Models\LiveSession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Live-commerce hub (M2.4): what's streaming now and what's coming up.
 */
#[Layout('layouts.storefront')]
class Index extends Component
{
    public function mount(): void
    {
        abort_unless(config('live.enabled', true), 404);
    }

    public function render(): View
    {
        return view('livewire.storefront.live.index', [
            'liveNow' => LiveSession::query()->live()
                ->with('store')
                ->withCount('products')
                ->latest('started_at')
                ->get(),
            'upcoming' => LiveSession::query()
                ->where('status', LiveSessionStatus::Scheduled)
                ->whereNotNull('scheduled_for')
                ->where('scheduled_for', '>', now())
                ->with('store')
                ->orderBy('scheduled_for')
                ->limit(12)
                ->get(),
        ])->title(__('Live shopping'));
    }
}
