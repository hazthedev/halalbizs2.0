<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Support\CatalogOptions;
use Illuminate\Support\ServiceProvider;

/** Drops the cached category/brand pick-lists whenever either table changes. */
class CatalogOptionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach ([Category::class, Brand::class] as $model) {
            $model::saved(fn () => CatalogOptions::flush());
            $model::deleted(fn () => CatalogOptions::flush());
        }
    }
}
