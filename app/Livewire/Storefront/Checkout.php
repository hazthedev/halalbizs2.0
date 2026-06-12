<?php

namespace App\Livewire\Storefront;

use App\Enums\PaymentMethod;
use App\Enums\VoucherScope;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\ShippingCalculator;
use App\Settings\CodSettings;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Checkout page (docs/06 §A): selected cart lines only, seller-grouped,
 * platform-voucher lite, COD gated with the reason shown. The UI only
 * previews totals — CheckoutService re-validates everything inside one
 * transaction on submit, so a stale paint can never charge wrong money.
 */
#[Layout('layouts.storefront')]
class Checkout extends Component
{
    public ?int $addressId = null;

    public bool $changingAddress = false;

    public string $voucherCode = '';

    public ?string $appliedVoucherCode = null;

    public ?string $voucherError = null;

    public ?string $addressError = null;

    public string $paymentMethod = 'ipay88';

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

    public function applyVoucher(): void
    {
        $code = trim($this->voucherCode);

        if ($code === '') {
            $this->voucherError = __('Enter a voucher code first.');

            return;
        }

        $voucher = $this->platformVoucher($code);

        if ($voucher === null) {
            $this->voucherError = __("We can't find that voucher — check the code and try again.");

            return;
        }

        $reason = $this->voucherFailureReason($voucher, $this->itemsSubtotalSen());

        if ($reason !== null) {
            $this->voucherError = $reason;

            return;
        }

        $this->appliedVoucherCode = $code;
        $this->voucherCode = '';
        $this->voucherError = null;
    }

    public function removeVoucher(): void
    {
        $this->appliedVoucherCode = null;
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
                $this->appliedVoucherCode,
                array_map(fn ($note) => trim((string) $note), $this->sellerNotes),
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

        $groups = $this->selectedGroups()->map(function (Collection $lines) use ($address, $shipping) {
            $store = $lines->first()->variant->product->store;
            $itemsSubtotalSen = (int) $lines->sum(fn ($line) => $line->variant->effectivePriceSen() * $line->qty);

            return (object) [
                'store' => $store,
                'lines' => $lines->values(),
                'itemsSubtotalSen' => $itemsSubtotalSen,
                'shippingFeeSen' => $address === null
                    ? null
                    : $shipping->feeForStore($store, $address->state, $itemsSubtotalSen),
            ];
        })->values();

        $subtotalSen = (int) $groups->sum(fn ($group) => $group->itemsSubtotalSen);
        $shippingTotalSen = (int) $groups->sum(fn ($group) => $group->shippingFeeSen ?? 0);

        [$voucher, $discountSen] = $this->appliedVoucherState($subtotalSen);

        $grandTotalSen = max($subtotalSen + $shippingTotalSen - $discountSen, 0);

        $codUnavailableReason = $this->codUnavailableReason($groups, $grandTotalSen);

        if ($codUnavailableReason !== null && $this->paymentMethod === PaymentMethod::Cod->value) {
            $this->paymentMethod = PaymentMethod::Ipay88->value;
        }

        return view('livewire.storefront.checkout', [
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->orderByDesc('id')->get(),
            'address' => $address,
            'groups' => $groups,
            'subtotalSen' => $subtotalSen,
            'shippingTotalSen' => $shippingTotalSen,
            'voucher' => $voucher,
            'discountSen' => $discountSen,
            'grandTotalSen' => $grandTotalSen,
            'codUnavailableReason' => $codUnavailableReason,
            'displayCurrency' => session('display_currency', 'MYR'),
        ])->title(__('Checkout'));
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

    private function itemsSubtotalSen(): int
    {
        return (int) $this->selectedGroups()
            ->flatten(1)
            ->sum(fn ($line) => $line->variant->effectivePriceSen() * $line->qty);
    }

    private function selectedAddress(): ?Address
    {
        if ($this->addressId === null) {
            return null;
        }

        return auth()->user()->addresses()->find($this->addressId);
    }

    /** voucher_lite (docs/06 §A): platform scope only until M8. */
    private function platformVoucher(string $code): ?Voucher
    {
        return Voucher::where('scope', VoucherScope::Platform)
            ->whereNull('store_id')
            ->where('code', $code)
            ->first();
    }

    /**
     * Re-validate the applied voucher on every paint so the discount can
     * never go stale between apply and submit.
     *
     * @return array{0: ?Voucher, 1: int} [voucher, discount in sen]
     */
    private function appliedVoucherState(int $subtotalSen): array
    {
        if ($this->appliedVoucherCode === null) {
            return [null, 0];
        }

        $voucher = $this->platformVoucher($this->appliedVoucherCode);
        $reason = $voucher === null
            ? __("We can't find that voucher — check the code and try again.")
            : $this->voucherFailureReason($voucher, $subtotalSen);

        if ($reason !== null) {
            $this->appliedVoucherCode = null;
            $this->voucherError = $reason;

            return [null, 0];
        }

        return [$voucher, min($voucher->discountSenFor($subtotalSen), $subtotalSen)];
    }

    /** Human reason a voucher can't apply, or null when it can (design §8). */
    private function voucherFailureReason(Voucher $voucher, int $subtotalSen): ?string
    {
        if (! $voucher->is_active) {
            return __('This voucher is no longer active.');
        }

        if ($voucher->starts_at !== null && now()->lt($voucher->starts_at)) {
            return __("This voucher isn't live yet — it starts :date.", ['date' => $voucher->starts_at->format('j M')]);
        }

        if ($voucher->ends_at !== null && now()->gt($voucher->ends_at)) {
            return __('This voucher expired on :date.', ['date' => $voucher->ends_at->format('j M Y')]);
        }

        if (! $voucher->hasQuotaRemaining()) {
            return __('This voucher has been fully redeemed.');
        }

        if ($voucher->min_spend_sen !== null && $subtotalSen < $voucher->min_spend_sen) {
            return __("This voucher needs a :min minimum — you're :short away.", [
                'min' => Money::format($voucher->min_spend_sen),
                'short' => Money::format($voucher->min_spend_sen - $subtotalSen),
            ]);
        }

        if (! $voucher->isRedeemableBy(auth()->user(), $subtotalSen)) {
            return __("You've already used this voucher the maximum number of times.");
        }

        return null;
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
