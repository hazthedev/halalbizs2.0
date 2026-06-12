<?php

namespace App\Livewire\Seller;

use App\Enums\PayoutStatus;
use App\Exceptions\CheckoutException;
use App\Livewire\Concerns\CurrentStore;
use App\Services\LedgerService;
use App\Settings\OrderSettings;
use App\Support\RinggitInput;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Seller earnings (docs/09 §A): the store's escrow ledger, the available /
 * pending / paid-out balance cards, and the payout request form. All money
 * is integer sen; the balance maths lives in LedgerService — this component
 * only reads and relays.
 */
#[Layout('layouts.seller')]
class Earnings extends Component
{
    use CurrentStore, WithPagination;

    public const PER_PAGE = 20;

    /** RM amount typed by the seller — parsed via RinggitInput, never floats. */
    public string $amount = '';

    public function requestPayout(LedgerService $ledger): void
    {
        $amountSen = RinggitInput::toSen($this->amount);

        if ($amountSen === null || $amountSen <= 0) {
            $this->addError('amount', __('Enter an amount in RM — e.g. 120.00.'));

            return;
        }

        $this->resetErrorBag('amount');

        try {
            // The service guards everything: minimum, available balance
            // (negative blocks), and one open request at a time.
            $payout = $ledger->requestPayout($this->currentStore(), $amountSen);
        } catch (CheckoutException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->reset('amount');
        $this->resetPage();

        $this->dispatch('toast', message: __('Payout :no requested — we\'ll review it and run the bank transfer.', ['no' => $payout->payout_no]));
    }

    public function render()
    {
        $store = $this->currentStore();
        $ledger = app(LedgerService::class);

        return view('livewire.seller.earnings', [
            'availableSen' => $ledger->availableBalanceSen($store),
            'pendingPayout' => $store->payouts()
                ->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])
                ->latest('requested_at')
                ->first(),
            'paidOutSen' => (int) $store->payouts()->where('status', PayoutStatus::Paid)->sum('amount_sen'),
            'entries' => $store->ledgerEntries()->latest('created_at')->latest('id')->paginate(self::PER_PAGE),
            'payouts' => $store->payouts()->latest('requested_at')->latest('id')->take(20)->get(),
            'minSen' => app(OrderSettings::class)->payout_min_sen,
            'bank' => $store->bank_details ?? [],
        ])->title(__('Earnings'));
    }
}
