<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Exceptions\ProductPublishBlockedException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Settings\ModerationSettings;
use App\Support\ContentLocales;
use App\Support\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Product writes for the mobile API (single-variant products: create, edit
 * basics + per-variant price/stock, images, publish) and the status moves the
 * seller products index already made (delist / relist). Lifted from
 * Seller\Products\Form::save() — same sanitizers, same translation writer,
 * same default-variant shape. The Livewire Form itself still runs its own
 * matrix-aware save; only the index's delist/relist call in here.
 *
 * Stock edits go through StockService so every change leaves a movement row
 * (the web form writes `variant.stock` directly — a pre-existing gap).
 */
class ProductWriteService
{
    public const MAX_IMAGES = 9;

    /** Top-level price/stock keys that address the default variant. */
    private const VARIANT_KEYS = ['price_sen', 'sale_price_sen', 'stock', 'sku'];

    public function __construct(
        private StockService $stock,
        private ModerationSettings $moderation,
        private ProductPublishPolicy $policy,
    ) {}

    /**
     * New draft with one default variant.
     *
     * @param  array<string, mixed>  $data  Validated: name{en,ms?,vi?}, description?, category_id,
     *                                      price_sen, sale_price_sen?, stock, sku?, weight_grams?,
     *                                      length_mm?, width_mm?, height_mm?, cod_enabled?
     */
    public function create(Store $store, array $data): Product
    {
        return DB::transaction(function () use ($store, $data) {
            $product = new Product([
                'store_id' => $store->id,
                'status' => ProductStatus::Draft,
                'condition' => 'new',
                'tax_class' => 'exempt',
                'cod_enabled' => true,
            ]);

            $this->fill($product, $data);
            $product->save();

            $variant = $product->variants()->create([
                'sku' => null,
                'options_label' => null,
                'option_value_ids' => null,
                'price_sen' => 0,
                'stock' => 0,
                'is_default' => true,
                'position' => 0,
            ]);

            $this->applyVariant($variant, Arr::only($data, self::VARIANT_KEYS), '');

            return $product;
        });
    }

    /**
     * Basics plus price/stock on the default variant (top-level keys) or on
     * named existing variants (`variants: [{id, price_sen?, sale_price_sen?,
     * stock?, sku?}]`). A variant id outside this product is a 404.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ProductPublishBlockedException when a live product is moved into
     *                                        a category the halal policy blocks (rolled back).
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $this->fill($product, $data);
            $product->save();

            // A live listing cannot sidestep the publish gate by changing
            // category after the fact (H-6). Fresh relation: the eager-loaded
            // category predates the fill.
            if ($product->status === ProductStatus::Live && array_key_exists('category_id', $data)) {
                $this->assertAllowed($product->unsetRelation('category'));
            }

            $rows = [];

            if (Arr::hasAny($data, self::VARIANT_KEYS)) {
                $default = $product->variants()->lockForUpdate()->where('is_default', true)->first()
                    ?? $product->variants()->lockForUpdate()->first();

                if ($default !== null) {
                    $rows[''] = ['id' => $default->id] + Arr::only($data, self::VARIANT_KEYS);
                }
            }

            foreach ($data['variants'] ?? [] as $index => $row) {
                $rows["variants.$index."] = $row;
            }

            foreach ($rows as $prefix => $row) {
                // Locked read: the stock delta below is computed against the
                // row as it is NOW, not the copy the request was built from.
                $this->applyVariant($product->variants()->lockForUpdate()->findOrFail($row['id']), $row, $prefix);
            }

            return $product->fresh();
        });
    }

    /**
     * Same gate as Form::publish for an existing product: PendingReview when
     * moderation requires approval, else Live — unless the halal policy
     * blocks it (H-6).
     *
     * @throws ValidationException without an image or a leaf category (the form's publish rules).
     * @throws InvalidArgumentException for a banned product.
     * @throws ProductPublishBlockedException with the policy's reason.
     */
    public function publish(Product $product): Product
    {
        if ($product->status === ProductStatus::Banned) {
            throw new InvalidArgumentException(__('A banned product cannot be published.'));
        }

        if (($problem = self::categoryProblem($product->category_id)) !== null) {
            throw ValidationException::withMessages(['category_id' => $problem]);
        }

        if ($product->getMedia('images')->isEmpty()) {
            throw ValidationException::withMessages(['images' => __('Add at least one image before publishing.')]);
        }

        $this->goLive($product);

        if ($product->status === ProductStatus::Live && $product->published_at === null) {
            $product->update(['published_at' => now()]);
        }

        return $product;
    }

