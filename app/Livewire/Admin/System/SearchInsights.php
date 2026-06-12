<?php

namespace App\Livewire\Admin\System;

use App\Models\SearchLog;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Search insights (docs/09 §E): trending terms (7d, with results) and the
 * zero-result report — the cheapest catalogue-gap detector there is.
 */
#[Layout('layouts.admin')]
class SearchInsights extends Component
{
    public function render()
    {
        $trending = SearchLog::query()
            ->selectRaw('term, count(*) as searches, max(created_at) as last_seen')
            ->where('created_at', '>=', now()->subDays(7))
            ->where('results_count', '>', 0)
            ->groupBy('term')
            ->orderByDesc('searches')
            ->limit(20)
            ->get();

        // Zero-result gets a wider window — gaps stay gaps until fixed.
        $zeroResult = SearchLog::query()
            ->selectRaw('term, count(*) as searches, max(created_at) as last_seen')
            ->where('created_at', '>=', now()->subDays(30))
            ->where('results_count', 0)
            ->groupBy('term')
            ->orderByDesc('searches')
            ->limit(20)
            ->get();

        $total14d = SearchLog::query()->where('created_at', '>=', now()->subDays(14))->count();

        return view('livewire.admin.system.search-insights', [
            'trending' => $trending,
            'zeroResult' => $zeroResult,
            'total14d' => $total14d,
            'perDay14d' => intdiv($total14d, 14),
        ])->title(__('Search insights'));
    }
}
