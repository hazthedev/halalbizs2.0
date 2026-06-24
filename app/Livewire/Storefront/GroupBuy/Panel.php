<?php

namespace App\Livewire\Storefront\GroupBuy;

use App\Enums\GroupBuyTeamStatus;
use App\Exceptions\CheckoutException;
use App\Models\GroupBuy;
use App\Models\GroupBuyTeam;
use App\Models\Product;
use App\Services\GroupBuyService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * PDP group-buy panel (M2.6): lists this product's live deals, lets a shopper
 * start a team or join an open one, and surfaces the share link. Thin — all
 * state changes route through GroupBuyService under its locks.
 */
class Panel extends Component
{
    #[Locked]
    public int $productId;

    public function mount(Product $product): void
    {
        $this->productId = $product->id;
    }

    public function start(int $dealId, GroupBuyService $groupBuy): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $deal = GroupBuy::find($dealId);

        if ($deal === null) {
            return;
        }

        try {
            $team = $groupBuy->startTeam(auth()->user(), $deal);
            $this->redirectRoute('group-buy.team', ['team' => $team->code], navigate: true);
        } catch (CheckoutException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(GroupBuyService $groupBuy): View
    {
        $deals = $groupBuy->enabled()
            ? GroupBuy::query()->live()
                ->where('product_id', $this->productId)
                ->with(['variant'])
                ->withCount(['teams as forming_teams_count' => fn ($q) => $q->where('status', GroupBuyTeamStatus::Forming)])
                ->get()
            : collect();

        // A few open teams per deal a shopper could join right now.
        $openTeams = $deals->isEmpty()
            ? collect()
            : GroupBuyTeam::query()
                ->whereIn('group_buy_id', $deals->modelKeys())
                ->where('status', GroupBuyTeamStatus::Forming)
                ->where('expires_at', '>', now())
                ->withCount('members')
                ->with('initiator:id,name')
                ->latest('id')
                ->limit(5)
                ->get()
                ->groupBy('group_buy_id');

        return view('livewire.storefront.group-buy.panel', [
            'deals' => $deals,
            'openTeams' => $openTeams,
        ]);
    }
}
