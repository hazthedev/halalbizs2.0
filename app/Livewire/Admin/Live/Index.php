<?php

namespace App\Livewire\Admin\Live;

use App\Enums\LiveSessionStatus;
use App\Models\LiveSession;
use App\Services\LiveSessionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin live-commerce moderation (M2.4): see what's streaming and force-end a
 * problematic session.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    public function forceEnd(int $sessionId, LiveSessionService $live): void
    {
        $session = LiveSession::find($sessionId);

        if ($session && $session->status === LiveSessionStatus::Live) {
            $live->end($session);
            $this->dispatch('toast', message: __('Session ended by admin.'));
        }
    }

    public function render(): View
    {
        return view('livewire.admin.live.index', [
            'liveNow' => LiveSession::query()->live()->with('store')->withCount('products')->latest('started_at')->get(),
            'sessions' => LiveSession::query()
                ->with('store')
                ->whereIn('status', [LiveSessionStatus::Scheduled, LiveSessionStatus::Ended])
                ->latest('id')
                ->paginate(self::PER_PAGE),
        ])->title(__('Live shopping'));
    }
}
