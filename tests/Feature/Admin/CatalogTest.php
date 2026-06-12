<?php

use App\Enums\ProductStatus;
use App\Livewire\Admin\Catalog\Attributes as AttributesComponent;
use App\Livewire\Admin\Catalog\Brands as BrandsComponent;
use App\Livewire\Admin\Catalog\Categories as CategoriesComponent;
use App\Livewire\Admin\Catalog\Moderation as ModerationComponent;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ProductModerationNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function catalogAdmin(): User
{
    (new RoleSeeder)->run();

    $admin = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    $admin->assignRole('admin');

    return $admin;
}

function catalogPendingProduct(): Product
{
    return Product::factory()->create([
        'status' => ProductStatus::PendingReview,
        'published_at' => null,
    ]);
}

// ── Categories ──────────────────────────────────────────────────────────

test('admin can create a root and a child category with en name and generated slug', function () {
    $admin = catalogAdmin();

    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('startCreate')
        ->set('name.en', 'Halal Gourmet')
        ->set('name.ms', 'Gourmet Halal')
        ->set('commissionRate', '7.5')
        ->call('save')
        ->assertHasNoErrors();

    $root = Category::query()->where('slug', 'halal-gourmet')->firstOrFail();

    expect($root->getTranslation('name', 'en'))->toBe('Halal Gourmet')
        ->and($root->getTranslation('name', 'ms', false))->toBe('Gourmet Halal')
        ->and($root->parent_id)->toBeNull()
        ->and((float) $root->commission_rate)->toBe(7.5);

    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('startCreate', $root->id)
        ->set('name.en', 'Snacks')
        ->call('save')
        ->assertHasNoErrors();

    $child = Category::query()->where('slug', 'snacks')->firstOrFail();

    expect($child->parent_id)->toBe($root->id)
        ->and($child->getTranslation('name', 'en'))->toBe('Snacks');
});

test('adding a child under a depth-3 category is rejected', function () {
    $admin = catalogAdmin();

    $root = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $root->id]);
    $leaf = Category::factory()->create(['parent_id' => $child->id]);

    // The "Add child" entry point refuses to open the form.
    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('startCreate', $leaf->id)
        ->assertHasErrors(['parent'])
        ->assertSet('formOpen', false);

    // And save re-checks depth even if the parent id is forced in.
    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('startCreate')
        ->set('parentId', $leaf->id)
        ->set('name.en', 'Too Deep')
        ->call('save')
        ->assertHasErrors(['parent']);

    expect(Category::count())->toBe(3);
});

test('reordering a category swaps positions within the parent', function () {
    $admin = catalogAdmin();

    $first = Category::factory()->create(['position' => 0]);
    $second = Category::factory()->create(['position' => 1]);

    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('move', $second->id, -1);

    expect($second->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1);

    // Moving the top item further up is a no-op.
    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('move', $second->id, -1);

    expect($second->fresh()->position)->toBe(0);
});

