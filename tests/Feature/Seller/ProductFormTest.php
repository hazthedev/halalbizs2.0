<?php

use App\Enums\ProductStatus;
use App\Livewire\Seller\Products\Form;
use App\Livewire\Storefront\ProductDetail;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Settings\ModerationSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function productFormSeller(): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('seller');

    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

/** A leaf category (no children) so the cascader validation passes at the top level. */
function productFormCategory(): Category
{
    return Category::factory()->create();
}

/** Attach an order_items row to a variant so reconciliation must block its removal. */
function productFormOrderItemFor(ProductVariant $variant): OrderItem
{
    $order = Order::create([
        'order_no' => Order::generateOrderNo(),
        'user_id' => User::factory()->create()->id,
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'shipping_address' => ['name' => 'Test Buyer', 'line1' => '1 Jalan Test'],
        'subtotal_sen' => 1990,
        'shipping_total_sen' => 0,
        'grand_total_sen' => 1990,
        'placed_at' => now(),
    ]);

    $subOrder = SubOrder::create([
        'sub_order_no' => $order->order_no.'-1',
        'order_id' => $order->id,
        'store_id' => $variant->product->store_id,
        'status' => 'confirmed',
        'items_subtotal_sen' => 1990,
        'total_sen' => 1990,
        'commission_rate' => 5.00,
    ]);

    return OrderItem::create([
        'sub_order_id' => $subOrder->id,
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'product_name' => $variant->product->getTranslation('name', 'en'),
        'variant_label' => $variant->options_label,
        'unit_price_sen' => $variant->price_sen,
        'qty' => 1,
        'line_total_sen' => $variant->price_sen,
    ]);
}

test('creating a single-variant draft makes a default variant with sen pricing and en translation', function () {
    $seller = productFormSeller();
    $category = productFormCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Acacia Honey 500g')
        ->set('categoryTop', $category->id)
        ->set('price', '19.90')
        ->set('stock', '12')
        ->set('sku', 'HNY-500')
        ->call('saveDraft')
        ->assertHasNoErrors()
        ->assertRedirect(route('seller.products.index'));

    $product = Product::query()->where('store_id', $seller->store->id)->firstOrFail();

    expect($product->status)->toBe(ProductStatus::Draft)
        ->and($product->getTranslation('name', 'en'))->toBe('Acacia Honey 500g')
        ->and($product->category_id)->toBe($category->id)
        ->and($product->variants()->count())->toBe(1);

    $variant = $product->variants()->firstOrFail();

    expect($variant->is_default)->toBeTrue()
        ->and($variant->options_label)->toBeNull()
        ->and($variant->option_value_ids)->toBeNull()
        ->and($variant->price_sen)->toBe(1990)
        ->and($variant->stock)->toBe(12)
        ->and($variant->sku)->toBe('HNY-500');
});

test('creating a 3x2 matrix product produces 6 variants with option value ids and labels', function () {
    $seller = productFormSeller();
    $category = productFormCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Cotton Tee Premium')
        ->set('categoryTop', $category->id)
        ->set('hasVariations', true)
        ->set('optionGroups.0.name', 'Colour')
        ->set('optionGroups.0.draft', 'Red')->call('addOptionValue', 0)
        ->set('optionGroups.0.draft', 'Blue')->call('addOptionValue', 0)
        ->set('optionGroups.0.draft', 'Green')->call('addOptionValue', 0)
        ->call('addOptionGroup')
        ->set('optionGroups.1.name', 'Size')
        ->set('optionGroups.1.draft', 'S')->call('addOptionValue', 1)
        ->set('optionGroups.1.draft', 'M')->call('addOptionValue', 1)
        ->set('bulkPrice', '25.00')->call('applyPriceToAll')
        ->set('bulkStock', '10')->call('applyStockToAll')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $product = Product::query()->where('store_id', $seller->store->id)->firstOrFail();

    expect($product->options()->count())->toBe(2)
        ->and($product->variants()->count())->toBe(6)
        ->and($product->variants()->where('is_default', true)->count())->toBe(1);

    $labels = $product->variants()->pluck('options_label')->all();

    expect($labels)->toContain('Red / S', 'Red / M', 'Blue / S', 'Blue / M', 'Green / S', 'Green / M');

    $validValueIds = $product->options()->with('values')->get()
        ->flatMap(fn ($option) => $option->values->pluck('id'))
        ->all();

    foreach ($product->variants as $variant) {
        expect($variant->price_sen)->toBe(2500)
            ->and($variant->stock)->toBe(10)
            ->and($variant->option_value_ids)->toHaveCount(2);

        foreach ($variant->option_value_ids as $valueId) {
            expect($validValueIds)->toContain($valueId);
        }
    }
});

