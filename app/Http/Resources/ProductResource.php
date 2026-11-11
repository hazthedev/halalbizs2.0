<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\LiveSessionService;
use App\Settings\CodSettings;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /** detail: true adds the gallery (`images[]`) and the option groups for the picker. */
    public function __construct(Product $resource, private bool $detail = false)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'description' => $this->getTranslation('description', app()->getLocale()),
            'min_price_sen' => $this->variants->isNotEmpty() ? $this->minPriceSen() : 0,
            'sold_count' => $this->sold_count,
            'rating_avg' => (float) $this->rating_avg,
            'image' => $this->getFirstMediaUrl('images', 'card') ?: null,
            'store' => $this->whenLoaded('store', fn () => [
                'name' => $this->store?->name,
                'slug' => $this->store?->slug,
            ] + ($this->detail ? $this->sellerCard() : [])),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'options_label' => $variant->options_label,
                'price_sen' => $variant->effectivePriceSen(),
                'regular_price_sen' => $variant->isOnSale() ? $variant->price_sen : null,
                'option_value_ids' => array_map('intval', $variant->option_value_ids ?? []),
                'stock' => $variant->stock,
            ])->values()),
            'halal' => $this->halal(),
        ] + $this->liveFields();

        if (! $this->detail) {
            return $data;
        }

        // Same groups and order as the web picker (ProductDetail: options.values by position).
        return $data + [
            // Traceability rows the web PDP shows beside the certificate.
            'halal_batch_code' => $this->halal_batch_code,
            // The number as printed on the listing — the web table's fallback
            // "Certificate" row when no certificate record is bound.
            'halal_cert_number' => $this->halal_cert_number,
            'halal_packed_on' => $this->halal_packed_on?->toDateString(),
            'metafields' => $this->metafieldGroups(),
            // Web codAvailable: purchasing on + the product's flag + the platform's COD switch.
            'cod_available' => app(GeneralSettings::class)->purchasing_enabled
                && $this->cod_enabled
                && app(CodSettings::class)->enabled,
            // Web shipping row: "Ships from :state" (Malaysia when the store has none).
            'ships_from' => $this->store?->state ?: 'Malaysia',
            // Web Specifications tab rows: brand, condition, weight, category.
            'specs' => [
                'brand' => $this->brand?->name,
                'condition' => $this->condition->label(),
                'weight_grams' => (int) $this->weight_grams,
                'category' => $this->category?->getTranslation('name', app()->getLocale()),
            ],
            'images' => $this->getMedia('images')->map(fn ($media) => [
                'large' => $media->getUrl(),
                'card' => $media->getUrl('card'),
            ])->values()->all(),
            'options' => $this->options->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'values' => $option->values->map(fn ($value) => ['id' => $value->id, 'value' => $value->value])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * The web seller card: verified badge, product and service ratings,
     * product count, state, and the approval date as "joined".
     *
     * @return array<string, mixed>
     */
    /** @return array{store_live: bool, live_slug?: string} */
    private function liveFields(): array
    {
        $slug = app(LiveSessionService::class)->liveStoreIds()[$this->store_id] ?? null;

        return $slug ? ['store_live' => true, 'live_slug' => $slug] : ['store_live' => false];
    }

    private function sellerCard(): array
    {
        $store = $this->store;

        return [
            'logo' => $store->getFirstMediaUrl('logo', 'thumb') ?: null,
            'verified' => $store->isApproved(),
            'rating_avg' => (float) $store->rating_avg,
            'rating_count' => (int) $store->rating_count,
            'service_rating_avg' => (float) $store->service_rating_avg,
            'service_rating_count' => (int) $store->service_rating_count,
            'product_count' => $store->products()->live()->count(),
            'state' => $store->state,
            'joined_at' => ($store->approved_at ?? $store->created_at)?->toIso8601String(),
        ];
    }

    /**
     * The certificate block the web PDP panel, traceability table and card
     * badge read. `verdict` is Product::halalVerdict() — the ONE owner of the
     * badge; the app renders it, never re-derives it. Null when the SKU has
     * no certificate record (the web renders nothing then either).
     *
     * @return array<string, mixed>|null
     */
    private function halal(): ?array
    {
        $cert = $this->halalCertificate;

        if ($cert === null) {
            return null;
        }

        $block = [
            'verdict' => $this->halalVerdict(),
            'number' => $cert->number,
            'issuing_body' => $cert->issuing_body,
            'valid_from' => $cert->valid_from->toDateString(),
            'valid_to' => $cert->valid_to->toDateString(),
            // Malaysia-day count, so the phone's clock never decides it.
            'days_remaining' => $cert->daysRemaining(),
        ];

        if (! $this->detail) {
            return $block;
        }

        return $block + [
            'issuing_body_name' => $cert->issuing_body_name,
            'scheme' => $cert->scheme,
            'scope_note' => $cert->scope_note,
            'facility' => $cert->facility,
            'dedicated_facility' => $cert->dedicated_facility,
            'export_paperwork' => $cert->export_paperwork,
            // The web "Read the record" button: the public register lookup.
            'register_url' => route('certificate.register', ['no' => $cert->number]),
        ];
    }

    /**
     * x-product-metafields: groups in config order (the 'halal' group is the
     * web's "Halal certification" box), empty groups dropped.
     *
     * @return list<array{key: string, label: string, rows: list<array{label: string, value: string}>}>
     */
    private function metafieldGroups(): array
    {
        if (! config('metafields.enabled', true)) {
            return [];
        }

        $values = $this->metafields->keyBy('key');
        $definitions = collect(config('metafields.definitions', []));

        return collect(config('metafields.groups', []))
            ->map(fn (string $label, string $group) => [
                'key' => $group,
                'label' => __($label),
                'rows' => $definitions->filter(fn ($d) => ($d['group'] ?? 'details') === $group)->keys()
                    ->map(fn ($key) => $values->get($key))->filter()
                    ->map(fn ($row) => ['label' => $row->label(), 'value' => (string) $row->value])
                    ->values()->all(),
            ])
            ->filter(fn (array $group) => $group['rows'] !== [])
            ->values()->all();
    }
}
