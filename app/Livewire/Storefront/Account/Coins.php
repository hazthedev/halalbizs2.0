<?php

namespace App\Livewire\Storefront\Account;

use App\Exceptions\CheckoutException;
use App\Models\CoinTransaction;
use App\Services\CoinService;
use App\Services\SpinService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Loyalty Coins hub (M2.1): wallet balance, daily check-in streak, spin-to-win
 * and recent ledger activity. All mutations route through CoinService /
 * SpinService under the wallet lock; this component stays thin.
 */
#[Layout('layouts.storefront')]
class Coins extends Component
{
    public const HISTORY_LIMIT = 12;

    /** Last spin/check-in outcome, surfaced as a one-shot banner. */
    public ?string $reward = null;

    public function mount(): void
    {
        abort_unless(config('coins.enabled', true), 404);
    }

    public function checkIn(CoinService $coins): void
    {
        try {
            $result = $coins->checkIn(auth()->user());
            $this->reward = __('Day :day check-in — +:coins coins!', ['day' => $result['day'], 'coins' => $result['coins']]);
            $this->dispatch('toast', message: $this->reward, type: 'success');
        } catch (CheckoutException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function spin(SpinService $spin): void
    {
        try {
            $outcome = $spin->spin(auth()->user());
            $this->reward = $outcome['label'];
            $this->dispatch('toast', message: $this->reward, type: 'success');
        } catch (CheckoutException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(CoinService $coins): View
    {
        $user = auth()->user();
        $wallet = $coins->walletFor($user);

        /** @var Collection<int, CoinTransaction> $history */
        $history = $wallet->transactions()
            ->latest('created_at')
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();

        // Soonest-expiring live lot, for the gentle "use them before" nudge.
        $expiringAt = $wallet->transactions()
            ->where('amount', '>', 0)
            ->where('remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->orderBy('expires_at')
            ->value('expires_at');

        return view('livewire.storefront.account.coins', [
            'wallet' => $wallet,
            'history' => $history,
            'canCheckIn' => $coins->canCheckInToday($user),
            'canSpin' => app(SpinService::class)->canSpinToday($user),
            'expiringAt' => $expiringAt,
        ])->title(__('My Coins'));
    }
}
