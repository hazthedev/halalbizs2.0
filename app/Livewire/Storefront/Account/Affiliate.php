<?php

namespace App\Livewire\Storefront\Account;

use App\Exceptions\CheckoutException;
use App\Models\Affiliate as AffiliateModel;
use App\Services\AffiliateService;
use App\Support\RinggitInput;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Creator dashboard for the affiliate program (M2.5): enrol, copy the share
 * link, track clicks + confirmed commission, and withdraw available earnings.
 * Thin — all logic lives in AffiliateService.
 */
#[Layout('layouts.storefront')]
class Affiliate extends Component
{
    public const REFERRAL_LIMIT = 15;

    public bool $showWithdraw = false;

    public string $withdrawAmount = '';

    public string $bankDetails = '';

    public function mount(): void
    {
        abort_unless(config('affiliate.enabled', true), 404);
    }

    public function enroll(AffiliateService $affiliates): void
    {
        $affiliates->enroll(auth()->user());
        $this->dispatch('toast', message: __('You’re in! Share your link to start earning.'), type: 'success');
    }

    public function requestPayout(AffiliateService $affiliates): void
    {
        $affiliate = AffiliateModel::where('user_id', auth()->id())->first();

        if ($affiliate === null) {
            return;
        }

        $this->validate([
            'withdrawAmount' => 'required|string',
            'bankDetails' => 'required|string|min:6|max:255',
        ]);

        try {
            $affiliates->requestPayout(
                $affiliate,
                (int) RinggitInput::toSen($this->withdrawAmount),
                ['details' => trim($this->bankDetails)],
            );
            $this->reset('showWithdraw', 'withdrawAmount', 'bankDetails');
            $this->dispatch('toast', message: __('Withdrawal requested — we’ll process it shortly.'), type: 'success');
        } catch (CheckoutException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(AffiliateService $affiliates): View
    {
        // Query fresh (not the cached relation) so the view updates the same
        // request the buyer enrols in.
        $affiliate = AffiliateModel::where('user_id', auth()->id())->first();

        $referrals = $affiliate
            ? $affiliate->referrals()->with('subOrder')->latest('created_at')->limit(self::REFERRAL_LIMIT)->get()
            : collect();

        return view('livewire.storefront.account.affiliate', [
            'affiliate' => $affiliate,
            'link' => $affiliate ? $affiliates->referralLink($affiliate) : null,
            'earningsSen' => $affiliate ? $affiliates->confirmedEarningsSen($affiliate) : 0,
            'availableSen' => $affiliate ? $affiliates->availableForPayoutSen($affiliate) : 0,
            'payouts' => $affiliate ? $affiliate->payouts()->latest('id')->limit(10)->get() : collect(),
            'minPayoutSen' => (int) config('affiliate.min_payout_sen', 5000),
            'referrals' => $referrals,
        ])->title(__('Creator program'));
    }
}
