<?php

use App\Models\Banner;
use App\Models\HelpArticle;
use App\Models\HomeSection;
use App\Models\Page;
use App\Models\Product;
use App\Support\ContentLocales;
use Database\Seeders\CategorySeeder;
use Database\Seeders\HalalCatalogueSeeder;
use Database\Seeders\HelpArticleSeeder;
use Database\Seeders\HomeSectionSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\Support\CatalogueProductName;
use Database\Seeders\VietnameseContentSeeder;

test('Vietnamese catalogue source covers every seeded product and category', function () {
    $catalogue = json_decode(file_get_contents(database_path('seeders/data/halalbizs-catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
    $vi = json_decode(file_get_contents(database_path('seeders/data/vietnamese-catalogue.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($catalogue['products'])->toHaveCount(166)
        ->and($vi['products'])->toHaveCount(166)
        ->and($vi['categories'])->toHaveCount(33);

    foreach ($catalogue['products'] as $product) {
        expect(trim($vi['products'][$product['name_en']] ?? $vi['products'][$product['name_ms']] ?? ''))->not->toBe('');
    }
});

test('effective English catalogue names do not reuse generated Malay titles', function () {
    $catalogue = json_decode(file_get_contents(database_path('seeders/data/halalbizs-catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
    $generated = collect($catalogue['products'])->where('source', 'generated');

    expect($generated)->toHaveCount(124)
        ->and($generated->map(fn (array $row): string => CatalogueProductName::for($row))->unique())->toHaveCount(124)
        ->and(CatalogueProductName::for($generated->firstWhere('key', 'tuna-in-brine')))->toBe('Tuna In Brine')
        ->and(CatalogueProductName::for($generated->firstWhere('key', 'honey-with-propolis')))->toBe('Honey With Propolis')
        ->and(CatalogueProductName::for($generated->firstWhere('key', 'spf50-sunscreen')))->toBe('SPF 50 Sunscreen');

    foreach ($generated as $row) {
        expect(CatalogueProductName::for($row))->not->toBe($row['name_ms']);
    }

    $reference = collect($catalogue['products'])->firstWhere('key', 'tuna-dalam-minyak');
    expect(CatalogueProductName::for($reference))->toBe('Tuna in Oil');
});

test('catalogue reseed corrects a legacy Malay English title without duplicating its product or variant', function () {
    $this->seed([RoleSeeder::class, CategorySeeder::class, HalalCatalogueSeeder::class]);

    $product = Product::where('slug', 'tuna-dalam-air-garam')->sole();
    $variant = $product->variants()->sole();

    $this->seed(HalalCatalogueSeeder::class);

    $corrected = Product::findOrFail($product->id);

    expect($corrected->getTranslation('name', 'en', false))->toBe('Tuna In Brine')
        ->and($corrected->slug)->toBe('tuna-dalam-air-garam')
        ->and(Product::whereIn('slug', ['tuna-in-brine', 'tuna-dalam-air-garam'])->count())->toBe(1)
        ->and($corrected->variants()->sole()->id)->toBe($variant->id);
});

test('Vietnamese CMS backfill is complete and preserves administrator copy', function () {
    $this->seed([PageSeeder::class, HelpArticleSeeder::class, HomeSectionSeeder::class]);

    foreach ((require database_path('seeders/data/vietnamese-cms.php'))['banners'] as $link => $_copy) {
        Banner::create(['link_url' => $link, 'title' => ['en' => $link], 'position' => Banner::count()]);
    }

    $this->seed(VietnameseContentSeeder::class);

    expect(Page::whereRaw("json_extract(title, '$.vi') is not null")->count())->toBe(6)
        ->and(HelpArticle::whereRaw("json_extract(title, '$.vi') is not null")->count())->toBe(10)
        ->and(HomeSection::whereNotNull('title')->whereRaw("json_extract(title, '$.vi') is not null")->count())->toBe(5)
        ->and(Banner::whereRaw("json_extract(title, '$.vi') is not null")->count())->toBe(5);

    $page = Page::where('slug', 'about')->sole();
    $page->setTranslation('title', 'vi', 'Bản dịch do quản trị viên viết');
    $page->save();

    $this->seed(VietnameseContentSeeder::class);

    expect($page->refresh()->getTranslation('title', 'vi', false))->toBe('Bản dịch do quản trị viên viết');
});

test('content locale writer removes blank Vietnamese so English fallback applies', function () {
    $page = Page::create([
        'slug' => 'locale-contract',
        'title' => ['en' => 'English title', 'vi' => 'Tiêu đề'],
        'body' => ['en' => 'English body'],
        'is_active' => true,
    ]);

    ContentLocales::write($page, 'title', ['en' => 'English title', 'ms' => '', 'vi' => '']);
    $page->save();

    expect($page->refresh()->getTranslation('title', 'vi', false))->toBe('')
        ->and($page->getTranslation('title', 'vi'))->toBe('English title');
});

test('search documents include Vietnamese product copy', function () {
    $product = Product::factory()->create([
        'name' => ['en' => 'Rice Crackers', 'vi' => 'Bánh gạo giòn'],
        'description' => ['en' => 'Crisp snack', 'vi' => 'Món ăn nhẹ giòn'],
    ]);

    expect($product->toSearchableArray())
        ->toMatchArray([
            'name_vi' => 'Bánh gạo giòn',
            'description_vi' => 'Món ăn nhẹ giòn',
        ]);
});
