<?php

namespace App\Http\Middleware;

use App\Models\ThemeAsset;
use App\Services\ContentDrafts;
use App\Settings\ThemeSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `?preview=1` shows content drafts instead of the live content, but only to
 * signed-in staff who may manage content. Anyone else gets the live page.
 * On the API the staff member is recognised by their Sanctum token.
 */
class PreviewDrafts
{
    public function handle(Request $request, Closure $next): Response
    {
        app(ContentDrafts::class)->previewing = $request->boolean('preview')
            && (bool) ($request->user() ?? auth('sanctum')->user())?->can('cms.manage');

        $previewing = app(ContentDrafts::class)->previewing;

        // The theme is settings, not rows: put its draft on them before the page renders.
        if ($previewing && ($hero = ThemeAsset::where('key', 'hero')->first()) !== null) {
            app(ContentDrafts::class)->preview($hero);
        }

        $response = $next($request);

        // A preview is one person's view of unpublished work: never stored by a browser or proxy.
        if ($previewing) {
            $response->headers->set('Cache-Control', 'no-store, private');
            // The draft was only in memory; the next request in this process must see the live theme.
            app(ThemeSettings::class)->refresh();
        }

        return $response;
    }
}
