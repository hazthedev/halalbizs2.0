<?php

use App\Enums\ProductStatus;
use App\Livewire\Admin\System\SearchInsights;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchLog;
use App\Models\UrlRedirect;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

afterEach(fn () => File::delete(public_path('sitemap.xml')));

// ── Sitemap ─────────────────────────────────────────────────────────────

test('sitemap:generate writes live product and store URLs and excludes drafts', function () {
    $live = Product::factory()->create(['status' => ProductStatus::Live]);
    $draft = Product::factory()->create(['status' => ProductStatus::Draft]);

    $this->artisan('sitemap:generate')->assertSuccessful();

    expect(File::exists(public_path('sitemap.xml')))->toBeTrue();

    $xml = File::get(public_path('sitemap.xml'));

    expect($xml)->toContain('<urlset')
        ->toContain('/p/'.$live->slug)
        ->toContain($live->store->slug.'.'.config('app.store_subdomain_base')) // stores live on subdomains
        ->toContain('/c/'.$live->category->slug)
        ->not->toContain('/p/'.$draft->slug);
});

// ── url_redirects middleware ────────────────────────────────────────────

test('an old product path 301s to the new path and increments hits', function () {
    $product = Product::factory()->create();

    UrlRedirect::create([
        'old_path' => '/p/some-retired-slug',
        'new_path' => '/p/'.$product->slug,
        'status_code' => 301,
    ]);

    $this->get('/p/some-retired-slug')
        ->assertStatus(301)
        ->assertRedirect('/p/'.$product->slug);

    expect(UrlRedirect::where('old_path', '/p/some-retired-slug')->sole()->hits)->toBe(1);
});

test('a 404 with no matching redirect stays a 404', function () {
    $this->get('/p/never-existed')->assertNotFound();
});

// ── Slug-change observers ───────────────────────────────────────────────

test('renaming a product writes a /p/ url redirect from the old slug', function () {
    $product = Product::factory()->create();
    $oldSlug = $product->slug;

    $product->setTranslation('name', 'en', 'Completely Renamed Halal Product')->save();
    $product->refresh();

    expect($product->slug)->not->toBe($oldSlug);

    $redirect = UrlRedirect::where('old_path', '/p/'.$oldSlug)->sole();

    expect($redirect->new_path)->toBe('/p/'.$product->slug)
        ->and($redirect->status_code)->toBe(301);

    // And the redirect actually works end to end.
    $this->get('/p/'.$oldSlug)->assertRedirect('/p/'.$product->slug);
});

test('renaming a category writes a /c/ redirect and renames stay deduplicated', function () {
    $category = Category::factory()->create();
    $first = $category->slug;

    $category->setTranslation('name', 'en', 'Renamed Once Category')->save();
    $second = $category->fresh()->slug;

    // Renaming again re-points the earlier redirect — no duplicate old_path.
    $category->fresh()->setTranslation('name', 'en', 'Renamed Twice Category')->save();
    $final = $category->fresh()->slug;

    expect(UrlRedirect::where('old_path', '/c/'.$first)->sole()->new_path)->toBe('/c/'.$final)
        ->and(UrlRedirect::where('old_path', '/c/'.$second)->sole()->new_path)->toBe('/c/'.$final);
});

// ── Search insights ─────────────────────────────────────────────────────

test('search insights lists zero-result and trending terms with the 14d count line', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    $admin->assignRole('admin');

    SearchLog::create(['term' => 'unicorn floss', 'results_count' => 0]);
    SearchLog::create(['term' => 'unicorn floss', 'results_count' => 0]);
    SearchLog::create(['term' => 'prayer mat', 'results_count' => 12]);

    $this->actingAs($admin)->get(route('admin.system.search'))->assertOk();

    Livewire::actingAs($admin)
        ->test(SearchInsights::class)
        ->assertSee('unicorn floss')   // zero-result report
        ->assertSee('prayer mat')      // trending
        ->assertSee('3 searches in the last 14 days');
});
