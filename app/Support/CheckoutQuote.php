<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * What CheckoutService::quote() previews for a buyer's selected cart lines:
 * the same numbers place() will write, computed by the same code. Every
 * amount is integer sen. The web checkout page reads the properties; the
 * JSON API reads toArray().
 */
final class CheckoutQuote
{
    /**
     * @param  Collection<int, object>  $groups  per store: store, lines, itemsSubtotalSen, shippingFeeSen (null = no address), shippingSubsidySen, taxSen
     * @param  array<int, int>  $storeSubtotals  [store_id => items_subtotal_sen]
     * @param  array<string, string>  $voucherErrors  [slot => reason] for applied codes that no longer validate
     * @param  Collection<int, object>  $platformVoucherOptions
     * @param  Collection<int, object>  $shopVoucherOptions
     */
    public function __construct(
        public readonly bool $purchasingEnabled,
        public readonly Collection $groups,
        public readonly array $storeSubtotals,
        public readonly int $subtotalSen,
        public readonly int $shippingTotalSen,
        public readonly int $shippingSubsidySen,
        public readonly int $taxTotalSen,
        public readonly ?VoucherDiscount $platformDiscount,
        public readonly ?VoucherDiscount $shopDiscount,
        public readonly ?VoucherDiscount $shippingDiscount,
        public readonly int $platformDiscountSen,
        public readonly int $shopDiscountSen,
        public readonly array $voucherErrors,
        public readonly Collection $platformVoucherOptions,
        public readonly Collection $shopVoucherOptions,
        public readonly ?int $bestPlatformVoucherId,
        public readonly bool $coinsEnabled,
        public readonly int $coinBalance,
        public readonly int $coinsRedeemed,
        public readonly int $coinRedemptionSen,
        public readonly int $grandTotalSen,
        public readonly ?string $codUnavailableReason,
        public readonly bool $ipay88Enabled,
        public readonly ?PaymentMethod $paymentMethod,
    ) {}

    /** Whether at least one payment method can take this order. */
    public function paymentMethodAvailable(): bool
    {
        return $this->ipay88Enabled || $this->codUnavailableReason === null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $locale = app()->getLocale();

        $voucher = fn (?VoucherDiscount $discount, int $sen): ?array => $discount === null ? null : [
            'id' => $discount->voucher->id,
            'code' => $discount->voucher->code,
            'type' => $discount->voucher->type->value,
            'discount_sen' => $sen,
            'free_shipping_store_ids' => $discount->freeShippingStoreIds,
        ];

        $option = fn (object $option): array => [
            'id' => $option->voucher->id,
            'code' => $option->voucher->code,
            'label' => $option->label,
            'store_name' => $option->storeName,
            'min_spend_sen' => (int) $option->voucher->min_spend_sen,
            'min_spend_met' => $option->minSpendMet,
            'short_by_sen' => $option->shortBySen,
            'discount_sen' => $option->discountSen,
            'free_shipping' => $option->freeShipping,
        ];

        return [
            'purchasing_enabled' => $this->purchasingEnabled,
            'groups' => $this->groups->map(fn (object $group) => [
                'store' => [
                    'id' => $group->store->id,
                    'slug' => $group->store->slug,
                    'name' => $group->store->name,
                ],
                'items_subtotal_sen' => $group->itemsSubtotalSen,
                'shipping_fee_sen' => $group->shippingFeeSen,
                'shipping_subsidy_sen' => $group->shippingSubsidySen,
                'tax_sen' => $group->taxSen,
                'lines' => $group->lines->map(fn (object $line) => [
                    'variant_id' => $line->variant->id,
                    'product_id' => $line->variant->product_id,
                    'name' => $line->variant->product->getTranslation('name', $locale)
                        ?: $line->variant->product->getTranslation('name', 'en'),
                    'variant_label' => $line->variant->options_label,
                    'qty' => $line->qty,
                    'unit_price_sen' => $line->unitPriceSen,
                    'list_price_sen' => $line->listPriceSen,
                    'line_total_sen' => $line->unitPriceSen * $line->qty,
                    'tax_sen' => $line->taxSen,
                    'cod_enabled' => (bool) $line->variant->product->cod_enabled,
                ])->values()->all(),
            ])->values()->all(),
            'subtotal_sen' => $this->subtotalSen,
            'shipping_total_sen' => $this->shippingTotalSen,
            'shipping_subsidy_sen' => $this->shippingSubsidySen,
            'tax_total_sen' => $this->taxTotalSen,
            'platform_discount_sen' => $this->platformDiscountSen,
            'shop_discount_sen' => $this->shopDiscountSen,
            'coin_redemption_sen' => $this->coinRedemptionSen,
            'grand_total_sen' => $this->grandTotalSen,
            'vouchers' => [
                'platform' => $voucher($this->platformDiscount, $this->platformDiscountSen),
                'shop' => $voucher($this->shopDiscount, $this->shopDiscountSen),
                'shipping' => $voucher($this->shippingDiscount, $this->shippingSubsidySen),
            ],
            'voucher_errors' => $this->voucherErrors,
            'voucher_options' => [
                'platform' => $this->platformVoucherOptions->map($option)->values()->all(),
                'shop' => $this->shopVoucherOptions->map($option)->values()->all(),
                'best_platform_voucher_id' => $this->bestPlatformVoucherId,
            ],
            'coins' => [
                'enabled' => $this->coinsEnabled,
                'balance' => $this->coinBalance,
                'redeemed' => $this->coinsRedeemed,
                'redemption_sen' => $this->coinRedemptionSen,
            ],
            'payment_methods' => [
                ['method' => PaymentMethod::Ipay88->value, 'available' => $this->ipay88Enabled, 'reason' => null],
                ['method' => PaymentMethod::Cod->value, 'available' => $this->codUnavailableReason === null, 'reason' => $this->codUnavailableReason],
            ],
            'payment_method' => $this->paymentMethod?->value,
            'payment_method_available' => $this->paymentMethodAvailable(),
        ];
    }
}
