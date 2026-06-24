<?php

namespace App\Livewire\Storefront;

use App\Enums\PaymentMethod;
use App\Enums\TaxClass;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CoinService;
use App\Services\ShippingCalculator;
use App\Services\TaxService;
use App\Services\VoucherService;
use App\Settings\CodSettings;
use App\Support\Money;
use App\Support\VoucherDiscount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Checkout page (docs/06 §A + docs/09 §B): selected cart lines only,
 * seller-grouped, voucher picker (one platform + one shop voucher, Shopee
 * model), COD gated with the reason shown. The UI only previews totals —
 * CheckoutService re-validates everything inside one transaction on submit,
 * so a stale paint can never charge wrong money.
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

        try {
            $order = $checkout->place(
                auth()->user(),
                $address,
                PaymentMethod::tryFrom($this->paymentMethod) ?? PaymentMethod::Ipay88,
                $this->appliedPlatformCode,
                $this->appliedShopCode,
                array_map(fn ($note) => trim((string) $note), $this->sellerNotes),
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
        $shipping = app(ShippingCalculator::class);
        $tax = app(TaxService::class);
        $jurisdiction = $address === null ? null : $tax->jurisdictionFor($address->country);

        $groups = $this->selectedGroups()->map(function (Collection $lines) use ($address, $shipping, $tax, $jurisdiction) {
            $store = $lines->first()->variant->product->store;
            $itemsSubtotalSen = (int) $lines->sum(fn ($line) => $line->variant->effectivePriceSen() * $line->qty);
            $registered = (bool) $store->sst_registered;

            $taxSen = $jurisdiction === null ? 0 : (int) $lines->sum(function ($line) use ($tax, $registered, $jurisdiction) {
                $lineTotal = $line->variant->effectivePriceSen() * $line->qty;
                $class = $line->variant->product->tax_class ?? TaxClass::Exempt;

                return $tax->lineTaxSen($lineTotal, $class, $registered, $jurisdiction);
            });

            return (object) [
                'store' => $store,
                'lines' => $lines->values(),
                'itemsSubtotalSen' => $itemsSubtotalSen,
                'shippingFeeSen' => $address === null
                    ? null
                    : $shipping->feeForStore(
                        $store,
                        $address->state,
                        $itemsSubtotalSen,
                        $address->postcode,
                        (int) $lines->sum(fn ($line) => (int) ($line->variant->product->weight_grams ?? 0) * $line->qty),
                    ),
                'taxSen' => $taxSen,
            ];
        })->values();

        $storeSubtotals = $groups->mapWithKeys(fn ($group) => [$group->store->id => $group->itemsSubtotalSen])->all();
        $subtotalSen = (int) array_sum($storeSubtotals);

        // Re-validate both applied vouchers on every paint so the discount
        // can never go stale between apply and submit.
        $platformDiscount = $this->appliedDiscount($this->appliedPlatformCode, VoucherScope::Platform, $storeSubtotals);
        $shopDiscount = $this->appliedDiscount($this->appliedShopCode, VoucherScope::Shop, $storeSubtotals);
        $shippingDiscount = $this->appliedDiscount($this->appliedShippingCode, null, $storeSubtotals);

        // Free-shipping vouchers zero the flagged stores' fees in the preview.
        foreach ([$platformDiscount, $shopDiscount, $shippingDiscount] as $discount) {
            foreach ($discount?->freeShippingStoreIds ?? [] as $storeId) {
                $group = $groups->first(fn ($candidate) => $candidate->store->id === $storeId);

                if ($group !== null && $group->shippingFeeSen !== null) {
                    $group->shippingFeeSen = 0;
                }
            }
        }

        $shippingTotalSen = (int) $groups->sum(fn ($group) => $group->shippingFeeSen ?? 0);
        $taxTotalSen = (int) $groups->sum(fn ($group) => $group->taxSen ?? 0);

        $platformDiscountSen = min($platformDiscount?->totalDiscountSen ?? 0, $subtotalSen);
        $shopDiscountSen = $shopDiscount?->totalDiscountSen ?? 0;

        $preCoinTotalSen = max($subtotalSen + $shippingTotalSen + $taxTotalSen - $platformDiscountSen - $shopDiscountSen, 0);

        // Coins preview (M2.1) — display only. CheckoutService re-derives and
        // re-caps under the wallet lock on submit, so a stale paint is harmless.
        $coinsEnabled = (bool) config('coins.enabled', true);
        $coinBalance = $coinsEnabled ? app(CoinService::class)->balance(auth()->user()) : 0;
        $coinRedemptionSen = $this->coinRedemptionPreviewSen($preCoinTotalSen);

        $grandTotalSen = $preCoinTotalSen - $coinRedemptionSen;

        $codUnavailableReason = $this->codUnavailableReason($groups, $grandTotalSen);

        if ($codUnavailableReason !== null && $this->paymentMethod === PaymentMethod::Cod->value) {
            $this->paymentMethod = PaymentMethod::Ipay88->value;
        }

        [$platformVoucherOptions, $shopVoucherOptions, $bestPlatformVoucherId] = $this->voucherOptions($storeSubtotals, $groups);

        return view('livewire.storefront.checkout', [
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->orderByDesc('id')->get(),
            'address' => $address,
            'groups' => $groups,
            'subtotalSen' => $subtotalSen,
            'shippingTotalSen' => $shippingTotalSen,
            'taxTotalSen' => $taxTotalSen,
            'platformDiscount' => $platformDiscount,
            'shopDiscount' => $shopDiscount,
            'shippingDiscount' => $shippingDiscount,
            'platformDiscountSen' => $platformDiscountSen,
            'shopDiscountSen' => $shopDiscountSen,
            'platformVoucherOptions' => $platformVoucherOptions,
            'shopVoucherOptions' => $shopVoucherOptions,
            'bestPlatformVoucherId' => $bestPlatformVoucherId,
            'grandTotalSen' => $grandTotalSen,
            'codUnavailableReason' => $codUnavailableReason,
            'displayCurrency' => session('display_currency', 'MYR'),
            'coinsEnabled' => $coinsEnabled,
            'coinBalance' => $coinBalance,
            'coinRedemptionSen' => $coinRedemptionSen,
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

    /** Sen the applied coins would shave off this bill (mirrors the service cap). */
    private function coinRedemptionPreviewSen(int $preCoinTotalSen): int
    {
        if (! $this->useCoins || ! config('coins.enabled', true)) {
            return 0;
        }

        $rate = max(1, (int) config('coins.redemption_rate_sen', 1));
        $capSen = min((int) config('coins.max_redemption_sen', 5000), max(0, $preCoinTotalSen - 1));
        $coins = min(app(CoinService::class)->balance(auth()->user()), intdiv($capSen, $rate));

        return $coins * $rate;
    }

    /**
     * Selected cart lines grouped by store — the only lines checkout sees.
     *
     * @return Collection<int, Collection<int, object>>
     */
    private function selectedGroups(): Collection
    {
        return app(CartService::class)->groupedByStore(auth()->user())
            ->map(fn (Collection $lines) => $lines->filter(fn ($line) => $line->selected)->values())
            ->filter(fn (Collection $lines) => $lines->isNotEmpty());
    }

    /** @return array<int, int> [store_id => items_subtotal_sen] */
    private function storeSubtotals(): array
    {
        return $this->selectedGroups()
            ->map(fn (Collection $lines) => (int) $lines->sum(fn ($line) => $line->variant->effectivePriceSen() * $line->qty))
            ->all();
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

    /**
     * Re-validate an applied code for this paint; drop it with the reason
     * when it no longer applies. A null scope is the free-shipping slot.
     *
     * @param  array<int, int>  $storeSubtotals
     */
    private function appliedDiscount(?string $code, ?VoucherScope $scope, array $storeSubtotals): ?VoucherDiscount
    {
        if ($code === null) {
            return null;
        }

        try {
            return app(VoucherService::class)->validate($code, auth()->user(), $storeSubtotals, $scope);
        } catch (CheckoutException $e) {
            match ($scope) {
                VoucherScope::Platform => $this->appliedPlatformCode = null,
                VoucherScope::Shop => $this->appliedShopCode = null,
                default => $this->appliedShippingCode = null,
            };

            $this->voucherError = $e->getMessage();

            return null;
        }
    }

    /**
     * Picker data: live vouchers a buyer could use on this cart — platform
     * plus shop vouchers for the cart's stores — with min-spend met/unmet
     * and the best-savings hint (docs/09 §B).
     *
     * @param  array<int, int>  $storeSubtotals
     * @param  Collection<int, object>  $groups
     * @return array{0: Collection<int, object>, 1: Collection<int, object>, 2: ?int}
     */
    private function voucherOptions(array $storeSubtotals, Collection $groups): array
    {
        $storeNames = $groups->mapWithKeys(fn ($group) => [$group->store->id => $group->store->name]);

        $options = Voucher::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where(function ($query) use ($storeSubtotals) {
                $query->where(fn ($inner) => $inner->where('scope', VoucherScope::Platform)->whereNull('store_id'))
                    ->orWhere(fn ($inner) => $inner->where('scope', VoucherScope::Shop)->whereIn('store_id', array_keys($storeSubtotals)));
            })
            ->withCount(['usages as user_usage_count' => fn ($query) => $query->where('user_id', auth()->id())])
            ->orderBy('min_spend_sen')
            ->get()
            ->filter(fn (Voucher $voucher) => $voucher->hasQuotaRemaining() && $voucher->user_usage_count < $voucher->per_user_limit)
            ->map(function (Voucher $voucher) use ($storeSubtotals, $storeNames) {
                $basisSen = $voucher->scope === VoucherScope::Platform
                    ? (int) array_sum($storeSubtotals)
                    : (int) ($storeSubtotals[$voucher->store_id] ?? 0);

                $met = $basisSen >= $voucher->min_spend_sen;

                return (object) [
                    'voucher' => $voucher,
                    'storeName' => $voucher->store_id !== null ? $storeNames->get($voucher->store_id) : null,
                    'label' => $this->voucherLabel($voucher),
                    'minSpendMet' => $met,
                    'shortBySen' => $met ? 0 : $voucher->min_spend_sen - $basisSen,
                    'discountSen' => $met ? min($voucher->discountSenFor($basisSen), $basisSen) : 0,
                    'freeShipping' => $voucher->type === VoucherType::FreeShipping,
                ];
            })
            ->values();

        [$platformOptions, $shopOptions] = $options->partition(fn ($option) => $option->voucher->scope === VoucherScope::Platform);

        $best = $platformOptions
            ->filter(fn ($option) => $option->minSpendMet && $option->discountSen > 0)
            ->sortByDesc('discountSen')
            ->first();

        return [$platformOptions->values(), $shopOptions->values(), $best?->voucher->id];
    }

    /** Buyer-facing one-liner: "RM 5.00 off" · "10% off, up to RM 20.00". */
    private function voucherLabel(Voucher $voucher): string
    {
        return match ($voucher->type) {
            VoucherType::Fixed => __(':amount off', ['amount' => Money::format((int) $voucher->value_sen)]),
            VoucherType::Percent => $voucher->max_discount_sen !== null
                ? __(':percent% off, up to :cap', [
                    'percent' => rtrim(rtrim((string) $voucher->percent, '0'), '.'),
                    'cap' => Money::format($voucher->max_discount_sen),
                ])
                : __(':percent% off', ['percent' => rtrim(rtrim((string) $voucher->percent, '0'), '.')]),
            VoucherType::FreeShipping => __('Free shipping'),
        };
    }

    /**
     * Why COD is unavailable for this order, or null when it's allowed
     * (docs/01 §3.5: item flag, global switch, order cap).
     *
     * @param  Collection<int, object>  $groups
     */
    private function codUnavailableReason(Collection $groups, int $grandTotalSen): ?string
    {
        $settings = app(CodSettings::class);

        if (! $settings->enabled) {
            return __('COD unavailable: cash on delivery is switched off right now.');
        }

        $allCodEnabled = $groups->every(
            fn ($group) => $group->lines->every(fn ($line) => $line->variant->product->cod_enabled)
        );

        if (! $allCodEnabled) {
            return __("COD unavailable: some selected items don't support cash on delivery.");
        }

        if ($grandTotalSen > $settings->max_order_sen) {
            return __('COD unavailable: order exceeds :max', ['max' => Money::format($settings->max_order_sen)]);
        }

        return null;
    }
}
