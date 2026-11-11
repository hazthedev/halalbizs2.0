<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
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
}
