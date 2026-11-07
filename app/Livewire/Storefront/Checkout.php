<?php

namespace App\Livewire\Storefront;

use App\Enums\PaymentMethod;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CoinService;
use App\Services\GroupBuyService;
use App\Services\Ipay88Service;
use App\Services\VoucherService;
use App\Settings\GeneralSettings;
use App\Support\VoucherDiscount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Checkout page (docs/06 §A + docs/09 §B): selected cart lines only,
 * seller-grouped, voucher picker (one platform + one shop voucher, Shopee
 * model), COD gated with the reason shown. The UI only previews totals via
 * CheckoutService::quote() — the same code place() charges with inside one
 * transaction on submit, so a stale paint can never charge wrong money.
 */
#[Layout('layouts.storefront')]
class Checkout extends Component
{
    public ?int $addressId = null;

    public bool $changingAddress = false;

    /** Manual code entry — the fallback beside the picker. */
    public string $voucherCode = '';

    public ?string $appliedPlatformCode = null;

    public ?string $appliedShopCode = null;

    /** M1.1 dedicated free-shipping slot — stacks with platform + shop. */
    public ?string $appliedShippingCode = null;

    public ?string $voucherError = null;

    public bool $voucherPanelOpen = false;

    public ?string $addressError = null;

    public string $paymentMethod = 'ipay88';

    /** Apply the buyer's Loyalty Coins to this order (M2.1). */
    public bool $useCoins = false;

    /** @var array<int|string, string> note to the seller, keyed by store_id */
    public array $sellerNotes = [];

    public function mount(): void
    {
        if (! app(GeneralSettings::class)->purchasing_enabled) {
            session()->flash('toast', [
                'message' => __('This marketplace is currently in listing-only mode. Purchasing is unavailable.'),
                'type' => 'error',
            ]);

            $this->redirectRoute('home', navigate: true);

            return;
        }

        // Guard direct GETs with nothing selected.
        if ($this->selectedGroups()->isEmpty()) {
            session()->flash('toast', [
                'message' => __('Select at least one cart item to check out.'),
                'type' => 'error',
            ]);

            $this->redirectRoute('cart', navigate: true);

            return;
        }

        $this->addressId = auth()->user()->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->value('id');

        if (! app(Ipay88Service::class)->isEnabled()) {
            $this->paymentMethod = PaymentMethod::Cod->value;
        }
    }

    public function selectAddress(int $addressId): void
    {
        $address = auth()->user()->addresses()->find($addressId);

        if ($address !== null) {
            $this->addressId = $address->id;
            $this->addressError = null;
            $this->changingAddress = false;
        }
    }

    /** Manual code entry: scope is auto-detected (platform preferred). */
    public function applyVoucher(): void
    {
        $code = strtoupper(trim($this->voucherCode));

        if ($code === '') {
            $this->voucherError = __('Enter a voucher code first.');

            return;
        }

        $discount = $this->tryValidate($code, null);

        if ($discount === null) {
            return;
        }

        $this->acceptDiscount($discount);
        $this->voucherCode = '';
    }

    /** Picker path: apply a listed voucher by id. */
    public function selectVoucher(int $voucherId): void
    {
        $voucher = Voucher::find($voucherId);

        if ($voucher === null) {
            $this->voucherError = __("We can't find that voucher — check the code and try again.");

            return;
        }

        $discount = $this->tryValidate($voucher->code, $voucher->scope);

        if ($discount !== null) {
            $this->acceptDiscount($discount);
        }
    }

    public function removeVoucher(string $slot): void
    {
        match ($slot) {
            'platform' => $this->appliedPlatformCode = null,
            'shipping' => $this->appliedShippingCode = null,
            default => $this->appliedShopCode = null,
        };

        $this->voucherError = null;
    }

    public function placeOrder(CheckoutService $checkout): void
    {
        $address = $this->selectedAddress();

        if ($address === null) {
            $this->addressError = __('Add a delivery address to place your order.');

            return;
        }

        // AL-L2: server-side guard on the buyer-note array — the client-side
        // maxlength="500" on the textarea is UX only. Length is enforced here;
        // keys are additionally restricted to stores actually in THIS
        // checkout (a stray/forged key for an unrelated store is dropped
        // rather than trusted, since it would otherwise skip validation
        // entirely with no matching sub-order to land on anyway).
        $this->validate([
            'sellerNotes' => ['array'],
            'sellerNotes.*' => ['nullable', 'string', 'max:500'],
        ]);

        $selectedGroups = $this->selectedGroups();
        $validStoreIds = $selectedGroups->keys()->map(fn ($id) => (string) $id)->all();

        $sellerNotes = collect($this->sellerNotes)
            ->only($validStoreIds)
            ->map(fn ($note) => trim((string) $note))
            ->all();

        $method = PaymentMethod::tryFrom($this->paymentMethod);

        if ($method === null || ($method === PaymentMethod::Ipay88 && ! app(Ipay88Service::class)->isEnabled())) {
            $this->dispatch('toast', message: __('The selected payment method is currently unavailable.'), type: 'error');

            return;
        }

        $groupBuyRedemption = app(GroupBuyService::class)->hasRedeemableFor(
            auth()->user(),
            $selectedGroups->flatten(1)->map(fn ($line) => (int) $line->variant->id)->all(),
        );

        $throttleKey = 'checkout:'.auth()->id();

        if (! $groupBuyRedemption) {
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $this->dispatch('toast', message: __('Too many checkout attempts. Try again in :minutes minute(s).', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]), type: 'error');

                return;
            }

