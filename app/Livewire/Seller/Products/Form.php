<?php

namespace App\Livewire\Seller\Products;

use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Settings\ModerationSettings;
use App\Support\RinggitInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Product create/edit — single page with sections (docs/07 §A4).
 * The variant matrix lives in $optionGroups + $matrix; on save the matrix is
 * reconciled against existing variants by sorted option_value_ids: matching
 * combos are updated, new combos created, removed combos deleted — unless the
 * variant has order history, which blocks the save (delist instead).
 */
#[Layout('layouts.seller')]
class Form extends Component
{
    use CurrentStore, WithFileUploads;

    public const MAX_IMAGES = 9;

    public const MAX_OPTION_GROUPS = 2;

    #[Locked]
    public ?int $productId = null;

    // ── Basics ─────────────────────────────────────────────────────────
    /** @var array{en: string, ms: string} */
    public array $name = ['en' => '', 'ms' => ''];

    /** @var array{en: string, ms: string} */
    public array $description = ['en' => '', 'ms' => ''];

    public ?int $categoryTop = null;

    public ?int $categoryChild = null;

    public ?int $categoryLeaf = null;

    public ?int $brandId = null;

    public string $condition = 'new';

    // ── Images ─────────────────────────────────────────────────────────
    /** @var array<int, TemporaryUploadedFile> */
    public array $newImages = [];

    // ── Variations ─────────────────────────────────────────────────────
    public bool $hasVariations = false;

    // Single-variant inputs (variations OFF) — RM strings, integer-sen conversion only.
    public string $price = '';

    public string $salePrice = '';

    public string $stock = '0';

    public string $sku = '';

    /** @var array<int, array{name: string, values: array<int, string>, draft: string}> */
    public array $optionGroups = [];

    /** @var array<string, array{label: string, price: string, sale_price: string, stock: string, sku: string, variant_id: int|null}> keyed by joined value indexes */
    public array $matrix = [];

    /** @var array<string, TemporaryUploadedFile|null> per-combination image uploads */
    public array $matrixImages = [];

    public string $bulkPrice = '';

    public string $bulkSalePrice = '';

    public string $bulkStock = '';

    // ── Sale schedule (applies to every variant that has a sale price) ──
    public string $saleStartsAt = '';

    public string $saleEndsAt = '';

    // ── Shipping ───────────────────────────────────────────────────────
    public ?int $weightGrams = null;

    public ?int $lengthMm = null;

    public ?int $widthMm = null;

    public ?int $heightMm = null;

    public bool $codEnabled = true;

    public function mount(?Product $product = null): void
    {
        if ($product === null || ! $product->exists) {
            return;
        }

        $this->authorizeStore($product->store_id);
        $this->productId = $product->id;
        $this->fillFromProduct($product);
    }

    // ── Option groups + matrix ─────────────────────────────────────────

