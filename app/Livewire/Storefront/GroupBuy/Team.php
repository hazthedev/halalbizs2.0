<?php

namespace App\Livewire\Storefront\GroupBuy;

use App\Enums\GroupBuyMemberStatus;
use App\Exceptions\CheckoutException;
use App\Models\GroupBuyTeam;
use App\Services\GroupBuyService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Group-buy team page (M2.6): the share-link destination. Shows progress and
 * lets a shopper join; once unlocked, members are sent to add the item and
 * check out at the deal price.
 */
#[Layout('layouts.storefront')]
class Team extends Component
{
    public GroupBuyTeam $team;

    public function mount(GroupBuyTeam $team): void
    {
        abort_unless(config('groupbuy.enabled', true), 404);

        $this->team = $team->load('groupBuy.variant.product', 'groupBuy.product');
    }

    public function join(GroupBuyService $groupBuy): void
    {
        if (! auth()->check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        try {
            $groupBuy->joinTeam(auth()->user(), $this->team);
            $this->team->refresh();
            $this->dispatch('toast', message: __('You’re in! Invite friends to unlock the price.'), type: 'success');
        } catch (CheckoutException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $this->team->loadCount('members');
        $deal = $this->team->groupBuy;

        $member = auth()->check()
            ? $this->team->members()->where('user_id', auth()->id())->first()
            : null;

        return view('livewire.storefront.group-buy.team', [
            'deal' => $deal,
            'product' => $deal->product,
            'memberCount' => $this->team->members_count,
            'isMember' => $member !== null,
            'hasPurchased' => $member?->status === GroupBuyMemberStatus::Purchased,
        ])->title(__('Group buy'));
    }
}