    /**
     * The form's validateCategory(): active, and a leaf. Null when fine, else
     * the seller-facing message. Shared with the API's request rule.
     */
    public static function categoryProblem(?int $categoryId): ?string
    {
        $category = $categoryId === null ? null : Category::query()->active()->find($categoryId);

        if ($category === null) {
            return __('Choose a category.');
        }

        if ($category->children()->active()->exists()) {
            return __('Pick the most specific category — this one has sub-categories.');
        }

        return null;
    }

    /** @throws InvalidArgumentException unless the product is live. */
    public function delist(Product $product): Product
    {
        if ($product->status !== ProductStatus::Live) {
            throw new InvalidArgumentException(__('Only a live product can be delisted.'));
        }

        $product->update(['status' => ProductStatus::Delisted]);

        return $product;
    }

    /**
     * Delisted → Live (or PendingReview), through the same halal gate as
     * publish.
     *
     * @throws InvalidArgumentException unless the product is delisted.
     * @throws ProductPublishBlockedException with the policy's reason.
     */
    public function relist(Product $product): Product
    {
        if ($product->status !== ProductStatus::Delisted) {
            throw new InvalidArgumentException(__('Only a delisted product can be relisted.'));
        }

        $this->goLive($product);

        return $product;
    }

    /**
     * Append to the `images` collection, capped at MAX_IMAGES in total.
     *
     * @param  list<UploadedFile>  $files
     *
     * @throws ValidationException over the cap.
     */
    public function addImages(Product $product, array $files): void
    {
        DB::transaction(function () use ($product, $files) {
            // Count under the product row lock, so two parallel uploads cannot
            // both see 8 and land 16.
            $locked = Product::whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $total = $locked->media()->where('collection_name', 'images')->count() + count($files);

            if ($total > self::MAX_IMAGES) {
                throw ValidationException::withMessages(['images' => __('A product can have up to :max images — remove :excess.', [
                    'max' => self::MAX_IMAGES,
                    'excess' => $total - self::MAX_IMAGES,
                ])]);
            }

            foreach ($files as $file) {
                $locked->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('images');
            }
        });
    }

    private function goLive(Product $product): void
    {
        $status = $this->moderation->require_product_approval
            ? ProductStatus::PendingReview
            : ProductStatus::Live;

        if ($status === ProductStatus::Live) {
            $this->assertAllowed($product);
        }

        $product->update(['status' => $status]);
    }

    /** @throws ProductPublishBlockedException */
    private function assertAllowed(Product $product): void
    {
        $reason = $this->policy->blockedReason($product);

        if ($reason !== null) {
            throw new ProductPublishBlockedException($reason);
        }
    }

    /** @param array<string, mixed> $data */
    private function fill(Product $product, array $data): void
    {
        $product->fill(Arr::only($data, ['category_id', 'weight_grams', 'length_mm', 'width_mm', 'height_mm', 'cod_enabled']));

        // Names are plain text; descriptions keep the sanitizer's tag allow-list
        // (C2). Locales left out of a PATCH keep their current text.
        if (isset($data['name'])) {
            ContentLocales::write($product, 'name', $data['name'] + ContentLocales::read($product, 'name'),
                transform: fn (string $text): string => trim(strip_tags($text)));
        }

        if (isset($data['description'])) {
            ContentLocales::write($product, 'description', $data['description'] + ContentLocales::read($product, 'description'),
                transform: fn (string $html): string => HtmlSanitizer::clean($html));
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string  $prefix  Error-bag prefix ('' for the default variant, 'variants.N.' otherwise).
     */
    private function applyVariant(ProductVariant $variant, array $row, string $prefix): void
    {
        $priceSen = (int) ($row['price_sen'] ?? $variant->price_sen);
        $saleSen = array_key_exists('sale_price_sen', $row) ? $row['sale_price_sen'] : $variant->sale_price_sen;
        $saleSen = $saleSen === null ? null : (int) $saleSen;

        if ($saleSen !== null && $saleSen >= $priceSen) {
            throw ValidationException::withMessages(["{$prefix}sale_price_sen" => __('The sale price must be below the normal price.')]);
        }

        $variant->fill([
            'price_sen' => $priceSen,
            'sale_price_sen' => $saleSen,
            'sale_starts_at' => $saleSen === null ? null : $variant->sale_starts_at,
            'sale_ends_at' => $saleSen === null ? null : $variant->sale_ends_at,
        ]);

        if (array_key_exists('sku', $row)) {
            $variant->sku = trim((string) $row['sku']) !== '' ? trim((string) $row['sku']) : null;
        }

        $variant->save();

        if (array_key_exists('stock', $row) && (int) $row['stock'] !== (int) $variant->stock) {
            $this->stock->apply($variant, (int) $row['stock'] - (int) $variant->stock, StockMovementType::Adjustment, 'seller-api');
        }
    }
}
