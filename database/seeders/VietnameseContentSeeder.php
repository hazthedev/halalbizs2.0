<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use App\Models\HelpArticle;
use App\Models\HomeSection;
use App\Models\Page;
use App\Models\Product;
use Database\Seeders\Support\CatalogueProductName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Adds Vietnamese to existing catalogue/CMS rows without overwriting content
 * an administrator has already authored. Safe to run on every deployment.
 */
class VietnameseContentSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = json_decode(
            File::get(database_path('seeders/data/halalbizs-catalogue.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $viCatalogue = json_decode(
            File::get(database_path('seeders/data/vietnamese-catalogue.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $cms = require database_path('seeders/data/vietnamese-cms.php');

        $this->categories($viCatalogue['categories']);
        $this->products($catalogue['products'], $viCatalogue);
        $this->pages($cms['pages']);
        $this->help($cms['help']);
        $this->home($cms['home']);
        $this->banners($cms['banners']);
    }

    /** @param array<string, string> $translations */
    private function categories(array $translations): void
    {
        Category::query()->each(function (Category $category) use ($translations): void {
            $english = $category->getTranslation('name', 'en', false);

            if (isset($translations[$english])) {
                $this->fill($category, 'name', $translations[$english]);
            }
        });
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function products(array $rows, array $translations): void
    {
        $byEnglish = collect($rows)->map(function (array $row): array {
            $row['legacy_name_en'] = $row['name_en'];
            $row['name_en'] = CatalogueProductName::for($row);

            return $row;
        })->keyBy('name_en');

        Product::withTrashed()->with('category')->each(function (Product $product) use ($byEnglish, $translations): void {
            $english = $product->getTranslation('name', 'en', false);
            $row = $byEnglish->get($english);

            if (! is_array($row)) {
                return;
            }

            $name = $translations['products'][$row['legacy_name_en']]
                ?? $translations['products'][$row['name_ms']]
                ?? null;

            if (! is_string($name)) {
                return;
            }
            $leaf = $translations['categories'][$row['leaf']] ?? $row['leaf'];
            $unit = trim((string) ($row['unit'] ?? ''));
            $unitSentence = $unit !== '' ? ' '.$unit.'.' : '';
            $description = "{$name}.{$unitSentence} Được {$row['certifier']} chứng nhận và xếp trong danh mục {$leaf}. Chứng nhận được liên kết với SKU này, không phải với cửa hàng.";

            $this->fill($product, 'name', $name);
            $this->fill($product, 'description', $description);
        });
    }

    /** @param array<string, array{title: string, body: string}> $translations */
    private function pages(array $translations): void
    {
        $brand = config('app.name', 'HalalBizs');

        foreach ($translations as $slug => $copy) {
            $page = Page::where('slug', $slug)->first();

            if ($page === null) {
                continue;
            }

            $this->fill($page, 'title', $copy['title']);
            $this->fill($page, 'body', str_replace(':brand', $brand, $copy['body']));
        }
    }

    /** @param array<string, array{0: string, 1: string}> $translations */
    private function help(array $translations): void
    {
        HelpArticle::query()->each(function (HelpArticle $article) use ($translations): void {
            $key = $article->category->value.':'.$article->position;

            if (! isset($translations[$key])) {
                return;
            }

            $this->fill($article, 'title', $translations[$key][0]);
            $this->fill($article, 'body', $translations[$key][1]);
        });
    }

    /** @param array<string, string> $translations */
    private function home(array $translations): void
    {
        foreach ($translations as $type => $title) {
            $section = HomeSection::where('type', $type)->first();

            if ($section !== null) {
                $this->fill($section, 'title', $title);
            }
        }
    }

    /** @param array<string, array{0: string, 1: string, 2: string}> $translations */
    private function banners(array $translations): void
    {
        foreach ($translations as $link => [$title, $subtitle, $cta]) {
            $banner = Banner::where('link_url', $link)->first();

            if ($banner === null) {
                continue;
            }

            $this->fill($banner, 'title', $title);
            $this->fill($banner, 'subtitle', $subtitle);
            $this->fill($banner, 'cta_label', $cta);
        }
    }

    private function fill(object $model, string $field, string $value): void
    {
        if (trim((string) $model->getTranslation($field, 'vi', false)) !== '') {
            return;
        }

        $model->setTranslation($field, 'vi', $value);
        $model->save();
    }
}
