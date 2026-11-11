<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Livewire\Storefront\Home;
use App\Models\Banner;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\HomeSection;
use App\Support\AppLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * The app's home tab (mobile plan §4): the web home's first product section
 * (same Home::products() ranking — boosted first for 'top', then organic),
 * top-level categories, the live flash sale and the active banners.
 */
class HomeController extends Controller
{
    public function __invoke(Home $home): JsonResponse
    {
        $locale = app()->getLocale();

        $section = HomeSection::active()->whereIn('type', ['product_carousel', 'product_grid'])->first();
        $featured = $home->products(
            $section?->payload['source'] ?? 'top',
            (int) ($section?->payload['limit'] ?? 12),
        );

        $categories = Category::active()->whereNull('parent_id')->orderBy('position')->get();

        $flashItems = FlashSaleItem::query()
            ->whereHas('flashSale', fn ($sale) => $sale->live())
            ->with(['variant.product.media', 'variant.product.store', 'variant.product.variants', 'variant.product.halalCertificate'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (FlashSaleItem $item) => $item->variant?->product?->isLive())
            ->values();

        $flashEndsAt = $flashItems->isEmpty() ? null : FlashSale::query()->live()->min('ends_at');

        return ApiResponse::ok([
            'featured' => ProductResource::collection($featured)->resolve(),
            'categories' => CategoryResource::collection($categories)->resolve(),
            'flash_sale' => $flashItems->isEmpty() ? null : [
                'ends_at' => Carbon::parse($flashEndsAt)->toIso8601String(),
                'products' => $flashItems->map(fn (FlashSaleItem $item) => (new ProductResource($item->variant->product))->resolve() + [
                    'flash' => [
                        'variant_id' => $item->product_variant_id,
                        'price_sen' => (int) $item->promo_price_sen,
                        'remaining' => $item->remaining(),
                        'percent_claimed' => $item->percentClaimed(),
                    ],
                ])->values()->all(),
            ],
            'banners' => Banner::active()->with('media')->get()->map(fn (Banner $banner) => [
                'id' => $banner->id,
                'title' => $banner->getTranslation('title', $locale),
                'subtitle' => $banner->getTranslation('subtitle', $locale),
                'cta_label' => $banner->getTranslation('cta_label', $locale),
                'link_url' => $banner->link_url,
                'app_url' => AppLinks::fromWebUrl($banner->link_url),
                'image' => $banner->imageUrl($locale) ?: null,
            ])->values()->all(),
        ]);
    }
}
