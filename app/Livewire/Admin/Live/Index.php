<?php

namespace App\Livewire\Admin\Live;

use App\Enums\LiveSessionStatus;
use App\Models\LiveSession;
use App\Services\LiveSessionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\WithTable;
use App\Support\JsonSearch;
use Livewire\Component;

/**
 * Admin live-commerce moderation (M2.4): see what's streaming and force-end a
 * problematic session.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    protected function tableFilters(): array
    {
        return [];
    }

    protected function tableSorts(): array
    {
        return ['session' => 'title', 'status' => 'status'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['id' => 'desc'];
    }

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
            'sessions' => $this->results(),
        ])->title(__('Live shopping'));
    }

    private function results()
    {
        $term = JsonSearch::pattern($this->search);

        return $this->sortTable(LiveSession::query()
            ->with('store')
            ->whereIn('status', [LiveSessionStatus::Scheduled, LiveSessionStatus::Ended])
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw(JsonSearch::clause('title'), [$term])
                ->orWhereHas('store', fn ($s) => $s->whereRaw(JsonSearch::clause('name'), [$term])))))
            ->paginate($this->tablePageSize());
    }
}
