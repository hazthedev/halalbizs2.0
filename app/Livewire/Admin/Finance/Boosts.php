<?php

namespace App\Livewire\Admin\Finance;

use App\Livewire\Concerns\WithTable;
use App\Models\ProductBoost;
use App\Support\DisplayTime;
use App\Support\JsonSearch;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Boost oversight (Phase-4): every boost across stores plus a revenue
 * summary by period. Boost fees are platform income — they were debited
 * from seller available balances via LedgerService::chargeBoost and are
 * never refunded (cancelled boosts still count). Integer sen throughout.
 */
#[Layout('layouts.admin')]
class Boosts extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    protected function tableFilters(): array
    {
        return [];
    }

    protected function tableSorts(): array
    {
        return ['window' => 'starts_at', 'fee' => 'amount_sen', 'status' => 'status'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['created_at' => 'desc'];
    }

    public function render()
    {
        return view('livewire.admin.finance.boosts', [
            'boosts' => $this->results(),
            'revenue' => $this->revenue(),
        ])->title(__('Boosts'));
    }

    /** @return array<string, int> period key → revenue in sen (sum of fees charged in the period) */
    private function results()
    {
        $term = JsonSearch::pattern($this->search);

        return $this->sortTable(ProductBoost::query()
            ->with(['product', 'store'])
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('store', fn ($s) => $s->whereRaw(JsonSearch::clause('name'), [$term]))
                ->orWhereHas('product', fn ($p) => $p->whereRaw(JsonSearch::clause('name'), [$term])))))
            ->paginate($this->tablePageSize());
    }

    private function revenue(): array
    {
        $sum = fn ($query) => (int) $query->sum('amount_sen');

        return [
            'today' => $sum(ProductBoost::query()->where('created_at', '>=', DisplayTime::dayStart())),
            'week' => $sum(ProductBoost::query()->where('created_at', '>=', now()->subDays(7))),
            'month' => $sum(ProductBoost::query()->where('created_at', '>=', now()->subDays(30))),
            'all' => $sum(ProductBoost::query()),
        ];
    }
}
