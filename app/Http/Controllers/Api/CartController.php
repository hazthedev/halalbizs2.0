<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The buyer's DB cart (mobile plan §4) over CartService — the API has no
 * guest cart, so every route is behind auth:sanctum. Availability checks
 * are CartService::addItem's; prices mirror CartPage::presentLine.
 */
class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::ok($this->payload($request));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $variant = ProductVariant::with('product.store')->findOrFail($data['variant_id']);
        $qty = (int) ($data['qty'] ?? 1);

        // Live / holiday / sold-out refusals come from CartService::addItem (422 via
        // BuyerActionException); only an in-stock variant gets the "only N left" check.
        if ($variant->stock > 0) {
            $this->ensureStock($variant, $qty);
        }

        $this->cart->addItem($request->user(), $variant, $qty);

        return ApiResponse::ok($this->payload($request), null, 201);
    }

    public function update(Request $request, ProductVariant $variant): JsonResponse
    {
        $data = $request->validate([
            'qty' => ['nullable', 'integer', 'min:0'],
            'selected' => ['nullable', 'boolean'],
        ]);

        abort_unless($this->cart->hasItem($request->user(), $variant->id), 404);

        if (isset($data['qty'])) {
            $this->ensureStock($variant, (int) $data['qty']);
            $this->cart->updateQty($request->user(), $variant, (int) $data['qty']);
        }

        if (isset($data['selected'])) {
            $this->cart->setSelected($request->user(), $variant, (bool) $data['selected']);
        }

        return ApiResponse::ok($this->payload($request));
    }

    public function destroy(Request $request, ProductVariant $variant): JsonResponse
    {
        $this->cart->removeItem($request->user(), $variant);

        return ApiResponse::ok($this->payload($request));
    }

    public function selectAll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'selected' => ['required', 'boolean'],
            'store_id' => ['nullable', 'integer'],
        ]);

        $this->cart->setAllSelected($request->user(), (bool) $data['selected'], isset($data['store_id']) ? (int) $data['store_id'] : null);

        return ApiResponse::ok($this->payload($request));
    }

    private function ensureStock(ProductVariant $variant, int $qty): void
    {
        if ($qty > $variant->stock) {
            throw ValidationException::withMessages(['qty' => trans_choice('Only :count left in stock.|Only :count left in stock.', $variant->stock, ['count' => $variant->stock])]);
        }
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $user = $request->user();
        $locale = app()->getLocale();
        $selectedCount = 0;
        $selectedSubtotal = 0;

        $groups = $this->cart->groupedByStore($user)->map(function ($lines) use ($locale, &$selectedCount, &$selectedSubtotal) {
            $store = $lines->first()->variant->product->store;
            $subtotal = 0;

            $rows = $lines->map(function ($line) use ($locale, &$subtotal, &$selectedCount) {
                $variant = $line->variant;
                $product = $variant->product;
                $unit = $variant->effectivePriceSen();
                $available = $product->isLive() && $variant->stock > 0;

                if ($line->selected && $available) {
                    $subtotal += $unit * $line->qty;
                    $selectedCount += $line->qty;
                }

                return [
                    'variant_id' => $variant->id,
                    'product' => [
                        'id' => $product->id,
                        'slug' => $product->slug,
                        'name' => $product->getTranslation('name', $locale) ?: $product->getTranslation('name', 'en'),
                        'thumb' => $variant->getFirstMediaUrl('image', 'thumb') ?: ($product->getFirstMediaUrl('images', 'thumb') ?: null),
                    ],
                    'variant_label' => $variant->options_label,
                    'qty' => $line->qty,
                    'unit_price_sen' => $unit,
                    'list_price_sen' => (int) $variant->price_sen,
                    'stock' => (int) $variant->stock,
                    'selected' => (bool) $line->selected,
                    'available' => $available,
                    'cod_enabled' => (bool) $product->cod_enabled,
                ];
            })->values()->all();

            $selectedSubtotal += $subtotal;

            return [
                'store' => [
                    'id' => $store->id,
                    'slug' => $store->slug,
                    'name' => $store->name,
                    'logo' => $store->getFirstMediaUrl('logo', 'thumb') ?: null,
                ],
                'lines' => $rows,
                'subtotal_sen' => $subtotal,
            ];
        })->values()->all();

        return [
            'groups' => $groups,
            'item_count' => $this->cart->itemCount($user),
            'selected_count' => $selectedCount,
            'selected_subtotal_sen' => $selectedSubtotal,
            'purchasing_enabled' => $this->cart->purchasingEnabled(),
        ];
    }
}
