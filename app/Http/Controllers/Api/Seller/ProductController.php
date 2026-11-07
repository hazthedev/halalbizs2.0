<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\ProductStatus;
use App\Http\Api\ApiResponse;
use App\Http\Resources\SellerProductDetailResource;
use App\Http\Resources\SellerProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductWriteService;
use App\Support\JsonSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The seller's catalogue (mobile plan §4): list, detail, single-variant
 * create/edit, images, and the publish/delist/relist moves. Writes live in
 * ProductWriteService; this controller validates and shapes.
 */
class ProductController extends SellerController
{
    public const PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::enum(ProductStatus::class)],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));

        $products = $this->currentStore($request)->products()
            ->with(['media', 'variants', 'category'])
            ->when(($data['status'] ?? null) !== null, fn ($query) => $query->where('status', $data['status']))
            ->when($term !== '', function (Builder $query) use ($term) {
                // Same recipe as Seller\Products\Index: lowered, escaped LIKE on
                // the JSON name, or a variant SKU (M-19).
                $like = JsonSearch::pattern($term);

                $query->where(fn (Builder $q) => $q
                    ->whereRaw(JsonSearch::clause('name'), [$like])
                    ->orWhereHas('variants', fn (Builder $variants) => $variants->whereRaw(JsonSearch::clause('sku'), [$like])));
            })
            ->latest('updated_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return ApiResponse::ok(SellerProductResource::collection($products));
    }

    public function show(Request $request, int $product): JsonResponse
    {
        return $this->detail($request, $product);
    }

    public function store(Request $request, ProductWriteService $products): JsonResponse
    {
        $product = $products->create($this->currentStore($request), $this->validated($request, creating: true));

        return ApiResponse::ok(new SellerProductDetailResource($this->product($request, $product->id)), null, 201);
    }

    public function update(Request $request, int $product, ProductWriteService $products): JsonResponse
    {
        $row = $this->product($request, $product);

        $products->update($row, $this->validated($request, creating: false));

        return $this->detail($request, $row->id);
    }

    public function images(Request $request, int $product, ProductWriteService $products): JsonResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:'.ProductWriteService::MAX_IMAGES],
            'images.*' => ['image', 'max:4096'],
        ], [], ['images.*' => __('image')]);

        $row = $this->product($request, $product);

        $products->addImages($row, $request->file('images'));

        return $this->detail($request, $row->id);
    }

    public function destroyImage(Request $request, int $product, int $media): JsonResponse
    {
        $this->product($request, $product)->media()->where('collection_name', 'images')->findOrFail($media)->delete();

        return ApiResponse::ok(null);
    }

    public function publish(Request $request, int $product, ProductWriteService $products): JsonResponse
    {
        $row = $this->product($request, $product);

        return $this->attempt(function () use ($request, $row, $products) {
            $reason = $products->publish($row);

            return $reason === null
                ? $this->detail($request, $row->id)
                : ApiResponse::error('halal_blocked', $reason, 409);
        });
    }

    public function delist(Request $request, int $product, ProductWriteService $products): JsonResponse
    {
        $row = $this->product($request, $product);

        return $this->attempt(function () use ($request, $row, $products) {
            $products->delist($row);

            return $this->detail($request, $row->id);
        });
    }

    public function relist(Request $request, int $product, ProductWriteService $products): JsonResponse
    {
        $row = $this->product($request, $product);

        return $this->attempt(function () use ($request, $row, $products) {
            $reason = $products->relist($row);

            return $reason === null
                ? $this->detail($request, $row->id)
                : ApiResponse::error('halal_blocked', $reason, 409);
        });
    }

    /**
     * The seller form's rules for a single-variant product, in sen. Create
     * requires the basics; update takes any subset.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        $base = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$base, 'array'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'name.ms' => ['nullable', 'string', 'max:255'],
            'name.vi' => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'array'],
            'description.en' => ['nullable', 'string', 'max:65000'],
            'description.ms' => ['nullable', 'string', 'max:65000'],
            'description.vi' => ['nullable', 'string', 'max:65000'],
            'category_id' => [
                $base, 'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
                // Same rule as the form's cascader: only a leaf may be chosen.
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (Category::query()->whereKey($value)->whereHas('children', fn (Builder $q) => $q->active())->exists()) {
                        $fail(__('Pick the most specific category — this one has sub-categories.'));
                    }
                },
            ],
            'price_sen' => [$base, 'integer', 'min:1'],
            'sale_price_sen' => ['nullable', 'integer', 'min:1'],
            'stock' => [$base, 'integer', 'min:0'],
            'sku' => ['nullable', 'string', 'max:64'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_mm' => ['nullable', 'integer', 'min:0'],
            'width_mm' => ['nullable', 'integer', 'min:0'],
            'height_mm' => ['nullable', 'integer', 'min:0'],
            'cod_enabled' => ['sometimes', 'boolean'],
            'variants' => $creating ? ['prohibited'] : ['sometimes', 'array'],
            'variants.*.id' => ['required', 'integer'],
            'variants.*.price_sen' => ['sometimes', 'integer', 'min:1'],
            'variants.*.sale_price_sen' => ['nullable', 'integer', 'min:1'],
            'variants.*.stock' => ['sometimes', 'integer', 'min:0'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
        ], [], [
            'name.en' => __('product name (English)'),
            'category_id' => __('category'),
        ]);
    }

    private function product(Request $request, int $id): Product
    {
        return $this->currentStore($request)->products()->with(['media', 'variants', 'category'])->findOrFail($id);
    }

    private function detail(Request $request, int $id): JsonResponse
    {
        return ApiResponse::ok(new SellerProductDetailResource($this->product($request, $id)));
    }
}