test('removing an option value on edit deletes the unordered variants for that combo', function () {
    $seller = productFormSeller();

    $product = Product::factory()
        ->withVariants(colour: 2, size: 2) // Red, Blue × S, M
        ->create(['store_id' => $seller->store->id]);

    expect($product->variants()->count())->toBe(4);

    $blueIds = $product->variants()->where('options_label', 'like', 'Blue%')->pluck('id');

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('removeOptionValue', 0, 1) // drop "Blue"
        ->call('saveDraft')
        ->assertHasNoErrors();

    expect($product->variants()->count())->toBe(2)
        ->and(ProductVariant::whereIn('id', $blueIds)->count())->toBe(0)
        ->and($product->variants()->pluck('options_label')->all())->toBe(['Red / S', 'Red / M'])
        ->and($product->options()->with('values')->get()->flatMap->values->pluck('value'))->not->toContain('Blue');
});

test('removing an option value is blocked when one of its variants has order history', function () {
    $seller = productFormSeller();

    $product = Product::factory()
        ->withVariants(colour: 2, size: 2)
        ->create(['store_id' => $seller->store->id]);

    $ordered = $product->variants()->where('options_label', 'Blue / S')->firstOrFail();
    productFormOrderItemFor($ordered);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('removeOptionValue', 0, 1) // drop "Blue"
        ->call('saveDraft')
        ->assertHasErrors(['matrix']);

    // Transaction rolled back — nothing was deleted.
    expect($product->variants()->count())->toBe(4)
        ->and($ordered->fresh())->not->toBeNull();
});

test('publish sets the product live with at least one image', function () {
    Storage::fake('public');

    $seller = productFormSeller();
    $category = productFormCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Published Prayer Mat')
        ->set('categoryTop', $category->id)
        ->set('price', '49.00')
        ->set('stock', '3')
        ->set('newImages', [UploadedFile::fake()->image('mat.jpg', 600, 600)])
        ->call('publish')
        ->assertHasNoErrors();

    $product = Product::query()->where('store_id', $seller->store->id)->firstOrFail();

    expect($product->status)->toBe(ProductStatus::Live)
        ->and($product->published_at)->not->toBeNull()
        ->and($product->getMedia('images'))->toHaveCount(1);
});

test('publish without any image is rejected', function () {
    $seller = productFormSeller();
    $category = productFormCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Imageless Gadget')
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->call('publish')
        ->assertHasErrors(['newImages']);

    expect(Product::query()->where('store_id', $seller->store->id)->count())->toBe(0);
});

test('publish goes to pending review when product approval is required', function () {
    Storage::fake('public');

    $seller = productFormSeller();
    $category = productFormCategory();

    $settings = app(ModerationSettings::class);
    $settings->require_product_approval = true;
    $settings->save();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Moderated Tea Set')
        ->set('categoryTop', $category->id)
        ->set('price', '88.00')
        ->set('stock', '4')
        ->set('newImages', [UploadedFile::fake()->image('tea.jpg', 600, 600)])
        ->call('publish')
        ->assertHasNoErrors();

    $product = Product::query()->where('store_id', $seller->store->id)->firstOrFail();

    expect($product->status)->toBe(ProductStatus::PendingReview)
        ->and($product->published_at)->toBeNull();
});

test('a category with children cannot be chosen as the final category', function () {
    $seller = productFormSeller();

    $parent = productFormCategory();
    Category::factory()->create(['parent_id' => $parent->id]);

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Deep Category Item')
        ->set('categoryTop', $parent->id)
        ->set('price', '5.00')
        ->call('saveDraft')
        ->assertHasErrors(['category']);
});