test('attribute mapping syncs the category_attribute pivot', function () {
    $admin = catalogAdmin();

    $category = Category::factory()->create();
    $material = Attribute::create(['name' => ['en' => 'Material'], 'is_filterable' => true]);
    $colour = Attribute::create(['name' => ['en' => 'Colour'], 'is_filterable' => true]);

    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('edit', $category->id)
        ->set('selectedAttributeIds', [(string) $material->id, (string) $colour->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($category->attributes()->pluck('attributes.id')->all())
        ->toEqualCanonicalizing([$material->id, $colour->id]);

    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('edit', $category->id)
        ->set('selectedAttributeIds', [(string) $colour->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($category->attributes()->pluck('attributes.id')->all())->toBe([$colour->id]);
});

test('deleting a category is blocked while it has products or children, allowed when empty', function () {
    $admin = catalogAdmin();

    $withProduct = Category::factory()->create();
    Product::factory()->create(['category_id' => $withProduct->id]);

    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('delete', $withProduct->id);

    expect(Category::find($withProduct->id))->not->toBeNull();

    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('delete', $parent->id);

    expect(Category::find($parent->id))->not->toBeNull();

    Livewire::actingAs($admin)
        ->test(CategoriesComponent::class)
        ->call('delete', $child->id);

    expect(Category::find($child->id))->toBeNull();
});

// ── Attributes ──────────────────────────────────────────────────────────

test('admin can create, rename, toggle, and delete an attribute and manage its values', function () {
    $admin = catalogAdmin();

    // Create — slug generated from the en name.
    Livewire::actingAs($admin)
        ->test(AttributesComponent::class)
        ->set('name.en', 'Material')
        ->set('name.ms', 'Bahan')
        ->call('create')
        ->assertHasNoErrors();

    $attribute = Attribute::query()->where('slug', 'material')->firstOrFail();

    expect($attribute->getTranslation('name', 'en'))->toBe('Material')
        ->and($attribute->getTranslation('name', 'ms', false))->toBe('Bahan')
        ->and($attribute->is_filterable)->toBeTrue();

    // Value add — en required, ms optional.
    $component = Livewire::actingAs($admin)
        ->test(AttributesComponent::class)
        ->call('manageValues', $attribute->id)
        ->set('valueDraft.en', '')
        ->call('addValue')
        ->assertHasErrors(['valueDraft.en']);

    $component
        ->set('valueDraft.en', 'Cotton')
        ->set('valueDraft.ms', 'Kapas')
        ->call('addValue')
        ->assertHasNoErrors();

    $value = $attribute->values()->firstOrFail();

    expect($value->getTranslation('value', 'en'))->toBe('Cotton')
        ->and($value->getTranslation('value', 'ms', false))->toBe('Kapas')
        ->and($value->position)->toBe(0);

    // Rename + filterable toggle.
    Livewire::actingAs($admin)
        ->test(AttributesComponent::class)
        ->call('edit', $attribute->id)
        ->set('editName.en', 'Fabric')
        ->call('update')
        ->assertHasNoErrors()
        ->call('toggleFilterable', $attribute->id);

    $attribute->refresh();

    expect($attribute->getTranslation('name', 'en'))->toBe('Fabric')
        ->and($attribute->is_filterable)->toBeFalse();

    // Remove value, then delete the attribute.
    Livewire::actingAs($admin)
        ->test(AttributesComponent::class)
        ->call('removeValue', $value->id)
        ->call('deleteAttribute', $attribute->id);

    expect(AttributeValue::find($value->id))->toBeNull()
        ->and(Attribute::find($attribute->id))->toBeNull();
});

// ── Brands ──────────────────────────────────────────────────────────────

test('admin can create, rename, toggle, and delete brands; deletion nulls product brand links', function () {
    $admin = catalogAdmin();

    Livewire::actingAs($admin)
        ->test(BrandsComponent::class)
        ->set('newName', 'Tefal')
        ->call('create')
        ->assertHasNoErrors();

    $brand = Brand::query()->where('slug', 'tefal')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(BrandsComponent::class)
        ->call('toggleActive', $brand->id)
        ->call('edit', $brand->id)
        ->set('editName', 'Tefal Malaysia')
        ->call('update')
        ->assertHasNoErrors();

    $brand->refresh();

    expect($brand->is_active)->toBeFalse()
        ->and($brand->name)->toBe('Tefal Malaysia');

    // Delete with products — products keep selling, brand link nulled.
    $product = Product::factory()->create(['brand_id' => $brand->id]);

    Livewire::actingAs($admin)
        ->test(BrandsComponent::class)
        ->call('delete', $brand->id);

    expect(Brand::find($brand->id))->toBeNull()
        ->and($product->fresh()->brand_id)->toBeNull();
});

// ── Moderation ──────────────────────────────────────────────────────────

test('approving a pending product makes it live, stamps published_at, and notifies the owner', function () {
    Notification::fake();

    $admin = catalogAdmin();
    $product = catalogPendingProduct();
    $owner = $product->store->user;

    Livewire::actingAs($admin)
        ->test(ModerationComponent::class)
        ->call('approve', $product->id);

    $product->refresh();

    expect($product->status)->toBe(ProductStatus::Live)
        ->and($product->published_at)->not->toBeNull();

    Notification::assertSentTo(
        $owner,
        ProductModerationNotification::class,
        fn (ProductModerationNotification $notification) => $notification->action === 'approved'
            && $notification->product->is($product),
    );
});

test('rejecting requires a reason, then moves the product to draft and notifies the owner', function () {
    Notification::fake();

    $admin = catalogAdmin();
    $product = catalogPendingProduct();
    $owner = $product->store->user;

    $component = Livewire::actingAs($admin)
        ->test(ModerationComponent::class)
        ->call('startReject', $product->id)
        ->set('rejectReason', '')
        ->call('confirmReject')
        ->assertHasErrors(['rejectReason']);

    expect($product->fresh()->status)->toBe(ProductStatus::PendingReview);
    Notification::assertNothingSent();

    $component
        ->set('rejectReason', 'Images are blurry — re-shoot on a plain background.')
        ->call('confirmReject')
        ->assertHasNoErrors();

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);

    Notification::assertSentTo(
        $owner,
        ProductModerationNotification::class,
        fn (ProductModerationNotification $notification) => $notification->action === 'rejected'
            && $notification->reason === 'Images are blurry — re-shoot on a plain background.',
    );
});

test('banning a pending product sets banned status and notifies the owner', function () {
    Notification::fake();

    $admin = catalogAdmin();
    $product = catalogPendingProduct();
    $owner = $product->store->user;

    Livewire::actingAs($admin)
        ->test(ModerationComponent::class)
        ->call('ban', $product->id);

    expect($product->fresh()->status)->toBe(ProductStatus::Banned);

    Notification::assertSentTo(
        $owner,
        ProductModerationNotification::class,
        fn (ProductModerationNotification $notification) => $notification->action === 'banned',
    );
});

test('bulk approve takes every selected pending product live', function () {
    Notification::fake();

    $admin = catalogAdmin();
    $first = catalogPendingProduct();
    $second = catalogPendingProduct();

    Livewire::actingAs($admin)
        ->test(ModerationComponent::class)
        ->set('selected', [(string) $first->id, (string) $second->id])
        ->call('bulkApprove');

    expect($first->fresh()->status)->toBe(ProductStatus::Live)
        ->and($second->fresh()->status)->toBe(ProductStatus::Live);
});

// ── Access + rendering ──────────────────────────────────────────────────

test('catalog pages render for an admin', function () {
    $admin = catalogAdmin();

    $root = Category::factory()->create(['position' => 0]);
    Category::factory()->create(['parent_id' => $root->id, 'position' => 0]);
    Attribute::create(['name' => ['en' => 'Material'], 'is_filterable' => true]);
    Brand::factory()->create();
    catalogPendingProduct();
    Product::factory()->create(['status' => ProductStatus::Banned]);

    $this->actingAs($admin)->get(route('admin.catalog.categories'))->assertOk();
    $this->actingAs($admin)->get(route('admin.catalog.attributes'))->assertOk();
    $this->actingAs($admin)->get(route('admin.catalog.brands'))->assertOk();
    $this->actingAs($admin)->get(route('admin.catalog.moderation'))->assertOk();
});

test('the moderation page explains when product approval is off', function () {
    $admin = catalogAdmin();

    $this->actingAs($admin)
        ->get(route('admin.catalog.moderation'))
        ->assertOk()
        ->assertSee('Product approval is off');
});

test('non-admins get 403 on every catalog page and guests are sent to login', function () {
    (new RoleSeeder)->run();

    $user = User::factory()->create();
    $user->assignRole('buyer');

    foreach (['admin.catalog.categories', 'admin.catalog.attributes', 'admin.catalog.brands', 'admin.catalog.moderation'] as $routeName) {
        $this->actingAs($user)->get(route($routeName))->assertForbidden();
    }

    auth()->logout();

    $this->get(route('admin.catalog.categories'))->assertRedirect(route('login'));
});
