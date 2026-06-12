<?php

namespace App\Livewire\Admin\Finance;

use App\Models\ProductBoost;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Boost oversight (Phase-4): every boost across stores plus a revenue
 * summary by period. Boost fees are platform income — they were debited
 * from seller available balances via LedgerService::chargeBoost and are
 * never refunded (cancelled boosts still count). Integer sen throughout.
 */
#[Layout('layouts.admin')]
class Boosts extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    public function render()
    {
        return view('livewire.admin.finance.boosts', [
            'boosts' => ProductBoost::query()
                ->with(['product', 'store'])
                ->latest()
                ->latest('id')
                ->paginate(self::PER_PAGE),
            'revenue' => $this->revenue(),
        ])->title(__('Boosts'));
    }

    /** @return array<string, int> period key → revenue in sen (sum of fees charged in the period) */
    private function revenue(): array
    {
        $sum = fn ($query) => (int) $query->sum('amount_sen');

        return [
            'today' => $sum(ProductBoost::query()->where('created_at', '>=', now()->startOfDay())),
            'week' => $sum(ProductBoost::query()->where('created_at', '>=', now()->subDays(7))),
            'month' => $sum(ProductBoost::query()->where('created_at', '>=', now()->subDays(30))),
            'all' => $sum(ProductBoost::query()),
        ];
    }
}
