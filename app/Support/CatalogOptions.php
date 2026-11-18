<?php

namespace App\Support;

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

    /** Active categories whose parent is $parentId (null = top level), menu order. */
    public static function childrenOf(?int $parentId): array
    {
        return array_values(array_filter(self::categories(), fn (array $row) => $row['parent_id'] === $parentId));
    }

    public static function flush(): void
    {
        foreach (ContentLocales::ALL as $locale) {
            Cache::forget('catalog-options.categories.'.$locale);
        }

        Cache::forget('catalog-options.brands');
    }
}
