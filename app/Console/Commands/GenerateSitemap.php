<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;

/**
 * docs/09 §F — nightly sitemap. Written by hand instead of pulling in
 * spatie/laravel-sitemap: a urlset of slugs is a ~30-line writer and a new
 * dependency needs justifying (CLAUDE.md hard rule 10). Output is the
 * static file public/sitemap.xml, served directly by the web server.
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Write public/sitemap.xml: home, live products, approved stores, active categories and pages';

    public function handle(): int
    {
        $urls = [[url('/'), now()]];

        foreach (Product::query()->live()->get(['slug', 'updated_at']) as $product) {
            $urls[] = [url('/p/'.$product->slug), $product->updated_at];
        }

        foreach (Store::query()->approved()->get(['slug', 'updated_at']) as $store) {
            $urls[] = [$store->subdomainUrl(), $store->updated_at];
        }

        foreach (Category::query()->active()->get(['slug', 'updated_at']) as $category) {
            $urls[] = [url('/c/'.$category->slug), $category->updated_at];
        }

        foreach (Page::query()->active()->get(['slug', 'updated_at']) as $page) {
            $urls[] = [url('/page/'.$page->slug), $page->updated_at];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as [$loc, $lastmod]) {
            $xml .= '  <url><loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'
                .'<lastmod>'.($lastmod ?? now())->toAtomString().'</lastmod></url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        file_put_contents(public_path('sitemap.xml'), $xml);

        $this->info('sitemap.xml written with '.count($urls).' URLs.');

        return self::SUCCESS;
    }
}
