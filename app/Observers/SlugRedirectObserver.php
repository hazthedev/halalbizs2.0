<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\UrlRedirect;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/09 §F — auto-insert url_redirects on slug change. HasSlug regenerates
 * the slug whenever the source name changes, so a rename silently 404s the
 * old storefront URL; this observer turns that 404 into a 301 (see
 * HandleUrlRedirects). `saved` fires before syncOriginal(), so
 * wasChanged('slug') + getOriginal('slug') still see the previous value.
 */
class SlugRedirectObserver
{
    private const PREFIXES = [
        Product::class => '/p/',
        Store::class => '/s/',
        Category::class => '/c/',
    ];

    public function saved(Model $model): void
    {
        $oldSlug = $model->getOriginal('slug');

        if (! $model->wasChanged('slug') || $oldSlug === null || $oldSlug === $model->slug) {
            return;
        }

        $prefix = self::PREFIXES[$model::class];
        $oldPath = $prefix.$oldSlug;
        $newPath = $prefix.$model->slug;

        // old_path is unique — updateOrCreate keeps re-renames from duplicating.
        UrlRedirect::updateOrCreate(
            ['old_path' => $oldPath],
            ['new_path' => $newPath, 'status_code' => 301],
        );

        // Re-point older redirects that ended at the previous slug (no
        // chains), then drop any rule shadowing the now-live path.
        UrlRedirect::query()->where('new_path', $oldPath)->where('old_path', '!=', $oldPath)->update(['new_path' => $newPath]);
        UrlRedirect::query()->where('old_path', $newPath)->delete();
    }
}
