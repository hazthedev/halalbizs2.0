<?php

namespace App\Http\Middleware;

use App\Models\UrlRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/09 §F — url_redirects lookup. Deliberately cheap: the table is only
 * queried once the response is already a 404 (old slugs match real route
 * patterns like /p/{slug}, so binding fails inside the web group and the
 * rendered 404 passes back through here). Happy-path requests never pay.
 */
class HandleUrlRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND && $request->isMethod('GET')) {
            $redirect = UrlRedirect::query()
                ->where('old_path', '/'.ltrim($request->path(), '/'))
                ->first();

            if ($redirect !== null) {
                $redirect->increment('hits');

                return redirect($redirect->new_path, $redirect->status_code);
            }

            // Renamed store subdomain: old-slug.<base> → /s/old-slug rows
            // already exist, so reuse them and bounce to the new subdomain.
            $host = $request->getHost();
            $base = config('app.store_subdomain_base');

            if (str_ends_with($host, '.'.$base)) {
                $oldSlug = substr($host, 0, -strlen('.'.$base));
                $storeRedirect = UrlRedirect::query()->where('old_path', "/s/{$oldSlug}")->first();

                if ($storeRedirect !== null && str_starts_with($storeRedirect->new_path, '/s/')) {
                    $storeRedirect->increment('hits');
                    $newSlug = substr($storeRedirect->new_path, 3);
                    $scheme = $request->getScheme();

                    return redirect("{$scheme}://{$newSlug}.{$base}", $storeRedirect->status_code);
                }
            }
        }

        return $response;
    }
}