test('duplicate SKUs inside the matrix are rejected', function () {
    $seller = productFormSeller();
    $category = productFormCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Twin SKU Shirt')
        ->set('categoryTop', $category->id)
        ->set('hasVariations', true)
        ->set('optionGroups.0.name', 'Size')
        ->set('optionGroups.0.draft', 'S')->call('addOptionValue', 0)
        ->set('optionGroups.0.draft', 'M')->call('addOptionValue', 0)
        ->set('bulkPrice', '10.00')->call('applyPriceToAll')
        ->set('matrix.0.sku', 'SAME-SKU')
        ->set('matrix.1.sku', 'SAME-SKU')
        ->call('saveDraft')
        ->assertHasErrors(['matrix.1.sku']);
});

test('editing another store\'s product is forbidden', function () {
    $seller = productFormSeller();
    $foreign = Product::factory()->create();

    $this->actingAs($seller)
        ->get(route('seller.products.edit', $foreign))
        ->assertForbidden();
});

// ── Product video ───────────────────────────────────────────────────────

test('a product video uploads to the videos collection and the PDP renders a video player', function () {
    Storage::fake('public');

    $seller = productFormSeller();
    $category = productFormCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Honey With Video')
        ->set('categoryTop', $category->id)
        ->set('price', '19.90')
        ->set('stock', '5')
        ->set('newImages', [UploadedFile::fake()->image('honey.jpg', 600, 600)])
        ->set('newVideo', UploadedFile::fake()->create('demo.mp4', 5120, 'video/mp4'))
        ->call('publish')
        ->assertHasNoErrors();

    $product = Product::query()->where('store_id', $seller->store->id)->firstOrFail();

    expect($product->getMedia('videos'))->toHaveCount(1);

    Livewire::test(ProductDetail::class, ['product' => $product])
        ->assertSee('<video', false)
        ->assertSee('preload="none"', false)
        ->assertSee($product->getFirstMediaUrl('videos'), false);
});

test('replacing the video keeps a single file and removeExistingVideo clears it', function () {
    Storage::fake('public');

    $seller = productFormSeller();
    $product = Product::factory()->create(['store_id' => $seller->store->id]);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->set('newVideo', UploadedFile::fake()->create('first.mp4', 1024, 'video/mp4'))
        ->call('saveDraft')
        ->assertHasNoErrors();

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->set('newVideo', UploadedFile::fake()->create('second.webm', 1024, 'video/webm'))
        ->call('saveDraft')
        ->assertHasNoErrors();

    expect($product->refresh()->getMedia('videos'))->toHaveCount(1)
        ->and($product->getFirstMedia('videos')->file_name)->toBe('second.webm');

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('removeExistingVideo');

    expect($product->refresh()->getMedia('videos'))->toHaveCount(0);
});

test('a product video must be MP4 or WebM', function () {
    $seller = productFormSeller();
    $category = productFormCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Wrong Format Item')
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->set('newVideo', UploadedFile::fake()->create('clip.avi', 1024, 'video/x-msvideo'))
        ->call('saveDraft')
        ->assertHasErrors(['newVideo' => 'mimetypes']);

    expect(Product::query()->where('store_id', $seller->store->id)->count())->toBe(0);
});

test('a product video over 30MB is rejected', function () {
    $seller = productFormSeller();
    $category = productFormCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Oversized Video Item')
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->set('newVideo', UploadedFile::fake()->create('big.mp4', 30721, 'video/mp4'))
        ->call('saveDraft')
        ->assertHasErrors(['newVideo' => 'max']);

    expect(Product::query()->where('store_id', $seller->store->id)->count())->toBe(0);
});

test('ms translations are written only when filled and en is always written', function () {
    $seller = productFormSeller();
    $category = productFormCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Bilingual Sambal')
        ->set('name.ms', 'Sambal Dwibahasa')
        ->set('description.en', '<p>Spicy<script>alert(1)</script></p>')
        ->set('categoryTop', $category->id)
        ->set('price', '7.50')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $product = Product::query()->where('store_id', $seller->store->id)->firstOrFail();

    expect($product->getTranslation('name', 'en'))->toBe('Bilingual Sambal')
        ->and($product->getTranslation('name', 'ms', false))->toBe('Sambal Dwibahasa')
        ->and($product->getTranslation('description', 'en'))->toBe('<p>Spicyalert(1)</p>')
        ->and($product->variants()->firstOrFail()->price_sen)->toBe(750);
});