            RateLimiter::hit($throttleKey, 3600);
        }

        try {
            $order = $checkout->place(
                auth()->user(),
                $address,
                $method,
                $this->appliedPlatformCode,
                $this->appliedShopCode,
                $sellerNotes,
                $this->appliedShippingCode,
                $this->requestedCoins(),
            );
        } catch (CheckoutException $e) {
            // Surface the human reason; the re-render that follows refreshes
            // every total against live stock, voucher, and settings state.
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->dispatch('cart-updated', count: app(CartService::class)->itemCount(auth()->user()));

        if ($order->payment_method === PaymentMethod::Ipay88) {
            // Hand off to the iPay88 bridge (full page load — gateway form POST).
            $this->redirect(route('payments.ipay88.pay', $order));

            return;
        }

        $this->redirectRoute('checkout.success', ['order' => $order->order_no], navigate: true);
    }

    public function render(): View
    {
        $address = $this->selectedAddress();

        $quote = app(CheckoutService::class)->quote(
            auth()->user(),
            $address,
            $this->appliedShippingCode,
            $this->appliedPlatformCode,
            $this->appliedShopCode,
            $this->requestedCoins(),
            PaymentMethod::tryFrom($this->paymentMethod),
        );

        // An applied code that no longer validates is dropped with the reason.
        foreach ($quote->voucherErrors as $slot => $message) {
            match ($slot) {
                'platform' => $this->appliedPlatformCode = null,
                'shop' => $this->appliedShopCode = null,
                default => $this->appliedShippingCode = null,
            };

            $this->voucherError = $message;
        }

        if ($quote->paymentMethod !== PaymentMethod::tryFrom($this->paymentMethod)) {
            $this->paymentMethod = $quote->paymentMethod?->value ?? '';
        }

        return view('livewire.storefront.checkout', [
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->orderByDesc('id')->get(),
            'address' => $address,
            'groups' => $quote->groups,
            'subtotalSen' => $quote->subtotalSen,
            'shippingTotalSen' => $quote->shippingTotalSen,
            'taxTotalSen' => $quote->taxTotalSen,
            'platformDiscount' => $quote->platformDiscount,
            'shopDiscount' => $quote->shopDiscount,
            'shippingDiscount' => $quote->shippingDiscount,
            'platformDiscountSen' => $quote->platformDiscountSen,
            'shopDiscountSen' => $quote->shopDiscountSen,
            'platformVoucherOptions' => $quote->platformVoucherOptions,
            'shopVoucherOptions' => $quote->shopVoucherOptions,
            'bestPlatformVoucherId' => $quote->bestPlatformVoucherId,
            'grandTotalSen' => $quote->grandTotalSen,
            'codUnavailableReason' => $quote->codUnavailableReason,
            'ipay88Enabled' => $quote->ipay88Enabled,
            'paymentMethodAvailable' => $quote->paymentMethodAvailable(),
            'displayCurrency' => session('display_currency', 'MYR'),
            'coinsEnabled' => $quote->coinsEnabled,
            'coinBalance' => $quote->coinBalance,
            'coinRedemptionSen' => $quote->coinRedemptionSen,
        ])->title(__('Checkout'));
    }

    /**
     * Coins the buyer is requesting to spend (capped by balance + the per-order
     * config ceiling). CheckoutService applies the final bill-aware cap under
     * the wallet lock, so this need not subtract the order total here.
     */
    private function requestedCoins(): int
    {
        if (! $this->useCoins || ! config('coins.enabled', true)) {
            return 0;
        }

        $rate = max(1, (int) config('coins.redemption_rate_sen', 1));
        $balance = app(CoinService::class)->balance(auth()->user());

        return min($balance, intdiv((int) config('coins.max_redemption_sen', 5000), $rate));
    }

    /**
     * Selected cart lines grouped by store — the only lines checkout sees.
     *
     * @return Collection<int, Collection<int, object>>
     */
    private function selectedGroups(): Collection
    {
        return app(CheckoutService::class)->selectedGroups(auth()->user());
    }

    /**
     * Selected lines that CANNOT be bought, so the page can name them instead of
     * silently dropping them.
     *
     * @return Collection<int, object>
     */
    public function blockedLines(): Collection
    {
        return app(CheckoutService::class)->blockedLines(auth()->user());
    }

    /** @return array<int, int> [store_id => items_subtotal_sen], priced like the quote */
    private function storeSubtotals(): array
    {
        return app(CheckoutService::class)->quote(auth()->user(), null)->storeSubtotals;
    }

    private function selectedAddress(): ?Address
    {
        if ($this->addressId === null) {
            return null;
        }

        return auth()->user()->addresses()->find($this->addressId);
    }

    /** Validate a code now, surfacing the human reason on failure. */
    private function tryValidate(string $code, ?VoucherScope $scope): ?VoucherDiscount
    {
        try {
            return app(VoucherService::class)->validate($code, auth()->user(), $this->storeSubtotals(), $scope);
        } catch (CheckoutException $e) {
            $this->voucherError = $e->getMessage();

            return null;
        }
    }

    /**
     * Three slots (Shopee model): free-shipping has its own slot so it stacks
     * with a platform discount + a shop voucher. Applying again replaces a slot.
     */
    private function acceptDiscount(VoucherDiscount $discount): void
    {
        match (true) {
            $discount->voucher->type === VoucherType::FreeShipping => $this->appliedShippingCode = $discount->voucher->code,
            $discount->scope === VoucherScope::Platform => $this->appliedPlatformCode = $discount->voucher->code,
            default => $this->appliedShopCode = $discount->voucher->code,
        };

        $this->voucherError = null;
    }
}
