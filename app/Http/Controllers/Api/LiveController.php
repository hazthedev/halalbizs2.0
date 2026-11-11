<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\LiveSession;
use App\Support\LiveVideoUrl;
use Illuminate\Http\JsonResponse;

/** Public list of sessions live right now (the app's Live pill sheet). */
class LiveController extends Controller
{
    public function index(): JsonResponse
    {
        if (! config('live.enabled', true)) {
            return ApiResponse::ok([]);
        }

        return ApiResponse::ok(LiveSession::query()->live()
            ->with('store:id,name,slug')
            ->latest('started_at')
            ->limit(50)
            ->get()
            ->map(fn (LiveSession $s) => [
                'slug' => $s->slug,
                'title' => $s->title,
                'store_name' => $s->store?->name,
                'store_slug' => $s->store?->slug,
                'thumbnail' => LiveVideoUrl::thumbnail($s->video_url),
                'platform' => LiveVideoUrl::parse($s->video_url)['platform'] ?? null,
                'started_at' => $s->started_at?->toIso8601String(),
            ])->values()->all());
    }

    /**
     * One room, same visibility as the web route (/live/{slug}: any status,
     * 404 only when the feature is off). Rail = the store-visible products
     * (Product::live), the shape every other product list uses.
     */
    public function show(LiveSession $session): JsonResponse
    {
        abort_unless(config('live.enabled', true), 404);

        $video = LiveVideoUrl::parse($session->video_url);
        $session->load('store:id,name,slug');

        return ApiResponse::ok([
            'slug' => $session->slug,
            'title' => $session->title,
            'store' => ['name' => $session->store?->name, 'slug' => $session->store?->slug],
            'platform' => $video['platform'] ?? null,
            'embed_url' => $video['embed'] ?? null,
            'external_url' => $video['link'] ?? null,
            'is_live' => $session->isLive(),
            'started_at' => $session->started_at?->toIso8601String(),
            'products' => ProductResource::collection(
                $session->products()->live()->with(['halalCertificate', 'variants', 'media', 'store'])->get()
            )->resolve(),
        ]);
    }
}
