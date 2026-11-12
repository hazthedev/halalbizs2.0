<?php

namespace App\Http\Middleware;

use App\Services\ContentDrafts;
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

        $response = $next($request);

        // A preview is one person's view of unpublished work: never stored by a browser or proxy.
        if (app(ContentDrafts::class)->previewing) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
