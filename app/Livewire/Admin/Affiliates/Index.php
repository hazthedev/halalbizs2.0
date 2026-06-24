<?php

namespace App\Livewire\Admin\Affiliates;

use App\Enums\AffiliatePayoutStatus;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Services\AffiliateService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin affiliate oversight (M2.5): the creator roster and the withdrawal
 * approval queue (mark paid with a bank reference, or reject — which releases
 * the earmark back to available). Money logic lives in AffiliateService.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    public ?int $payingId = null;

    public string $paidReference = '';

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public function openPay(int $payoutId): void
    {
        $this->payingId = $payoutId;
        $this->paidReference = '';
        $this->resetValidation();
    }

    public function markPaid(AffiliateService $affiliates): void
    {
        $this->validate(['paidReference' => ['required', 'string', 'min:3', 'max:100']]);

        $payout = AffiliatePayout::find($this->payingId);

        if ($payout && $payout->status === AffiliatePayoutStatus::Requested) {
            $affiliates->markPayoutPaid($payout, trim($this->paidReference));
            $this->dispatch('toast', message: __('Withdrawal marked paid.'));
        }

        $this->reset('payingId', 'paidReference');
    }

    public function openReject(int $payoutId): void
    {
        $this->rejectingId = $payoutId;
        $this->rejectReason = '';
        $this->resetValidation();
    }

    public function reject(AffiliateService $affiliates): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'min:3', 'max:255']]);

        $payout = AffiliatePayout::find($this->rejectingId);

        if ($payout && $payout->status === AffiliatePayoutStatus::Requested) {
            $affiliates->rejectPayout($payout, trim($this->rejectReason));
            $this->dispatch('toast', message: __('Withdrawal rejected — funds return to available.'));
        }

        $this->reset('rejectingId', 'rejectReason');
    }

    public function render(): View
    {
        return view('livewire.admin.affiliates.index', [
            'pending' => AffiliatePayout::query()
                ->with('affiliate.user')
                ->where('status', AffiliatePayoutStatus::Requested)
                ->oldest('requested_at')
                ->get(),
            'affiliates' => Affiliate::query()
                ->with('user')
                ->withCount('referrals')
                ->withSum('referrals as commission_sen_sum', 'commission_sen')
                ->orderByDesc('commission_sen_sum')
                ->paginate(self::PER_PAGE),
        ])->title(__('Affiliates'));
    }
}
