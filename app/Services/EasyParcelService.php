<?php

namespace App\Services;

use App\Models\SubOrder;
use App\Services\Shipping\ShippingContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * EasyParcel MY aggregator (developers.easyparcel.com): live rate-check across
 * couriers, shipment booking, AWB label, and tracking. Inert until enabled +
 * an API key is supplied; callers treat a null rate as "fall back to flat".
 * Money stays integer sen — decimal prices are parsed without floats.
 */
class EasyParcelService
{
    public function configured(): bool
    {
        return (bool) config('shipping.easyparcel.enabled') && ! empty(config('shipping.easyparcel.api_key'));
    }

    /** Cheapest live rate in sen, or null to signal a fallback. */
    public function cheapestRateSen(ShippingContext $context): ?int
    {
        if (! $this->configured() || empty($context->destinationPostcode)) {
            return null;
        }

        try {
            $weightKg = $this->weightKg($context->weightGrams);

            $response = Http::baseUrl($this->baseUrl())->asForm()->post('/api/v1/rate-checking', [
                'api' => config('shipping.easyparcel.api_key'),
                'pick_code' => $this->originPostcode($context),
                'send_code' => $context->destinationPostcode,
                'weight' => $weightKg,
            ]);

            if ($response->failed()) {
                return null;
            }

            $rates = collect($response->json('result.0.rates') ?? $response->json('rates') ?? []);

            $cheapest = $rates
                ->map(fn ($rate) => $this->priceToSen((string) ($rate['price'] ?? $rate['shipment_price'] ?? '')))
                ->filter(fn ($sen) => $sen > 0)
                ->min();

            return $cheapest !== null ? (int) $cheapest : null;
        } catch (Throwable $e) {
            Log::warning('EasyParcel rate-check failed; falling back to flat fee.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Book a shipment for a sub-order if its store uses EasyParcel. No-op otherwise. */
    public function bookIfEnabled(SubOrder $subOrder): void
    {
        if ($subOrder->store->shipping_mode !== 'easyparcel' || ! $this->configured() || $subOrder->awb_no !== null) {
            return;
        }

        try {
            $this->book($subOrder);
        } catch (Throwable $e) {
            Log::warning('EasyParcel booking failed.', ['sub_order' => $subOrder->sub_order_no, 'error' => $e->getMessage()]);
        }
    }

    /** Create the shipment and persist the AWB + label onto the sub-order. */
    public function book(SubOrder $subOrder): void
    {
        $subOrder->loadMissing(['store', 'order']);
        $address = $subOrder->order->shipping_address;

        $response = Http::baseUrl($this->baseUrl())->asForm()->post('/api/v1/order-submission', [
            'api' => config('shipping.easyparcel.api_key'),
            'send_code' => $address['postcode'] ?? null,
            'pick_code' => $subOrder->store->shipping_origin_postcode ?? config('shipping.easyparcel.origin_postcode'),
            'weight' => $this->weightKg((int) ($subOrder->items->sum(fn ($i) => ($i->product?->weight_grams ?? 0) * $i->qty))),
            'reference' => $subOrder->sub_order_no,
        ]);

        $result = $response->json('result.0') ?? $response->json('result') ?? [];

        $subOrder->forceFill([
            'awb_no' => $result['awb'] ?? $result['parcel_no'] ?? null,
            'shipping_label_url' => $result['awb_id_link'] ?? $result['label_url'] ?? null,
            'courier_service' => $result['courier'] ?? $result['service_id'] ?? null,
        ])->save();
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('shipping.easyparcel.base_url'), '/');
    }

    private function originPostcode(ShippingContext $context): ?string
    {
        return $context->store->shipping_origin_postcode ?: config('shipping.easyparcel.origin_postcode');
    }

    private function weightKg(int $grams): string
    {
        $grams = $grams > 0 ? $grams : (int) config('shipping.default_weight_grams');
        $grams = max($grams, 100); // EasyParcel min 0.1kg

        // Integer math → "K.kkk" string, no float.
        return sprintf('%d.%03d', intdiv($grams, 1000), $grams % 1000);
    }

    /** "5.30" → 530 sen, integer string parsing only (Hard Rule 1). */
    public function priceToSen(string $price): int
    {
        $clean = preg_replace('/[^0-9.]/', '', trim($price));

        if ($clean === '' || $clean === '.') {
            return 0;
        }

        [$units, $minor] = array_pad(explode('.', $clean, 2), 2, '0');

        return ((int) $units) * 100 + (int) str_pad(substr($minor, 0, 2), 2, '0');
    }
}