    public function updatedHasVariations(bool $value): void
    {
        if ($value && $this->optionGroups === []) {
            $this->addOptionGroup();
        }

        $this->regenerateMatrix();
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'optionGroups')) {
            $this->regenerateMatrix();
        }
    }

    public function addOptionGroup(): void
    {
        if (count($this->optionGroups) >= self::MAX_OPTION_GROUPS) {
            return;
        }

        $this->optionGroups[] = ['name' => '', 'values' => [], 'draft' => ''];
    }

    public function removeOptionGroup(int $index): void
    {
        unset($this->optionGroups[$index]);
        $this->optionGroups = array_values($this->optionGroups);
        $this->regenerateMatrix();
    }

    public function addOptionValue(int $groupIndex): void
    {
        if (! isset($this->optionGroups[$groupIndex])) {
            return;
        }

        $value = trim($this->optionGroups[$groupIndex]['draft']);
        $this->optionGroups[$groupIndex]['draft'] = '';

        if ($value === '') {
            return;
        }

        $exists = collect($this->optionGroups[$groupIndex]['values'])
            ->contains(fn (string $existing) => mb_strtolower($existing) === mb_strtolower($value));

        if (! $exists) {
            $this->optionGroups[$groupIndex]['values'][] = $value;
        }

        $this->regenerateMatrix();
    }

    public function removeOptionValue(int $groupIndex, int $valueIndex): void
    {
        unset($this->optionGroups[$groupIndex]['values'][$valueIndex]);
        $this->optionGroups[$groupIndex]['values'] = array_values($this->optionGroups[$groupIndex]['values']);
        $this->regenerateMatrix();
    }

    /**
     * Rebuild the cartesian matrix from current groups/values, carrying
     * previous row data over by combination label.
     */
    public function regenerateMatrix(): void
    {
        $groups = array_values(array_filter(
            array_map(fn (array $group) => array_values($group['values']), $this->optionGroups),
            fn (array $values) => $values !== [],
        ));

        if (! $this->hasVariations || $groups === []) {
            $this->matrix = [];

            return;
        }

        $previousByLabel = collect($this->matrix)->keyBy('label');
        $matrix = [];

        foreach ($this->combinations($groups) as $combo) {
            $key = implode('-', array_column($combo, 'index'));
            $label = implode(' / ', array_column($combo, 'value'));
            $previous = $previousByLabel->get($label);

            $matrix[$key] = [
                'label' => $label,
                'price' => $previous['price'] ?? '',
                'sale_price' => $previous['sale_price'] ?? '',
                'stock' => $previous['stock'] ?? '0',
                'sku' => $previous['sku'] ?? '',
                'variant_id' => $previous['variant_id'] ?? null,
            ];
        }

        $this->matrix = $matrix;
    }

    public function applyPriceToAll(): void
    {
        $this->applyToAllRows('price', $this->bulkPrice);
    }

    public function applySalePriceToAll(): void
    {
        $this->applyToAllRows('sale_price', $this->bulkSalePrice);
    }

    public function applyStockToAll(): void
    {
        $this->applyToAllRows('stock', $this->bulkStock);
    }

    // ── Category cascader ──────────────────────────────────────────────

    public function updatedCategoryTop(): void
    {
        $this->categoryChild = null;
        $this->categoryLeaf = null;
    }

    public function updatedCategoryChild(): void
    {
        $this->categoryLeaf = null;
    }

    // ── Images ─────────────────────────────────────────────────────────

    public function removeNewImage(int $index): void
    {
        unset($this->newImages[$index]);
        $this->newImages = array_values($this->newImages);
    }

    public function moveNewImage(int $index, int $direction): void
    {
        $target = $index + ($direction < 0 ? -1 : 1);

        if (! isset($this->newImages[$index]) || ! isset($this->newImages[$target])) {
            return;
        }

        [$this->newImages[$index], $this->newImages[$target]] = [$this->newImages[$target], $this->newImages[$index]];
    }

    public function removeMedia(int $mediaId): void
    {
        $this->loadedProduct()?->media()
            ->where('collection_name', 'images')
            ->find($mediaId)
            ?->delete();
    }

    public function moveMedia(int $mediaId, int $direction): void
    {
        $product = $this->loadedProduct();

        if ($product === null) {
            return;
        }

        $ids = $product->media()
            ->where('collection_name', 'images')
            ->orderBy('order_column')
            ->pluck('id')
            ->all();

        $index = array_search($mediaId, $ids, true);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($index === false || ! isset($ids[$target])) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        Media::setNewOrder($ids);
    }

    // ── Save ───────────────────────────────────────────────────────────

    public function saveDraft(): void
    {
        $this->save(ProductStatus::Draft);
    }

    public function publish(): void
    {
        $status = app(ModerationSettings::class)->require_product_approval
            ? ProductStatus::PendingReview
            : ProductStatus::Live;

        $this->save($status, publishing: true);
    }

    public function render()
    {
        $editing = $this->productId !== null;

        return view('livewire.seller.products.form', [
            'editing' => $editing,
            'topCategories' => Category::query()->active()->whereNull('parent_id')->orderBy('position')->get(),
            'childCategories' => $this->categoryTop ? Category::query()->active()->where('parent_id', $this->categoryTop)->orderBy('position')->get() : collect(),
            'leafCategories' => $this->categoryChild ? Category::query()->active()->where('parent_id', $this->categoryChild)->orderBy('position')->get() : collect(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'conditions' => ProductCondition::cases(),
            'existingMedia' => $this->loadedProduct()?->media()->where('collection_name', 'images')->orderBy('order_column')->get() ?? collect(),
            'requireApproval' => app(ModerationSettings::class)->require_product_approval,
            'maxImages' => self::MAX_IMAGES,
        ])->title($editing ? __('Edit product') : __('Add product'));
    }

    private function save(ProductStatus $status, bool $publishing = false): void
    {
        $this->validateForm($publishing);

        $product = DB::transaction(function () use ($status) {
            $store = $this->currentStore();

            $product = $this->productId !== null
                ? Product::query()->where('store_id', $store->id)->findOrFail($this->productId)
                : new Product(['store_id' => $store->id]);

            $product->fill([
                'category_id' => $this->selectedCategoryId(),
                'brand_id' => $this->brandId,
                'condition' => $this->condition,
                'status' => $status,
                'weight_grams' => $this->weightGrams,
                'length_mm' => $this->lengthMm,
                'width_mm' => $this->widthMm,
                'height_mm' => $this->heightMm,
                'cod_enabled' => $this->codEnabled,
            ]);

            if ($status === ProductStatus::Live && $product->published_at === null) {
                $product->published_at = now();
            }

            $this->applyTranslations($product);

            $product->save();

            if ($this->hasVariations) {
                $this->syncVariantMatrix($product);
            } else {
                $this->syncSingleVariant($product);
            }

            foreach ($this->newImages as $file) {
                $product->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('images');
            }

            return $product;
        });

        $this->dispatch('toast', message: match ($product->status) {
            ProductStatus::Live => __('Product published'),
            ProductStatus::PendingReview => __('Submitted for review — it goes live once approved.'),
            default => __('Draft saved'),
        });

        $this->redirect(route('seller.products.index'), navigate: true);
    }

    private function applyTranslations(Product $product): void
    {
        $sanitize = fn (string $html): string => strip_tags($html, '<p><br><ul><ol><li><strong><em>');

        // en is ALWAYS written (fallback locale); ms only when filled.
        $product->setTranslation('name', 'en', trim($this->name['en']));
        $product->setTranslation('description', 'en', $sanitize(trim($this->description['en'] ?? '')));

        if (trim($this->name['ms'] ?? '') !== '') {
            $product->setTranslation('name', 'ms', trim($this->name['ms']));
        } else {
            $product->forgetTranslation('name', 'ms');
        }

        if (trim($this->description['ms'] ?? '') !== '') {
            $product->setTranslation('description', 'ms', $sanitize(trim($this->description['ms'])));
        } else {
            $product->forgetTranslation('description', 'ms');
        }
    }

    /** Variations OFF → exactly one default variant, options_label null. */
    private function syncSingleVariant(Product $product): void
    {
        $product->load('variants');

        $keep = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();

        foreach ($product->variants as $variant) {
            if ($keep !== null && $variant->id === $keep->id) {
                continue;
            }

            $this->assertRemovable($variant);
            $variant->delete();
        }

        foreach ($product->options as $option) {
            $option->values()->delete();
            $option->delete();
        }

        $saleSen = RinggitInput::toSen($this->salePrice);

        $payload = [
            'sku' => trim($this->sku) !== '' ? trim($this->sku) : null,
            'options_label' => null,
            'option_value_ids' => null,
            'price_sen' => RinggitInput::toSen($this->price),
            'sale_price_sen' => $saleSen,
            'sale_starts_at' => $saleSen !== null ? $this->parseScheduleDate($this->saleStartsAt) : null,
            'sale_ends_at' => $saleSen !== null ? $this->parseScheduleDate($this->saleEndsAt) : null,
            'stock' => (int) $this->stock,
            'is_default' => true,
            'position' => 0,
        ];

        $keep !== null ? $keep->update($payload) : $product->variants()->create($payload);
    }

    /**
     * Variations ON → reconcile options/values/variants with the matrix.
     * Variants are matched by sorted option_value_ids; removed combos are
     * deleted unless they carry order history, which aborts the save.
     */
    private function syncVariantMatrix(Product $product): void
    {
        $product->load(['options.values', 'variants']);

        // 1. Find-or-create options (by group position) and values (by string).
        $valueIdLookup = [];
        $existingOptions = $product->options->values();

        foreach (array_values($this->optionGroups) as $groupIndex => $group) {
            $option = $existingOptions->get($groupIndex);

            if ($option === null) {
                $option = $product->options()->create(['name' => trim($group['name']), 'position' => $groupIndex]);
            } else {
                $option->update(['name' => trim($group['name']), 'position' => $groupIndex]);
            }

            $existingValues = $option->values()->get();

            foreach (array_values($group['values']) as $valueIndex => $value) {
                $valueModel = $existingValues->first(fn ($existing) => $existing->value === $value);

                if ($valueModel === null) {
                    $valueModel = $option->values()->create(['value' => $value, 'position' => $valueIndex]);
                } else {
                    $valueModel->update(['position' => $valueIndex]);
                }

                $valueIdLookup[$groupIndex][$value] = $valueModel->id;
            }
        }

        // 2. Target rows keyed by sorted value-id signature.
        $targets = [];

        foreach ($this->matrix as $key => $row) {
            $valueIds = [];
            $labels = [];

            foreach (explode('-', (string) $key) as $groupIndex => $valueIndex) {
                $value = $this->optionGroups[$groupIndex]['values'][(int) $valueIndex] ?? null;

                if ($value === null) {
                    continue 2; // stale row — combination no longer exists
                }

                $valueIds[] = $valueIdLookup[$groupIndex][$value];
                $labels[] = $value;
            }

            $sorted = $valueIds;
            sort($sorted);
            $saleSen = RinggitInput::toSen($row['sale_price']);

            $targets[implode(',', $sorted)] = [
                'key' => (string) $key,
                'payload' => [
                    'options_label' => implode(' / ', $labels),
                    'option_value_ids' => $valueIds,
                    'price_sen' => RinggitInput::toSen($row['price']),
                    'sale_price_sen' => $saleSen,
                    'sale_starts_at' => $saleSen !== null ? $this->parseScheduleDate($this->saleStartsAt) : null,
                    'sale_ends_at' => $saleSen !== null ? $this->parseScheduleDate($this->saleEndsAt) : null,
                    'stock' => (int) $row['stock'],
                    'sku' => trim($row['sku']) !== '' ? trim($row['sku']) : null,
                    'is_default' => false,
                ],
            ];
        }

        // 3. Reconcile: keep matching combos, delete removed ones (block if ordered).
        $kept = [];

        foreach ($product->variants as $variant) {
            $ids = $variant->option_value_ids ?? [];
            sort($ids);
            $signature = implode(',', $ids);

            if ($ids !== [] && isset($targets[$signature])) {
                $kept[$signature] = $variant;
            } else {
                $this->assertRemovable($variant);
                $variant->delete();
            }
        }

        // 4. Update kept / create new, in matrix order; first row is the default.
        $position = 0;
        $byKey = [];

        foreach ($targets as $signature => $target) {
            $payload = $target['payload'];
            $payload['position'] = $position;
            $payload['is_default'] = $position === 0;

            $variant = $kept[$signature] ?? null;

            if ($variant !== null) {
                $variant->update($payload);
            } else {
                $variant = $product->variants()->create($payload);
            }

            $byKey[$target['key']] = $variant;
            $position++;
        }

        // 5. Per-row images (singleFile collection replaces the previous one).
        foreach ($this->matrixImages as $key => $file) {
            if ($file !== null && isset($byKey[(string) $key])) {
                $byKey[(string) $key]->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('image');
            }
        }

        // 6. Prune values/options that no longer exist (their variants are gone by now).
        $options = $product->options()->orderBy('position')->get();

        foreach ($options as $index => $option) {
            if ($index >= count($this->optionGroups)) {
                $option->values()->delete();
                $option->delete();

                continue;
            }

            $option->values()->whereNotIn('value', $this->optionGroups[$index]['values'])->delete();
        }
    }

    private function assertRemovable(ProductVariant $variant): void
    {
        if ($variant->orderItems()->exists()) {
            throw ValidationException::withMessages([
                'matrix' => __('":label" has order history and can\'t be removed. Delist the product instead.', [
                    'label' => $variant->options_label ?? __('Default variant'),
                ]),
            ]);
        }
    }

    // ── Validation ─────────────────────────────────────────────────────

    private function validateForm(bool $publishing): void
    {
        $this->validate([
            'name.en' => ['required', 'string', 'max:255'],
            'name.ms' => ['nullable', 'string', 'max:255'],
            'description.en' => ['nullable', 'string', 'max:65000'],
            'description.ms' => ['nullable', 'string', 'max:65000'],
            'brandId' => ['nullable', Rule::exists('brands', 'id')->where('is_active', true)],
            'condition' => ['required', Rule::enum(ProductCondition::class)],
            'newImages' => ['array', 'max:'.self::MAX_IMAGES],
            'newImages.*' => ['image', 'max:4096'],
            'matrixImages.*' => ['nullable', 'image', 'max:4096'],
            'weightGrams' => ['nullable', 'integer', 'min:0'],
            'lengthMm' => ['nullable', 'integer', 'min:0'],
            'widthMm' => ['nullable', 'integer', 'min:0'],
            'heightMm' => ['nullable', 'integer', 'min:0'],
        ], attributes: [
            'name.en' => __('product name (English)'),
            'newImages.*' => __('image'),
        ]);

        $this->validateCategory();
        $this->validateImages($publishing);
        $this->validateSchedule();

        if ($this->hasVariations) {
            $this->validateMatrix();
        } else {
            $this->validateSinglePricing();
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            throw ValidationException::withMessages($this->getErrorBag()->getMessages());
        }
    }

    private function validateCategory(): void
    {
        $categoryId = $this->selectedCategoryId();

        if ($categoryId === null) {
            $this->addError('category', __('Choose a category.'));

            return;
        }

        $category = Category::query()->active()->find($categoryId);

        if ($category === null) {
            $this->addError('category', __('Choose a category.'));

            return;
        }

        if ($category->children()->active()->exists()) {
            $this->addError('category', __('Pick the most specific category — this one has sub-categories.'));
        }
    }

    private function validateImages(bool $publishing): void
    {
        $existingCount = $this->loadedProduct()?->media()->where('collection_name', 'images')->count() ?? 0;
        $total = $existingCount + count($this->newImages);

        if ($total > self::MAX_IMAGES) {
            $this->addError('newImages', __('A product can have up to :max images — remove :excess.', [
                'max' => self::MAX_IMAGES,
                'excess' => $total - self::MAX_IMAGES,
            ]));
        }

        if ($publishing && $total < 1) {
            $this->addError('newImages', __('Add at least one image before publishing.'));
        }
    }

    private function validateSchedule(): void
    {
        $starts = $this->parseScheduleDate($this->saleStartsAt);
        $ends = $this->parseScheduleDate($this->saleEndsAt);

        if (trim($this->saleStartsAt) !== '' && $starts === null) {
            $this->addError('saleStartsAt', __('Enter a valid date and time.'));
        }

        if (trim($this->saleEndsAt) !== '' && $ends === null) {
            $this->addError('saleEndsAt', __('Enter a valid date and time.'));
        }

        if ($starts !== null && $ends !== null && $ends->lte($starts)) {
            $this->addError('saleEndsAt', __('The sale must end after it starts.'));
        }
    }

    private function validateSinglePricing(): void
    {
        $priceSen = RinggitInput::toSen($this->price);

        if ($priceSen === null || $priceSen <= 0) {
            $this->addError('price', __('Enter a price above RM 0 — e.g. 19.90.'));
        }

        if (trim($this->salePrice) !== '') {
            $saleSen = RinggitInput::toSen($this->salePrice);

            if ($saleSen === null || $saleSen <= 0) {
                $this->addError('salePrice', __('Enter a valid sale price — e.g. 15.90.'));
            } elseif ($priceSen !== null && $saleSen >= $priceSen) {
                $this->addError('salePrice', __('The sale price must be below the normal price.'));
            }
        }

        if (! ctype_digit($this->stock)) {
            $this->addError('stock', __('Stock must be 0 or more.'));
        }

        if (mb_strlen(trim($this->sku)) > 64) {
            $this->addError('sku', __('SKUs can be up to 64 characters.'));
        }
    }

    private function validateMatrix(): void
    {
        $groups = array_values($this->optionGroups);

        if ($groups === [] || count($groups) > self::MAX_OPTION_GROUPS) {
            $this->addError('optionGroups', __('Use 1 or 2 option groups.'));

            return;
        }

        $names = [];

        foreach ($groups as $index => $group) {
            $name = trim($group['name']);

            if ($name === '') {
                $this->addError("optionGroups.{$index}.name", __('Name this option — e.g. Colour or Size.'));
            } elseif (in_array(mb_strtolower($name), $names, true)) {
                $this->addError("optionGroups.{$index}.name", __('Option names must be different.'));
            }

            $names[] = mb_strtolower($name);

            if ($group['values'] === []) {
                $this->addError("optionGroups.{$index}.values", __('Add at least one value.'));
            }
        }

        if ($this->matrix === []) {
            $this->addError('matrix', __('Add option values to generate the variant table.'));

            return;
        }

        $seenSkus = [];

        foreach ($this->matrix as $key => $row) {
            $priceSen = RinggitInput::toSen($row['price']);

            if ($priceSen === null || $priceSen <= 0) {
                $this->addError("matrix.{$key}.price", __('Price above RM 0 required.'));
            }

            if (trim($row['sale_price']) !== '') {
                $saleSen = RinggitInput::toSen($row['sale_price']);

                if ($saleSen === null || $saleSen <= 0) {
                    $this->addError("matrix.{$key}.sale_price", __('Invalid sale price.'));
                } elseif ($priceSen !== null && $saleSen >= $priceSen) {
                    $this->addError("matrix.{$key}.sale_price", __('Must be below the price.'));
                }
            }

            if (! ctype_digit((string) $row['stock'])) {
                $this->addError("matrix.{$key}.stock", __('Stock must be 0 or more.'));
            }

            $sku = mb_strtolower(trim($row['sku']));

            if ($sku !== '') {
                if (mb_strlen($sku) > 64) {
                    $this->addError("matrix.{$key}.sku", __('Up to 64 characters.'));
                } elseif (in_array($sku, $seenSkus, true)) {
                    $this->addError("matrix.{$key}.sku", __('SKUs must be unique within this product.'));
                }

                $seenSkus[] = $sku;
            }
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function loadedProduct(): ?Product
    {
        if ($this->productId === null) {
            return null;
        }

        return Product::query()
            ->where('store_id', $this->currentStore()->id)
            ->findOrFail($this->productId);
    }

    private function selectedCategoryId(): ?int
    {
        return $this->categoryLeaf ?? $this->categoryChild ?? $this->categoryTop;
    }

    private function parseScheduleDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyToAllRows(string $field, string $value): void
    {
        $value = trim($value);

        if ($value === '' && $field !== 'sale_price') {
            return;
        }

        foreach (array_keys($this->matrix) as $key) {
            $this->matrix[$key][$field] = $value;
        }
    }

    private function fillFromProduct(Product $product): void
    {
        $product->load(['options.values', 'variants']);

        $this->name = [
            'en' => $product->getTranslation('name', 'en'),
            'ms' => $product->getTranslation('name', 'ms', false) ?? '',
        ];
        $this->description = [
            'en' => $product->getTranslation('description', 'en'),
            'ms' => $product->getTranslation('description', 'ms', false) ?? '',
        ];

        $this->fillCategorySelection($product->category_id);

        $this->brandId = $product->brand_id;
        $this->condition = $product->condition->value;
        $this->weightGrams = $product->weight_grams;
        $this->lengthMm = $product->length_mm;
        $this->widthMm = $product->width_mm;
        $this->heightMm = $product->height_mm;
        $this->codEnabled = $product->cod_enabled;

        $this->hasVariations = $product->options->isNotEmpty();

        if ($this->hasVariations) {
            $this->optionGroups = $product->options->map(fn (ProductOption $option) => [
                'name' => $option->name,
                'values' => $option->values->pluck('value')->all(),
                'draft' => '',
            ])->values()->all();

            $this->regenerateMatrix();

            foreach ($product->variants as $variant) {
                $key = $this->matrixKeyForVariant($product, $variant);

                if ($key === null || ! isset($this->matrix[$key])) {
                    continue;
                }

                $this->matrix[$key]['price'] = RinggitInput::fromSen($variant->price_sen);
                $this->matrix[$key]['sale_price'] = RinggitInput::fromSen($variant->sale_price_sen);
                $this->matrix[$key]['stock'] = (string) $variant->stock;
                $this->matrix[$key]['sku'] = $variant->sku ?? '';
                $this->matrix[$key]['variant_id'] = $variant->id;
            }
        } else {
            $variant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();

            if ($variant !== null) {
                $this->price = RinggitInput::fromSen($variant->price_sen);
                $this->salePrice = RinggitInput::fromSen($variant->sale_price_sen);
                $this->stock = (string) $variant->stock;
                $this->sku = $variant->sku ?? '';
            }
        }

        $scheduled = $product->variants->first(fn (ProductVariant $v) => $v->sale_starts_at !== null || $v->sale_ends_at !== null);

        if ($scheduled !== null) {
            $this->saleStartsAt = $scheduled->sale_starts_at?->format('Y-m-d\TH:i') ?? '';
            $this->saleEndsAt = $scheduled->sale_ends_at?->format('Y-m-d\TH:i') ?? '';
        }
    }

    private function fillCategorySelection(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $chain = [];
        $node = Category::find($categoryId);

        while ($node !== null) {
            array_unshift($chain, $node->id);
            $node = $node->parent;
        }

        $this->categoryTop = $chain[0] ?? null;
        $this->categoryChild = $chain[1] ?? null;
        $this->categoryLeaf = $chain[2] ?? null;
    }

    /** Map a variant's option_value_ids back to its matrix key (value indexes). */
    private function matrixKeyForVariant(Product $product, ProductVariant $variant): ?string
    {
        $valueIds = $variant->option_value_ids ?? [];

        if ($valueIds === []) {
            return null;
        }

        $indexes = [];

        foreach ($product->options as $option) {
            $index = $option->values->values()->search(
                fn ($value) => in_array($value->id, $valueIds, true),
            );

            if ($index === false) {
                return null;
            }

            $indexes[] = $index;
        }

        return implode('-', $indexes);
    }

    /**
     * Cartesian product of group values.
     *
     * @param  array<int, array<int, string>>  $groups
     * @return array<int, array<int, array{index: int, value: string}>>
     */
    private function combinations(array $groups): array
    {
        $result = [[]];

        foreach ($groups as $values) {
            $next = [];

            foreach ($result as $combo) {
                foreach ($values as $index => $value) {
                    $next[] = [...$combo, ['index' => $index, 'value' => $value]];
                }
            }

            $result = $next;
        }

        return $result;
    }
}
