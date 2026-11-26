<?php

namespace App\Support;

use App\Http\Resources\CategoryResource;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/**
 * Category and brand pick-lists for forms, kept as PLAIN arrays (PROD has
 * cache.serializable_classes off, so cached Eloquent models come back broken).
 * Flushed whenever a Category or Brand is saved or deleted
 * (CatalogOptionsServiceProvider); the TTL only bounds mass updates that skip
 * model events.
 */
final class CatalogOptions
{
    private const TTL = 300;

    /** @return list<array{id: int, parent_id: ?int, name: string}> active categories in menu order */
    public static function categories(): array
    {
        return Cache::remember('catalog-options.categories.'.app()->getLocale(), self::TTL, fn () => Category::query()
            ->active()
            ->orderBy('position')
            ->get(['id', 'parent_id', 'name'])
            ->map(fn (Category $category) => [
                'id' => (int) $category->id,
                'parent_id' => $category->parent_id === null ? null : (int) $category->parent_id,
                'name' => (string) $category->getTranslation('name', app()->getLocale()),
            ])
            ->all());
    }

    /** @return list<array{id: int, name: string}> */
    public static function brands(): array
    {
        return Cache::remember('catalog-options.brands', self::TTL, fn () => Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand) => ['id' => (int) $brand->id, 'name' => (string) $brand->name])
            ->all());
    }

    /**
     * The public API list (id, slug, name, parent_id), plain arrays, per locale.
     *
     * @return list<array{id: int, slug: string, name: string, parent_id: ?int}>
     */
    public static function apiCategories(): array
    {
        return Cache::remember('api.categories.'.app()->getLocale(), self::TTL, fn () => Category::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->map(fn (Category $category) => (new CategoryResource($category))->resolve())
            ->all());
    }

    /** Active categories whose parent is $parentId (null = top level), menu order. */
    public static function childrenOf(?int $parentId): array
    {
        return array_values(array_filter(self::categories(), fn (array $row) => $row['parent_id'] === $parentId));
    }

    public static function flush(): void
    {
        foreach (ContentLocales::ALL as $locale) {
            Cache::forget('catalog-options.categories.'.$locale);
            Cache::forget('api.categories.'.$locale);
        }

        Cache::forget('catalog-options.brands');
    }
}
